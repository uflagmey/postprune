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
 * Candidate selection across forums: batch budget, backlog, dry run, num_posts correction,
 * delete_posts() arguments. Real phpBB sqlite3 driver; delete_posts() is a recording test double.
 */
class pruner_test extends \PHPUnit\Framework\TestCase
{
	/** @var \phpbb\db\driver\sqlite3 */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var array */
	protected $logs;

	// phpcs:ignore PhpbbCodingStandard.NamingConventions.LowercaseUnderscoredFunctions.NotAllowed -- PHPUnit hook name
	protected function setUp(): void
	{
		$this->db = fixtures::db();
		$this->config = new \phpbb\config\config(['num_posts' => 1000]);
		$this->logs = [];
	}

	protected function pruner($schema = 0)
	{
		if ($schema)
		{
			$this->config['uflagmey_pp_schema'] = $schema;
		}

		$log = $this->createMock(\phpbb\log\log_interface::class);
		$log->method('add')->willReturnCallback(function ($mode, $user_id, $ip, $operation, $time, $data) {
			$this->logs[] = [$mode, $user_id, $operation, $data];
			return 1;
		});
		$user = $this->createMock(\phpbb\user::class);
		$user->data = ['user_id' => 2];
		$user->ip = '10.0.0.1';

		return new pruner($this->db, $this->config, $log, $user, fixtures::query_builder(), './', 'php');
	}

	/**
	 * Forum A: 5 candidates, B: 3, F: 12 (keep_first=1, each topic start post is young enough to stay anyway).
	 */
	protected function seed(array $modes = [1 => 2, 2 => 2, 3 => 2], array $count_sync = [])
	{
		$sizes = [1 => 5, 2 => 3, 3 => 12];
		foreach ($sizes as $fid => $n)
		{
			fixtures::forum($this->db, $fid, 'F' . $fid, FORUM_POST, [
				'uflagmey_pp_mode'			=> $modes[$fid] ?? 0,
				'uflagmey_pp_count_sync'	=> $count_sync[$fid] ?? 0,
			]);
			$posts = [$fid * 100 => 1];
			for ($i = 1; $i <= $n; $i++)
			{
				$posts[$fid * 100 + $i] = 90 - $i;
			}
			fixtures::topic($this->db, $fid, $fid * 10, $posts);
		}
	}

	protected function deleted_per_call()
	{
		return array_map(function ($call) {
			return count($call[1]);
		}, fixtures::$delete_calls);
	}

	public function test_batch_budget_spans_forums_and_reports_backlog()
	{
		$this->seed();
		$result = $this->pruner()->run(fixtures::NOW, 6);

		$this->assertTrue($result['backlog']);
		$this->assertSame([1 => 5, 2 => 1], $result['pruned']);
		$this->assertSame([5, 1], $this->deleted_per_call());
		$this->assertSame([], $result['dryrun'], 'no dry run while a backlog is left');
	}

	public function test_backlog_is_worked_off_over_runs_oldest_first()
	{
		$this->seed();
		$pruner = $this->pruner();
		$runs = [];
		do
		{
			fixtures::$delete_calls = [];
			$result = $pruner->run(fixtures::NOW, 6);
			$runs[] = array_sum($this->deleted_per_call());
		}
		while ($result['backlog'] && count($runs) < 10);

		$this->assertSame([6, 6, 6, 2], $runs);
		$this->assertSame([100, 200, 300], fixtures::post_ids($this->db), 'only start posts remain');
	}

	public function test_exact_budget_is_no_backlog()
	{
		$this->seed([1 => 2]);
		$result = $this->pruner()->run(fixtures::NOW, 5);

		$this->assertFalse($result['backlog']);
		$this->assertSame([1 => 5], $result['pruned']);
	}

	public function test_oldest_posts_go_first_within_a_forum()
	{
		$this->seed([3 => 2]);
		$this->pruner()->run(fixtures::NOW, 4);

		$this->assertSame([301, 302, 303, 304], fixtures::$delete_calls[0][1]);
	}

	public function test_delete_posts_arguments()
	{
		$this->seed([1 => 2, 2 => 2], [2 => 1]);
		$this->pruner()->run(fixtures::NOW, 100);

		$calls = fixtures::$delete_calls;
		$this->assertSame(['post_id', [101, 102, 103, 104, 105], true, true, false, true], $calls[0]);
		$this->assertSame(['post_id', [201, 202, 203], true, true, true, true], $calls[1]);
	}

	public function test_dry_run_deletes_nothing_counts_all_and_uses_no_budget()
	{
		$this->seed([1 => 2, 2 => 1, 3 => 1]);
		$result = $this->pruner()->run(fixtures::NOW, 5);

		$this->assertFalse($result['backlog'], 'dry-run candidates are no backlog');
		$this->assertSame([1 => 5], $result['pruned'], 'full budget for the active forum');
		$this->assertSame([2 => 3, 3 => 12], $result['dryrun'], 'full count, not capped at the batch');
		$this->assertSame([5], $this->deleted_per_call());
		$this->assertCount(23 - 5, fixtures::post_ids($this->db));
	}

