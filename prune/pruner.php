<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune\prune;

/**
 * Selects and deletes old posts. Deletion goes exclusively through the core
 * function delete_posts(), which also removes attachments (rows and files),
 * reports, notifications and search index entries and resyncs topics/forums.
 */
class pruner
{
	/** @var string Config value (dynamic, m4_cursor): last topic the active run finished under rule "count"; 0: start of a cycle */
	const CURSOR = 'uflagmey_pp_cursor';

	/**
	 * @var int Rule "count", dry run and preview: topics per count query (rc1: no statement with thousands of OR
	 *			terms). Small on purpose: on MariaDB with outdated index statistics (e.g. after a bulk import) larger
	 *			lists made the optimizer read the whole forum (2,000 topics: 100 per query 6 s, 10-50 per query < 1 s).
	 */
	const COUNT_CHUNK = 25;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\user */
	protected $user;

	/** @var query_builder */
	protected $query_builder;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/** @var \phpbb\db\tools\tools_interface|null */
	protected $db_tools;

	/**
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\config\config				$config
	 * @param \phpbb\log\log_interface			$log
	 * @param \phpbb\user						$user
	 * @param query_builder						$query_builder
	 * @param string							$root_path
	 * @param string							$php_ext
	 * @param \phpbb\db\tools\tools_interface|null	$db_tools	null: built by an outdated container (rc1 added it);
	 *																then only the columns the schema marker announces are read
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, \phpbb\log\log_interface $log, \phpbb\user $user, query_builder $query_builder, $root_path, $php_ext, $db_tools = null)
	{
		$this->db = $db;
		$this->config = $config;
		$this->log = $log;
		$this->user = $user;
		$this->query_builder = $query_builder;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->db_tools = $db_tools;
	}

	/**
	 * Which setting columns to read: those the schema marker announces, and with a marker below 3
	 * also scope/rule columns that exist anyway (update incomplete, database restored, downgrade
	 * and back), so settings::doubt() can see a stored scope/rule the marker does not cover yet
	 * instead of silently falling back to a broader rule (rc1, "when in doubt, delete nothing").
	 *
	 * @return array	[bool scope columns, bool rule columns] for query_builder::forums_query()
	 */
	protected function columns()
	{
		$scope = schema::topic_scope($this->config);
		$rule = schema::keep_count($this->config);

		if (!$rule && $this->db_tools instanceof \phpbb\db\tools\tools_interface)
		{
			$table = $this->query_builder->forums_table();
			$scope = $scope || $this->db_tools->sql_column_exists($table, 'uflagmey_pp_scope');
			$rule = $scope && $this->db_tools->sql_column_exists($table, 'uflagmey_pp_rule');
		}

		return [$scope, $rule];
	}

	/**
	 * @param array $forum	Forum row
	 * @return bool	The stored settings are not understood: skip the forum (settings::doubt())
	 */
	protected function doubt(array $forum)
	{
		return settings::doubt($forum, schema::version($this->config));
	}

	/**
	 * Forums with post pruning switched on (post forums only).
	 *
	 * @return array
	 */
	public function get_forums()
	{
		list($scope, $rule) = $this->columns();
		$sql = $this->db->sql_build_query('SELECT', $this->query_builder->forums_query($scope, $rule));
		$result = $this->db->sql_query($sql);
		$forums = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $forums ?: [];
	}

