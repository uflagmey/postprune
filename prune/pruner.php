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

	/**
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\config\config				$config
	 * @param \phpbb\log\log_interface			$log
	 * @param \phpbb\user						$user
	 * @param query_builder						$query_builder
	 * @param string							$root_path
	 * @param string							$php_ext
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, \phpbb\log\log_interface $log, \phpbb\user $user, query_builder $query_builder, $root_path, $php_ext)
	{
		$this->db = $db;
		$this->config = $config;
		$this->log = $log;
		$this->user = $user;
		$this->query_builder = $query_builder;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Forums with post pruning switched on (post forums only).
	 *
	 * @return array
	 */
	public function get_forums()
	{
		$sql = $this->db->sql_build_query('SELECT', $this->query_builder->forums_query(schema::topic_scope($this->config), schema::keep_count($this->config)));
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
	 * cycle saying so (dry run and active).
	 *
	 * @param int	$now
	 * @param int	$batch	Maximum number of posts to delete in this run
	 * @return array ['backlog' => bool, 'pruned' => [forum_id => n], 'dryrun' => [forum_id => n]]
	 */
	public function run($now, $batch)
	{
		$remaining = max(1, (int) $batch);
		$backlog = false;
		$pruned = $dryrun = $no_topics = [];
		$forums = $this->select($this->query_builder->forums_query(schema::topic_scope($this->config), schema::keep_count($this->config)));
		if ($forums === false)
		{
			return $this->schema_mismatch($pruned, $dryrun);
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

			$bounds = $this->bounds($forum);
			if ($bounds === false)
			{
				return $this->schema_mismatch($pruned, $dryrun);
			}

			// One row more than the budget tells whether a backlog remains.
			$rows = ($bounds === null || $bounds) ? $this->select($this->query_builder->candidates_query($forum, $now, (array) $bounds), $remaining + 1) : [];
			if ($rows === false)
			{
				return $this->schema_mismatch($pruned, $dryrun);
			}

			if (count($rows) > $remaining)
			{
				$backlog = true;
				$rows = array_slice($rows, 0, $remaining);
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

		if (!$backlog)
		{
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
	 * @return array|null|false	['forum' => settings row used, 'post_forum' => bool, 'topics' => int|null,
	 *							'num' => int, 'softdeleted' => int, 'oldest' => int, 'newest' => int];
	 *							null: no such forum; false: a query failed
	 */
	public function preview($forum_id, $now, array $form = [])
	{
		$rows = $this->select($this->query_builder->forum_query($forum_id, schema::topic_scope($this->config), schema::keep_count($this->config)));
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
		$count = $post_forum ? $this->count($forum, $now) : ['topics' => $this->marked_topics($forum), 'num' => 0, 'softdeleted' => 0, 'oldest' => 0, 'newest' => 0];
		if ($count === false || $count['topics'] === false)
		{
			return false;
		}

		return ['forum' => $forum, 'post_forum' => $post_forum] + $count;
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

		$rows = $this->select($this->query_builder->count_query($forum, $now, (array) $bounds));
		if ($rows === false)
		{
			return false;
		}

		$count['num'] = (int) $rows[0]['num_posts'];
		if ($count['num'])
		{
			$count['softdeleted'] = (int) $rows[0]['num_softdeleted'];
			$count['oldest'] = (int) $rows[0]['oldest'];
			$count['newest'] = (int) $rows[0]['newest'];
		}

		return $count;
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
	 * "marked topics only": $prefix_TOPIC(S), rule "count" $prefix_KEEP_TOPIC(S)
	 * with X as fourth value. With soft-deleted posts among them: the same key
	 * with _SOFT and their number as the last value (older entries stay readable).
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
			$operation = $prefix . '_KEEP' . (($topics === 1) ? '_TOPIC' : '_TOPICS');
			$data = [$forum['forum_name'], $num, $topics, $this->query_builder->keep_count($forum)];
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
	 */
	protected function add_log($operation, array $data)
	{
		$user_id = isset($this->user->data['user_id']) ? (int) $this->user->data['user_id'] : ANONYMOUS;
		$ip = !empty($this->user->ip) ? $this->user->ip : '127.0.0.1';

		$this->log->add('admin', $user_id, $ip, $operation, false, $data);
	}
}
