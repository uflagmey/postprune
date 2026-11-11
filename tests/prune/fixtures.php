<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune\tests\prune
{
	/**
	 * In-memory SQLite board (phpBB's real sqlite3 driver) with the columns the extension reads.
	 */
	class fixtures
	{
		const NOW = 1800000000;
		const DAY = 86400;

		/** @var \phpbb\db\driver\driver_interface DB the delete_posts() test double works on */
		public static $db;

		/** @var array Recorded delete_posts() calls */
		public static $delete_calls = [];

		/**
		 * @return \phpbb\db\driver\sqlite3
		 */
		public static function db()
		{
			$db = new \phpbb\db\driver\sqlite3();
			$db->sql_connect(':memory:', '', '', '');
			$db->sql_query('CREATE TABLE phpbb_forums (forum_id INTEGER PRIMARY KEY, forum_name TEXT, forum_type INTEGER,
				uflagmey_pp_mode INTEGER DEFAULT 0, uflagmey_pp_days INTEGER DEFAULT 30, uflagmey_pp_keep_first INTEGER DEFAULT 1,
				uflagmey_pp_skip_sticky INTEGER DEFAULT 1, uflagmey_pp_count_sync INTEGER DEFAULT 0)');
			$db->sql_query('CREATE TABLE phpbb_topics (topic_id INTEGER PRIMARY KEY, forum_id INTEGER, topic_type INTEGER, topic_first_post_id INTEGER)');
			$db->sql_query('CREATE TABLE phpbb_posts (post_id INTEGER PRIMARY KEY, topic_id INTEGER, forum_id INTEGER, post_time INTEGER, post_visibility INTEGER)');

			self::$db = $db;
			self::$delete_calls = [];

			return $db;
		}

		/**
		 * @param \phpbb\db\driver\driver_interface	$db
		 * @param int		$forum_id
		 * @param string	$name
		 * @param int		$type
		 * @param array		$settings	uflagmey_pp_* overrides
		 */
		public static function forum($db, $forum_id, $name, $type = FORUM_POST, array $settings = [])
		{
			$row = array_merge([
				'forum_id'					=> $forum_id,
				'forum_name'				=> $name,
				'forum_type'				=> $type,
				'uflagmey_pp_mode'			=> 2,
				'uflagmey_pp_days'			=> 30,
				'uflagmey_pp_keep_first'	=> 1,
				'uflagmey_pp_skip_sticky'	=> 1,
				'uflagmey_pp_count_sync'	=> 0,
			], $settings);
			$db->sql_query('INSERT INTO phpbb_forums ' . $db->sql_build_array('INSERT', $row));
		}

		/**
		 * Topic with posts of the given ages (days); the first age is the start post.
		 *
		 * @param \phpbb\db\driver\driver_interface	$db
		 * @param int	$forum_id
		 * @param int	$topic_id
		 * @param array	$posts		[post_id => age_days] or [post_id => [age_days, visibility]]
		 * @param int	$topic_type
		 */
		public static function topic($db, $forum_id, $topic_id, array $posts, $topic_type = POST_NORMAL)
		{
			$db->sql_query('INSERT INTO phpbb_topics ' . $db->sql_build_array('INSERT', [
				'topic_id'				=> $topic_id,
				'forum_id'				=> $forum_id,
				'topic_type'			=> $topic_type,
				'topic_first_post_id'	=> (int) key($posts),
			]));

			foreach ($posts as $post_id => $spec)
			{
				list($age, $visibility) = is_array($spec) ? $spec : [$spec, ITEM_APPROVED];
				$db->sql_query('INSERT INTO phpbb_posts ' . $db->sql_build_array('INSERT', [
					'post_id'			=> $post_id,
					'topic_id'			=> $topic_id,
					'forum_id'			=> $forum_id,
					'post_time'			=> (int) round(self::NOW - $age * self::DAY),
					'post_visibility'	=> $visibility,
				]));
			}
		}

		/**
		 * @return \uflagmey\postprune\prune\query_builder
		 */
		public static function query_builder()
		{
			return new \uflagmey\postprune\prune\query_builder('phpbb_posts', 'phpbb_topics', 'phpbb_forums');
		}

		/**
		 * @param \phpbb\db\driver\driver_interface	$db
		 * @return int[]
		 */
		public static function post_ids($db)
		{
			$result = $db->sql_query('SELECT post_id FROM phpbb_posts ORDER BY post_id');
			$ids = array_map('intval', array_column($db->sql_fetchrowset($result), 'post_id'));
			$db->sql_freeresult($result);

			return $ids;
		}
	}
}

namespace
{
	/**
	 * Test double for the core function (includes/functions_admin.php). Records the call and
	 * removes the posts, so the pruner's budget/backlog logic can be checked across runs.
	 */
	function delete_posts($where_type, $where_ids, $auto_sync = true, $posted_sync = true, $post_count_sync = true, $call_delete_topics = true)
	{
		\uflagmey\postprune\tests\prune\fixtures::$delete_calls[] = func_get_args();
		$db = \uflagmey\postprune\tests\prune\fixtures::$db;
		$db->sql_query('DELETE FROM phpbb_posts WHERE ' . $db->sql_in_set($where_type, $where_ids));

		return $db->sql_affectedrows();
	}
}