	/**
	 * At least one post forum has post pruning switched on. Independent of the
	 * schema marker (see query_builder::active_forums_count_query()).
	 *
	 * @return bool
	 */
	public function has_forums()
	{
		$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $this->query_builder->active_forums_count_query()));
		$num = (int) $this->db->sql_fetchfield('num_forums');
		$this->db->sql_freeresult($result);

		return $num > 0;
	}

	/**
	 * One cron run. Active forums are processed first, oldest posts first, at
	 * most $batch posts in total. Dry-run forums are only counted and logged
	 * when no backlog is left, so a backlog that is worked off over several
	 * page requests produces exactly one dry-run log entry per cycle. That
	 * entry is written even with 0 candidates, as proof that the cron ran.
	 * Forums with scope "marked topics only" name the number of marked topics
	 * in their log entries; without any marked topic they get one entry per
	 * cycle saying so (dry run and active). Forums whose stored settings are
	 * not understood (settings::doubt()) are skipped, with one critical log
	 * entry per cycle, also written in the run that closes the cycle.
	 * Rule "count" works topic by topic (rc1, topic_rows()): the run stops when
	 * the batch is full and remembers the last finished topic (config CURSOR);
	 * the next run continues behind it, and count forums before the cursor's
	 * forum are skipped (finished earlier in this cycle). The cycle ends when a
	 * run leaves no backlog; the cursor is then 0 and the next cycle starts at
	 * the first topic again. Dry run and preview never touch the cursor.
	 *
	 * @param int	$now
	 * @param int	$batch	Maximum number of posts to delete in this run
	 * @return array ['backlog' => bool, 'pruned' => [forum_id => n], 'dryrun' => [forum_id => n]]
	 */
	public function run($now, $batch)
	{
		$remaining = max(1, (int) $batch);
		$backlog = false;
		$pruned = $dryrun = $no_topics = $skipped = [];
		list($scope, $rule) = $this->columns();
		$rows = $this->select($this->query_builder->forums_query($scope, $rule));
		if ($rows === false)
		{
			return $this->schema_mismatch($pruned, $dryrun);
		}

		$cursor = isset($this->config[self::CURSOR]) ? (int) $this->config[self::CURSOR] : 0;
		$cursor_forum = 0;
		if ($cursor > 0)
		{
			$at = $this->select($this->query_builder->topic_forum_query($cursor));
			if ($at === false)
			{
				return $this->schema_mismatch($pruned, $dryrun);
			}
			// Topic gone or moved away meanwhile: start the cycle over
			$cursor_forum = $at ? (int) $at[0]['forum_id'] : 0;
			$cursor = $cursor_forum ? $cursor : 0;
		}
		$last_done = $cursor;

		$forums = [];
		foreach ($rows as $forum)
		{
			if ($this->doubt($forum))
			{
				$skipped[] = $forum;
				continue;
			}
			$forums[] = $forum;
		}

		foreach ($forums as $forum)
		{
			if ((int) $forum['uflagmey_pp_mode'] !== query_builder::MODE_ACTIVE)
			{
				continue;
			}

			$topics = $this->marked_topics($forum);
			if ($topics === false)
			{
				return $this->schema_mismatch($pruned, $dryrun);
			}
			if ($topics === 0)
			{
				$no_topics[] = $forum;
				continue;
			}

			if ($this->query_builder->rule($forum) === query_builder::RULE_COUNT)
			{
				$forum_id = (int) $forum['forum_id'];
				if ($cursor_forum && $forum_id < $cursor_forum)
				{
					// Finished earlier in this cycle (forums run in forum_id order)
					continue;
				}
				if ($remaining <= 0)
				{
					$backlog = true;
					continue;
				}

				$result = $this->topic_rows($forum, $now, $remaining, ($forum_id === $cursor_forum) ? $cursor : 0, $last_done);
				if ($result === false)
				{
					return $this->schema_mismatch($pruned, $dryrun);
				}
				list($rows, $full) = $result;
				$backlog = $backlog || $full;
			}
			else
			{
				// One row more than the budget tells whether a backlog remains.
				$rows = $this->select($this->query_builder->candidates_query($forum, $now), $remaining + 1);
				if ($rows === false)
				{
					return $this->schema_mismatch($pruned, $dryrun);
				}

				if (count($rows) > $remaining)
				{
					$backlog = true;
					$rows = array_slice($rows, 0, $remaining);
				}
			}

			if (!count($rows))
			{
				continue;
			}

			$softdeleted = count(array_filter($rows, function ($row) {
				return (int) $row['post_visibility'] === ITEM_DELETED;
			}));
			$deleted = $this->delete($rows, (bool) $forum['uflagmey_pp_count_sync']);
			$remaining -= count($rows);
			$pruned[(int) $forum['forum_id']] = $deleted;

			if ($deleted)
			{
				$this->log_result('LOG_UFLAGMEY_PP_PRUNED', $forum, $deleted, $topics, min($softdeleted, $deleted));
			}
		}

		// Cursor: where the next run continues (backlog), 0 when the cycle is complete
		$next = $backlog ? $last_done : 0;
		if ($next !== (isset($this->config[self::CURSOR]) ? (int) $this->config[self::CURSOR] : 0))
		{
			$this->config->set(self::CURSOR, $next, false);
		}

		if (!$backlog)
		{
			foreach ($skipped as $forum)
			{
				$this->add_log('LOG_UFLAGMEY_PP_SKIPPED', [$forum['forum_name']], 'critical');
			}

			foreach ($no_topics as $forum)
			{
				$this->add_log('LOG_UFLAGMEY_PP_PRUNED_NO_TOPICS', [$forum['forum_name']]);
			}

			foreach ($forums as $forum)
			{
				if ((int) $forum['uflagmey_pp_mode'] !== query_builder::MODE_DRYRUN)
				{
					continue;
				}

				$count = $this->count($forum, $now);
				if ($count === false)
				{
					return $this->schema_mismatch($pruned, $dryrun);
				}

				$dryrun[(int) $forum['forum_id']] = $count['num'];
				if ($count['topics'] === 0)
				{
					$this->add_log('LOG_UFLAGMEY_PP_DRYRUN_NO_TOPICS', [$forum['forum_name']]);
					continue;
				}

				$this->log_result('LOG_UFLAGMEY_PP_DRYRUN', $forum, $count['num'], $count['topics'], $count['softdeleted']);
			}
		}

		return [
			'backlog'	=> $backlog,
			'pruned'	=> $pruned,
			'dryrun'	=> $dryrun,
		];
	}

	/**
	 * ACP preview ("calculate dry run now"): what a dry run of this forum would
	 * count right now, from the stored settings, optionally overlaid with the
	 * values in the ACP form, whatever the mode. Read only: no deletion, no log
	 * entry, no last_run, no cron lock. Same counting as the dry run of run()
	 * (count()), without batch limit.
	 *
	 * @param int	$forum_id
	 * @param int	$now
	 * @param array	$form	Values from the form, checked by settings::errors(); only
	 *						settings::PREVIEW_KEYS count, as integers
	 * @return array|null|false	['forum' => settings row used, 'post_forum' => bool, 'doubt' => bool, 'topics' => int|null,
	 *							'num' => int, 'softdeleted' => int, 'oldest' => int, 'newest' => int];
	 *							doubt: the settings are not understood, the cron skips the forum, nothing counted;
	 *							null: no such forum; false: a query failed
	 */
	public function preview($forum_id, $now, array $form = [])
	{
		list($scope, $rule) = $this->columns();
		$rows = $this->select($this->query_builder->forum_query($forum_id, $scope, $rule));
		if ($rows === false || !$rows)
		{
			return $rows === false ? false : null;
		}

		$forum = $rows[0];
		foreach (array_intersect_key($form, array_flip(settings::PREVIEW_KEYS)) as $key => $value)
		{
			// Only columns this schema state has (beta4 rows carry no rule)
			if (array_key_exists($key, $forum))
			{
				$forum[$key] = (int) $value;
			}
		}
		$post_forum = ((int) $forum['forum_type'] === FORUM_POST);
		$doubt = $this->doubt($forum);
		if ($doubt || !$post_forum)
		{
			return ['forum' => $forum, 'post_forum' => $post_forum, 'doubt' => $doubt, 'topics' => null, 'num' => 0, 'softdeleted' => 0, 'oldest' => 0, 'newest' => 0];
		}

		$count = $this->count($forum, $now);
		if ($count === false)
		{
			return false;
		}

		return ['forum' => $forum, 'post_forum' => $post_forum, 'doubt' => false] + $count;
	}

	/**
	 * Counts the candidates of one forum (dry run and ACP preview).
	 *
	 * @param array	$forum	Row from forums_query() or query_builder::forum_query()
	 * @param int	$now
	 * @return array|false	['topics' => int|null (see marked_topics()), 'num' => int, 'softdeleted' => int (of num),
	 *						'oldest' => int, 'newest' => int]; false: a query failed
	 */
	protected function count(array $forum, $now)
	{
		$topics = $this->marked_topics($forum);
		if ($topics === false)
		{
			return false;
		}

		$count = ['topics' => $topics, 'num' => 0, 'softdeleted' => 0, 'oldest' => 0, 'newest' => 0];
		if ($topics === 0)
		{
			return $count;
		}

		$bounds = $this->bounds($forum);
		if ($bounds === false)
		{
			return false;
		}
		if ($bounds !== null && !$bounds)
		{
			return $count;
		}

		// Rule "age": one query; rule "count": COUNT_CHUNK topics per query (rc1: no statement with
		// thousands of OR terms). The loop runs once per chunk of the forum's marked topics.
		foreach (($bounds === null) ? [null] : array_chunk($bounds, self::COUNT_CHUNK, true) as $chunk)
		{
			$rows = $this->select($this->query_builder->count_query($forum, $now, (array) $chunk));
			if ($rows === false)
			{
				return false;
			}

			$num = (int) $rows[0]['num_posts'];
			if (!$num)
			{
				continue;
			}
			$count['oldest'] = $count['num'] ? min($count['oldest'], (int) $rows[0]['oldest']) : (int) $rows[0]['oldest'];
			$count['newest'] = $count['num'] ? max($count['newest'], (int) $rows[0]['newest']) : (int) $rows[0]['newest'];
			$count['num'] += $num;
			$count['softdeleted'] += (int) $rows[0]['num_softdeleted'];
		}

		return $count;
	}

	/**
	 * Rule "count", active run (rc1): topic by topic in topic_id order, behind $after, one boundary
	 * query and one small candidate query per topic (no OR list), until $remaining posts are collected.
	 * SQL in a loop (Validation Policy exception, rc1): the loop ends as soon as the batch is full;
	 * it visits each marked topic of the forum at most once per run, two queries each.
	 *
	 * @param array	$forum
	 * @param int	$now
	 * @param int	$remaining	Batch left in this run
	 * @param int	$after		Cursor: continue behind this topic_id; 0: from the first topic
	 * @param int	$last_done	Set to each topic whose candidates all fitted into the batch
	 * @return array|false	[rows (post_id, post_visibility), bool batch full]; false: a query failed
	 */
	protected function topic_rows(array $forum, $now, $remaining, $after, &$last_done)
	{
		$topics = $this->select($this->query_builder->count_topics_query($forum, $after));
		if ($topics === false)
		{
			return false;
		}

		$x = $this->query_builder->keep_count($forum);
		$rows = [];
		foreach ($topics as $topic)
		{
			$topic_id = (int) $topic['topic_id'];
			$bound_rows = $this->select($this->query_builder->boundary_query($forum, $topic), $x);
			if ($bound_rows === false)
			{
				return false;
			}

			$bound = $this->query_builder->boundary($bound_rows, $x);
			if ($bound !== null)
			{
				$left = $remaining - count($rows);
				// One row more than what is left tells whether the topic is finished.
				$candidates = $this->select($this->query_builder->candidates_query($forum, $now, [$topic_id => $bound]), $left + 1);
				if ($candidates === false)
				{
					return false;
				}
				if (count($candidates) > $left)
				{
					return [array_merge($rows, array_slice($candidates, 0, $left)), true];
				}
				$rows = array_merge($rows, $candidates);
			}

			$last_done = $topic_id;
			if (count($rows) >= $remaining)
			{
				return [$rows, true];
			}
		}

		return [$rows, false];
	}

	/**
	 * Rule "count": boundary per topic, i.e. the X-th newest approved post
	 * (query_builder::boundary_query()). Recomputed on every run, so posts
	 * written in the meantime move it. Topics with fewer than X approved
	 * posts get no boundary and lose nothing.
	 *
	 * @param array $forum
	 * @return array|null|false	[topic_id => [post_time, post_id]]; null: rule "age"; false: a query failed
	 */
	protected function bounds(array $forum)
	{
		if ($this->query_builder->rule($forum) !== query_builder::RULE_COUNT)
		{
			return null;
		}

		$topics = $this->select($this->query_builder->count_topics_query($forum));
		if ($topics === false)
		{
			return false;
		}

		$x = $this->query_builder->keep_count($forum);
		$bounds = [];
		foreach ($topics as $topic)
		{
			$rows = $this->select($this->query_builder->boundary_query($forum, $topic), $x);
			if ($rows === false)
			{
				return false;
			}

			$bound = $this->query_builder->boundary($rows, $x);
			if ($bound !== null)
			{
				$bounds[(int) $topic['topic_id']] = $bound;
			}
		}

		return $bounds;
	}

	/**
	 * Log entry of a dry run or a deletion. Scope "whole forum": $prefix;
	 * "marked topics only": $prefix_TOPIC(S), rule "count" $prefix_KEEP_AGE_TOPIC(S)
	 * with X as fourth and the minimum age N (days) as fifth value. With soft-deleted
	 * posts among them: the same key with _SOFT and their number as the last value.
	 * Older keys (_KEEP_TOPIC(S), beta5 without minimum age) stay in the language
	 * files, so existing log entries stay readable.
	 *
	 * @param string	$prefix			LOG_UFLAGMEY_PP_DRYRUN or LOG_UFLAGMEY_PP_PRUNED
	 * @param array		$forum
	 * @param int		$num			Number of posts
	 * @param int|null	$topics			Number of marked topics, null for scope "whole forum"
	 * @param int		$softdeleted	How many of $num are soft-deleted
	 */
	protected function log_result($prefix, array $forum, $num, $topics, $softdeleted = 0)
	{
		if ($topics === null)
		{
			$operation = $prefix;
			$data = [$forum['forum_name'], $num];
		}
		else if ($this->query_builder->rule($forum) === query_builder::RULE_COUNT)
		{
			$operation = $prefix . '_KEEP_AGE' . (($topics === 1) ? '_TOPIC' : '_TOPICS');
			$data = [$forum['forum_name'], $num, $topics, $this->query_builder->keep_count($forum), $this->query_builder->days($forum)];
		}
		else
		{
			$operation = $prefix . (($topics === 1) ? '_TOPIC' : '_TOPICS');
			$data = [$forum['forum_name'], $num, $topics];
		}

		if ($softdeleted > 0)
		{
			$operation .= '_SOFT';
			$data[] = (int) $softdeleted;
		}

		$this->add_log($operation, $data);
	}

	/**
	 * Number of marked topics for a forum with scope "marked topics only",
	 * null for scope "whole forum". Counts all marked topics of the forum,
	 * not only those with candidates (see query_builder::marked_count_query()).
	 *
	 * @param array $forum
	 * @return int|null|false	false: the query failed (see select())
	 */
	protected function marked_topics(array $forum)
	{
		if ($this->query_builder->scope($forum) !== query_builder::SCOPE_MARKED)
		{
			return null;
		}

		$rows = $this->select($this->query_builder->marked_count_query($forum));

		return $rows === false ? false : (int) $rows[0]['num_topics'];
	}

	/**
	 * Runs one SELECT of the cron run without aborting the request on an SQL
	 * error. The only expected error is a database restored from a backup
	 * older than the cached config (stale schema marker, missing column): a
	 * fatal SQL error would leave phpBB's cron_lock behind and block every
	 * cron task of the board for an hour.
	 *
	 * @param array	$query	sql_build_query() array
	 * @param int	$limit	0: no limit
	 * @return array|false	Rows, false on an SQL error
	 */
	protected function select(array $query, $limit = 0)
	{
		$sql = $this->db->sql_build_query('SELECT', $query);
		$this->db->sql_return_on_error(true);
		$result = $limit ? $this->db->sql_query_limit($sql, $limit) : $this->db->sql_query($sql);
		$this->db->sql_return_on_error(false);

		if ($result === false)
		{
			return false;
		}

		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows ?: [];
	}

	/**
	 * Ends the run cleanly after an SQL error: one entry in the error log,
	 * no backlog, so the cron task closes the cycle (last_run) and phpBB
	 * releases its cron_lock. Next attempt in the next cycle.
	 *
	 * @param array $pruned
	 * @param array $dryrun
	 * @return array
	 */
	protected function schema_mismatch(array $pruned, array $dryrun)
	{
		$user_id = isset($this->user->data['user_id']) ? (int) $this->user->data['user_id'] : ANONYMOUS;
		$ip = !empty($this->user->ip) ? $this->user->ip : '127.0.0.1';
		$this->log->add('critical', $user_id, $ip, 'LOG_UFLAGMEY_PP_SCHEMA_MISMATCH', false, []);

		return [
			'backlog'	=> false,
			'pruned'	=> $pruned,
			'dryrun'	=> $dryrun,
		];
	}

	/**
	 * @param array	$rows			post_id + post_visibility of the posts to delete
	 * @param bool	$count_sync		Reduce the authors' post counts
	 * @return int	Number of deleted posts
	 */
	protected function delete(array $rows, $count_sync)
	{
		if (!function_exists('delete_posts'))
		{
			include($this->root_path . 'includes/functions_admin.' . $this->php_ext);
		}

		$post_ids = $approved = [];
		foreach ($rows as $row)
		{
			$post_ids[] = (int) $row['post_id'];
			if ((int) $row['post_visibility'] === ITEM_APPROVED)
			{
				$approved[] = (int) $row['post_id'];
			}
		}

		$deleted = (int) delete_posts('post_id', $post_ids, true, true, $count_sync, true);

		// delete_posts() only lowers the board statistic num_posts together with
		// the user post counts. Without count sync, correct it here, counting
		// only approved posts (determined before the deletion).
		if (!$count_sync && $deleted && count($approved))
		{
			$this->config->increment('num_posts', -count($approved), false);
		}

		return $deleted;
	}

	/**
	 * Log data: forum name first, then the post count (phpBB picks the plural
	 * form by the first integer), then the number of marked topics if any,
	 * then the keep count X for rule "count".
	 *
	 * @param string	$operation
	 * @param array		$data
	 * @param string	$mode		admin or critical (error log)
	 */
	protected function add_log($operation, array $data, $mode = 'admin')
	{
		$user_id = isset($this->user->data['user_id']) ? (int) $this->user->data['user_id'] : ANONYMOUS;
		$ip = !empty($this->user->ip) ? $this->user->ip : '127.0.0.1';

		$this->log->add($mode, $user_id, $ip, $operation, false, $data);
	}
}