	public function test_dry_run_logged_only_in_cycle_closing_run()
	{
		$this->seed([1 => 1, 2 => 2, 3 => 2]);
		$pruner = $this->pruner();
		$dry_per_run = [];
		do
		{
			$before = count($this->logs);
			$result = $pruner->run(fixtures::NOW, 6);
			$dry_per_run[] = count(array_filter(array_slice($this->logs, $before), function ($l) {
				return $l[2] === 'LOG_UFLAGMEY_PP_DRYRUN';
			}));
		}
		while ($result['backlog'] && count($dry_per_run) < 10);

		$this->assertSame([0, 0, 1], $dry_per_run);
		$last = end($this->logs);
		$this->assertSame(['admin', 2, 'LOG_UFLAGMEY_PP_DRYRUN', ['F1', 5]], $last);
	}

	public function test_dry_run_logs_zero_candidates()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 1]);
		fixtures::topic($this->db, 1, 10, [100 => 90, 101 => 5]);
		$result = $this->pruner()->run(fixtures::NOW, 6);

		$this->assertFalse($result['backlog']);
		$this->assertSame([1 => 0], $result['dryrun']);
		$this->assertSame([['admin', 2, 'LOG_UFLAGMEY_PP_DRYRUN', ['A', 0]]], $this->logs, 'entry even with 0 candidates');
		$this->assertSame([], fixtures::$delete_calls);
	}

	public function test_zero_candidate_dry_run_still_waits_for_closing_run()
	{
		$this->seed([1 => 2, 2 => 2, 3 => 2]);
		fixtures::forum($this->db, 4, 'D0', FORUM_POST, ['uflagmey_pp_mode' => 1]);
		fixtures::topic($this->db, 4, 40, [400 => 5]);
		$pruner = $this->pruner();
		$dry_per_run = [];
		do
		{
			$before = count($this->logs);
			$result = $pruner->run(fixtures::NOW, 6);
			$dry = array_values(array_filter(array_slice($this->logs, $before), function ($l) {
				return $l[2] === 'LOG_UFLAGMEY_PP_DRYRUN';
			}));
			$dry_per_run[] = array_map(function ($l) {
				return $l[3];
			}, $dry);
		}
		while ($result['backlog'] && count($dry_per_run) < 10);

		$this->assertSame([[], [], [], [['D0', 0]]], $dry_per_run);
	}

	public function test_pruned_log_entry()
	{
		$this->seed([2 => 2]);
		$this->pruner()->run(fixtures::NOW, 100);

		$this->assertSame([['admin', 2, 'LOG_UFLAGMEY_PP_PRUNED', ['F2', 3]]], $this->logs);
	}

	public function test_num_posts_reduced_by_approved_posts_only_without_count_sync()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 2, 'uflagmey_pp_count_sync' => 0]);
		fixtures::topic($this->db, 1, 10, [100 => 1, 101 => 80, 102 => [70, ITEM_UNAPPROVED], 103 => [60, ITEM_DELETED], 104 => 50]);
		$this->pruner()->run(fixtures::NOW, 100);

		$this->assertSame([101, 102, 103, 104], fixtures::$delete_calls[0][1]);
		$this->assertSame(998, (int) $this->config['num_posts'], '2 approved of 4 deleted');
	}

	public function test_num_posts_left_to_core_with_count_sync()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 2, 'uflagmey_pp_count_sync' => 1]);
		fixtures::topic($this->db, 1, 10, [100 => 1, 101 => 80, 102 => 70]);
		$this->pruner()->run(fixtures::NOW, 100);

		$this->assertSame(1000, (int) $this->config['num_posts'], 'delete_posts() itself decrements with count sync');
	}

	public function test_nothing_to_do()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 2]);
		fixtures::topic($this->db, 1, 10, [100 => 90, 101 => 5]);
		$result = $this->pruner()->run(fixtures::NOW, 6);

		$this->assertSame(['backlog' => false, 'pruned' => [], 'dryrun' => []], $result);
		$this->assertSame([], fixtures::$delete_calls);
		$this->assertSame([], $this->logs);
	}

	/**
	 * Forum 1 scope "marked topics only": 10 marked normal, 11 marked sticky (skip_sticky=1), 12 unmarked.
	 */
	protected function seed_scoped(array $forum = [], array $marked = [10, 11])
	{
		fixtures::forum($this->db, 1, 'S', FORUM_POST, array_merge(['uflagmey_pp_mode' => 2, 'uflagmey_pp_scope' => 1], $forum));
		fixtures::topic($this->db, 1, 10, [100 => 90, 101 => 80, 102 => 70]);
		fixtures::topic($this->db, 1, 11, [110 => 90, 111 => 80], POST_STICKY);
		fixtures::topic($this->db, 1, 12, [120 => 90, 121 => 80, 122 => 70]);
		if ($marked)
		{
			$this->db->sql_query('UPDATE phpbb_topics SET uflagmey_pp_marked = 1 WHERE ' . $this->db->sql_in_set('topic_id', $marked));
		}
	}

	public function test_scope_marked_deletes_only_marked_topics_and_names_them()
	{
		$this->seed_scoped();
		$result = $this->pruner(2)->run(fixtures::NOW, 100);

		$this->assertSame([101, 111, 102], fixtures::$delete_calls[0][1], 'marked sticky included, start posts kept, unmarked 12 untouched');
		$this->assertSame([1 => 3], $result['pruned']);
		$this->assertSame([['admin', 2, 'LOG_UFLAGMEY_PP_PRUNED_TOPICS', ['S', 3, 2]]], $this->logs);
	}

	public function test_scope_marked_single_topic_log_key()
	{
		$this->seed_scoped([], [10]);
		$this->pruner(2)->run(fixtures::NOW, 100);
		$this->assertSame([['admin', 2, 'LOG_UFLAGMEY_PP_PRUNED_TOPIC', ['S', 2, 1]]], $this->logs);
	}

	public function test_scope_marked_without_schema_marker_behaves_like_beta2()
	{
		$this->seed_scoped();
		$this->pruner()->run(fixtures::NOW, 100);

		$this->assertSame([101, 121, 102, 122], fixtures::$delete_calls[0][1], 'whole forum minus sticky, marks ignored');
		$this->assertSame([['admin', 2, 'LOG_UFLAGMEY_PP_PRUNED', ['S', 4]]], $this->logs);
	}

	public function test_active_without_marked_topics_logs_once_per_cycle()
	{
		// forum 5 (whole forum, 10 candidates) keeps a backlog over two runs with batch 6
		$this->seed_scoped([], []);
		fixtures::forum($this->db, 5, 'W', FORUM_POST, ['uflagmey_pp_mode' => 2]);
		$posts = [500 => 1];
		for ($i = 1; $i <= 10; $i++)
		{
			$posts[500 + $i] = 90 - $i;
		}
		fixtures::topic($this->db, 5, 50, $posts);
		$pruner = $this->pruner(2);
		$no_topics = [];
		do
		{
			$before = count($this->logs);
			$result = $pruner->run(fixtures::NOW, 6);
			$no_topics[] = count(array_filter(array_slice($this->logs, $before), function ($l) {
				return $l[2] === 'LOG_UFLAGMEY_PP_PRUNED_NO_TOPICS';
			}));
		}
		while ($result['backlog'] && count($no_topics) < 10);

		$this->assertSame([0, 1], $no_topics, 'only in the cycle-closing run');
		$this->assertNotContains(120, array_merge(...array_column(fixtures::$delete_calls, 1)), 'unmarked topic untouched');
		$this->assertContains(['admin', 2, 'LOG_UFLAGMEY_PP_PRUNED_NO_TOPICS', ['S']], $this->logs);
	}

	public function test_dry_run_scope_marked_texts()
	{
		$this->seed_scoped(['uflagmey_pp_mode' => 1]);
		fixtures::forum($this->db, 2, 'N', FORUM_POST, ['uflagmey_pp_mode' => 1, 'uflagmey_pp_scope' => 1]);
		fixtures::topic($this->db, 2, 20, [200 => 90, 201 => 80]);
		fixtures::forum($this->db, 3, 'Z', FORUM_POST, ['uflagmey_pp_mode' => 1, 'uflagmey_pp_scope' => 1]);
		fixtures::topic($this->db, 3, 30, [300 => 90, 301 => 5], POST_NORMAL, ['uflagmey_pp_marked' => 1]);
		fixtures::topic($this->db, 3, 31, [310 => 90], POST_NORMAL, ['uflagmey_pp_marked' => 1]);
		$result = $this->pruner(2)->run(fixtures::NOW, 100);

		$this->assertSame([], fixtures::$delete_calls);
		$this->assertSame([1 => 3, 2 => 0, 3 => 0], $result['dryrun']);
		$this->assertSame([
			['admin', 2, 'LOG_UFLAGMEY_PP_DRYRUN_TOPICS', ['S', 3, 2]],
			['admin', 2, 'LOG_UFLAGMEY_PP_DRYRUN_NO_TOPICS', ['N']],
			['admin', 2, 'LOG_UFLAGMEY_PP_DRYRUN_TOPICS', ['Z', 0, 2]],
		], $this->logs);
	}

	public function test_marked_topic_in_forum_with_mode_off_untouched()
	{
		$this->seed_scoped(['uflagmey_pp_mode' => 0]);
		$result = $this->pruner(2)->run(fixtures::NOW, 100);
		$this->assertSame(['backlog' => false, 'pruned' => [], 'dryrun' => []], $result);
		$this->assertSame([], fixtures::$delete_calls);
		$this->assertSame([], $this->logs);
	}
}
