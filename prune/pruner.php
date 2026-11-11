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
		$sql = $this->db->sql_build_query('SELECT', $this->query_builder->forums_query(schema::topic_scope($this->config)));
		$result = $this->db->sql_query($sql);
		$forums = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $forums ?: [];
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
		$forums = $this->get_forums();

		foreach ($forums as $forum)
		{
			if ((int) $forum['uflagmey_pp_mode'] !== query_builder::MODE_ACTIVE)
			{
				continue;
			}

			$topics = $this->marked_topics($forum);
			if ($topics === 0)
			{
				$no_topics[] = $forum;
				continue;
			}

			// One row more than the budget tells whether a backlog remains.
			$sql = $this->db->sql_build_query('SELECT', $this->query_builder->candidates_query($forum, $now));
			$result = $this->db->sql_query_limit($sql, $remaining + 1);
			$rows = $this->db->sql_fetchrowset($result);
			$this->db->sql_freeresult($result);

			if (count($rows) > $remaining)
			{
				$backlog = true;
				$rows = array_slice($rows, 0, $remaining);
			}

			if (!count($rows))
			{
				continue;
			}

			$deleted = $this->delete($rows, (bool) $forum['uflagmey_pp_count_sync']);
			$remaining -= count($rows);
			$pruned[(int) $forum['forum_id']] = $deleted;

			if ($deleted)
			{
				if ($topics === null)
				{
					$this->add_log('LOG_UFLAGMEY_PP_PRUNED', [$forum['forum_name'], $deleted]);
				}
				else
				{
					$this->add_log($topics === 1 ? 'LOG_UFLAGMEY_PP_PRUNED_TOPIC' : 'LOG_UFLAGMEY_PP_PRUNED_TOPICS', [$forum['forum_name'], $deleted, $topics]);
				}
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

				$topics = $this->marked_topics($forum);
				if ($topics === 0)
				{
					$dryrun[(int) $forum['forum_id']] = 0;
					$this->add_log('LOG_UFLAGMEY_PP_DRYRUN_NO_TOPICS', [$forum['forum_name']]);
					continue;
				}

				$sql = $this->db->sql_build_query('SELECT', $this->query_builder->count_query($forum, $now));
				$result = $this->db->sql_query($sql);
				$num = (int) $this->db->sql_fetchfield('num_posts');
				$this->db->sql_freeresult($result);

				$dryrun[(int) $forum['forum_id']] = $num;
				if ($topics === null)
				{
					$this->add_log('LOG_UFLAGMEY_PP_DRYRUN', [$forum['forum_name'], $num]);
				}
				else
				{
					$this->add_log($topics === 1 ? 'LOG_UFLAGMEY_PP_DRYRUN_TOPIC' : 'LOG_UFLAGMEY_PP_DRYRUN_TOPICS', [$forum['forum_name'], $num, $topics]);
				}
			}
		}

		return [
			'backlog'	=> $backlog,
			'pruned'	=> $pruned,
			'dryrun'	=> $dryrun,
		];
	}

	/**
	 * Number of marked topics for a forum with scope "marked topics only",
	 * null for scope "whole forum".
	 *
	 * @param array $forum
	 * @return int|null
	 */
	protected function marked_topics(array $forum)
	{
		if ($this->query_builder->scope($forum) !== query_builder::SCOPE_MARKED)
		{
			return null;
		}

		$sql = $this->db->sql_build_query('SELECT', $this->query_builder->marked_count_query($forum));
		$result = $this->db->sql_query($sql);
		$num = (int) $this->db->sql_fetchfield('num_topics');
		$this->db->sql_freeresult($result);

		return $num;
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
	 * form by the first integer), then the number of marked topics if any.
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
