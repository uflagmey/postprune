<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune\tests\prune;

use uflagmey\postprune\prune\pruner;

/**
 * "Keep first post" (rc1, R-01): the protected post is the EARLIEST post of the topic by (post_time, post_id),
 * whatever its visibility and whatever topics.topic_first_post_id says. Same for counting, deleting and the
 * keep-count boundary.
 */
class first_post_test extends \PHPUnit\Framework\TestCase
{
	/** @var \phpbb\db\driver\sqlite3 */
	protected $db;

	// phpcs:ignore PhpbbCodingStandard.NamingConventions.LowercaseUnderscoredFunctions.NotAllowed -- PHPUnit hook name
	protected function setUp(): void
	{
		$this->db = fixtures::db();
		fixtures::$delete_calls = [];
	}

	protected function pruner()
	{
		$log = $this->createMock(\phpbb\log\log_interface::class);
		$user = $this->createMock(\phpbb\user::class);
		$user->data = ['user_id' => 2];
		$user->ip = '10.0.0.1';

		return new pruner($this->db, new \phpbb\config\config(['num_posts' => 1000, 'uflagmey_pp_schema' => 3]), $log, $user, fixtures::query_builder(), './', 'php');
	}

	/**
	 * Preview count, then an active run; returns [count, posts left].
	 */
	protected function count_and_run($forum_id)
	{
		$count = $this->pruner()->preview($forum_id, fixtures::NOW)['num'];
		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_mode = 2 WHERE forum_id = ' . (int) $forum_id);
		$this->pruner()->run(fixtures::NOW, 1000);

		return [$count, fixtures::post_ids($this->db)];
	}

	public function test_soft_deleted_start_post_stays_protected()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_days' => 30]);
		fixtures::topic($this->db, 1, 10, [101 => [100, ITEM_DELETED], 102 => 90, 103 => 80, 104 => 70]);
		// as phpBB's sync after soft-deleting the start post alone: the first APPROVED post becomes topic_first_post_id
		$this->db->sql_query('UPDATE phpbb_topics SET topic_first_post_id = 102 WHERE topic_id = 10');

		$this->assertSame([3, [101]], $this->count_and_run(1), 'the real start post 101 stays, the old replies 102-104 go');
	}

	public function test_unapproved_start_post_stays_protected()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_days' => 30]);
		fixtures::topic($this->db, 1, 10, [101 => [100, ITEM_UNAPPROVED], 102 => 90, 103 => 5]);
		$this->db->sql_query('UPDATE phpbb_topics SET topic_first_post_id = 102 WHERE topic_id = 10');

		$this->assertSame([1, [101, 103]], $this->count_and_run(1));
	}

	public function test_equal_post_time_exactly_one_post_is_protected()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_days' => 30]);
		// 201 and 202 have the same post_time; the lower post_id is the start post
		fixtures::topic($this->db, 1, 20, [202 => 100, 201 => 100, 203 => 90]);

		$this->assertSame([2, [201]], $this->count_and_run(1), 'only 201 (same time, lower id) is protected');
	}

	public function test_older_merged_post_becomes_the_protected_one()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_days' => 30]);
		fixtures::topic($this->db, 1, 10, [101 => 100, 102 => 90]);
		// a post merged in from another topic, older than the start post
		$this->db->sql_query('INSERT INTO phpbb_posts (post_id, topic_id, forum_id, post_time, post_visibility) VALUES (150, 10, 1, '
			. (int) (fixtures::NOW - 300 * fixtures::DAY) . ', ' . ITEM_APPROVED . ')');

		$this->assertSame([2, [150]], $this->count_and_run(1), 'the earliest post (150) is protected, the former start post goes');
	}

	public function test_keep_count_boundary_leaves_out_the_earliest_post()
	{
		fixtures::forum($this->db, 1, 'K', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 1, 'uflagmey_pp_keep_count' => 2, 'uflagmey_pp_days' => 1]);
		// start post soft-deleted; approved replies 102-105. Keep the newest 2 approved besides the start post: 104, 105
		fixtures::topic($this->db, 1, 10, [101 => [100, ITEM_DELETED], 102 => 90, 103 => 80, 104 => 70, 105 => 60], POST_NORMAL, ['uflagmey_pp_marked' => 1]);
		$this->db->sql_query('UPDATE phpbb_topics SET topic_first_post_id = 102 WHERE topic_id = 10');

		$this->assertSame([2, [101, 104, 105]], $this->count_and_run(1));
	}

	public function test_without_keep_first_the_earliest_post_counts_and_goes()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_days' => 30, 'uflagmey_pp_keep_first' => 0]);
		fixtures::topic($this->db, 1, 10, [101 => [100, ITEM_DELETED], 102 => 90, 103 => 5]);

		$this->assertSame([2, [103]], $this->count_and_run(1));
	}
}
