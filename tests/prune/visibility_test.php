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
 * Visibility (rc1, R-04): posts waiting for approval (unapproved, reapprove) are never deleted, under both rules,
 * and do not count towards the kept X; soft-deleted posts are deleted and named separately (preview, logs _SOFT).
 */
class visibility_test extends \PHPUnit\Framework\TestCase
{
	/** @var \phpbb\db\driver\sqlite3 */
	protected $db;

	/** @var array */
	protected $logs;

	// phpcs:ignore PhpbbCodingStandard.NamingConventions.LowercaseUnderscoredFunctions.NotAllowed -- PHPUnit hook name
	protected function setUp(): void
	{
		$this->db = fixtures::db();
		$this->logs = [];
		fixtures::$delete_calls = [];
	}

	protected function pruner()
	{
		$log = $this->createMock(\phpbb\log\log_interface::class);
		$log->method('add')->willReturnCallback(function ($mode, $user_id, $ip, $operation, $time, $data) {
			$this->logs[] = [$operation, $data];
			return 1;
		});
		$user = $this->createMock(\phpbb\user::class);
		$user->data = ['user_id' => 2];
		$user->ip = '10.0.0.1';

		return new pruner($this->db, new \phpbb\config\config(['num_posts' => 1000, 'uflagmey_pp_schema' => 3]), $log, $user, fixtures::query_builder(), './', 'php');
	}

	protected function run_mode($forum_id, $mode)
	{
		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_mode = ' . (int) $mode . ' WHERE forum_id = ' . (int) $forum_id);
		$this->logs = [];
		$this->pruner()->run(fixtures::NOW, 1000);

		return $this->logs;
	}

	public function test_age_rule_waiting_posts_stay_soft_deleted_go_and_are_named()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_days' => 30]);
		fixtures::topic($this->db, 1, 10, [101 => 200, 102 => 150, 103 => [140, ITEM_UNAPPROVED], 104 => [130, ITEM_REAPPROVE], 105 => [120, ITEM_DELETED], 106 => 5]);

		$preview = $this->pruner()->preview(1, fixtures::NOW);
		$this->assertSame([2, 1], [$preview['num'], $preview['softdeleted']], '102 and 105 (soft-deleted)');
		$this->assertSame([['LOG_UFLAGMEY_PP_DRYRUN_SOFT', ['A', 2, 1]]], $this->run_mode(1, 1));
		$this->assertSame([['LOG_UFLAGMEY_PP_PRUNED_SOFT', ['A', 2, 1]]], $this->run_mode(1, 2));
		$this->assertSame([101, 103, 104, 106], fixtures::post_ids($this->db), 'start post, both posts waiting for approval and the young post stay');
	}

	public function test_count_rule_waiting_posts_stay_and_do_not_count()
	{
		fixtures::forum($this->db, 1, 'K', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 1, 'uflagmey_pp_keep_count' => 2, 'uflagmey_pp_days' => 1]);
		// approved replies 102, 104, 106, 107; waiting 103 (old) and 108 (newest) do not count: keep 106, 107
		fixtures::topic($this->db, 1, 10, [101 => 100, 102 => 90, 103 => [80, ITEM_UNAPPROVED], 104 => 70, 105 => [60, ITEM_DELETED], 106 => 50, 107 => 40, 108 => [30, ITEM_REAPPROVE]], POST_NORMAL, ['uflagmey_pp_marked' => 1]);

		$preview = $this->pruner()->preview(1, fixtures::NOW);
		$this->assertSame([3, 1], [$preview['num'], $preview['softdeleted']], '102, 104 and 105 (soft-deleted)');
		$this->assertSame([['LOG_UFLAGMEY_PP_PRUNED_KEEP_TOPIC_SOFT', ['K', 3, 1, 2, 1]]], $this->run_mode(1, 2));
		$this->assertSame([101, 103, 106, 107, 108], fixtures::post_ids($this->db));
	}

	public function test_without_soft_deleted_posts_the_plain_log_keys_are_used()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_days' => 30]);
		fixtures::topic($this->db, 1, 10, [101 => 200, 102 => 150, 103 => [140, ITEM_UNAPPROVED]]);

		$this->assertSame(0, $this->pruner()->preview(1, fixtures::NOW)['softdeleted']);
		$this->assertSame([['LOG_UFLAGMEY_PP_PRUNED', ['A', 1]]], $this->run_mode(1, 2));
	}
}
