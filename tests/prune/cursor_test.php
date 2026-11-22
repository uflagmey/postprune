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
 * Rule "count" topic by topic (rc1, R-05 / SR-06): the active run works through the marked topics in
 * topic_id order until the batch is full, remembers the last finished topic (config uflagmey_pp_cursor)
 * and the next run continues behind it; at the end of the cycle the cursor is 0. Dry run and preview
 * count all topics in chunks and never touch the cursor.
 */
class cursor_test extends \PHPUnit\Framework\TestCase
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
		$this->config = new \phpbb\config\config(['num_posts' => 1000, 'uflagmey_pp_schema' => 3, 'uflagmey_pp_cursor' => 0]);
		$this->logs = [];
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

		return new pruner($this->db, $this->config, $log, $user, fixtures::query_builder(), './', 'php', new \phpbb\db\tools\tools($this->db));
	}

	protected function forum($fid, $mode = 2, $x = 2)
	{
		fixtures::forum($this->db, $fid, 'K' . $fid, FORUM_POST, ['uflagmey_pp_mode' => $mode, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 1,
			'uflagmey_pp_keep_count' => $x, 'uflagmey_pp_days' => 1]);
	}

	/**
	 * Marked topic, start post + 5 replies (100..50 days), post ids topic_id * 10 + 0..5: with X 2 the replies 1-3 go.
	 */
	protected function topic($fid, $tid)
	{
		fixtures::topic($this->db, $fid, $tid, [$tid * 10 => 100, $tid * 10 + 1 => 90, $tid * 10 + 2 => 80, $tid * 10 + 3 => 70, $tid * 10 + 4 => 60, $tid * 10 + 5 => 50],
			POST_NORMAL, ['uflagmey_pp_marked' => 1]);
	}

	protected function gone(array $before)
	{
		return array_values(array_diff($before, fixtures::post_ids($this->db)));
	}

	public function test_topic_by_topic_with_cursor_and_end_of_cycle()
	{
		$this->forum(1);
		foreach ([10, 11, 12] as $tid)
		{
			$this->topic(1, $tid);
		}
		$pruner = $this->pruner();

		$before = fixtures::post_ids($this->db);
		$this->assertTrue($pruner->run(fixtures::NOW, 4)['backlog']);
		$this->assertSame([101, 102, 103, 111], $this->gone($before), 'topic 10 complete, then the oldest of topic 11');
		$this->assertSame(10, (int) $this->config['uflagmey_pp_cursor'], 'last finished topic');

		$before = fixtures::post_ids($this->db);
		$this->assertTrue($pruner->run(fixtures::NOW, 4)['backlog']);
		$this->assertSame([112, 113, 121, 122], $this->gone($before), 'continues in topic 11');
		$this->assertSame(11, (int) $this->config['uflagmey_pp_cursor']);

		$before = fixtures::post_ids($this->db);
		$this->assertFalse($pruner->run(fixtures::NOW, 4)['backlog'], 'cycle complete');
		$this->assertSame([123], $this->gone($before));
		$this->assertSame(0, (int) $this->config['uflagmey_pp_cursor']);
		$this->assertSame(['LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPICS', ['K1', 4, 3, 2, 1]], $this->logs[0]);
	}

	public function test_batch_full_exactly_at_the_end_of_a_topic()
	{
		$this->forum(1);
		$this->topic(1, 10);
		$this->topic(1, 11);
		$pruner = $this->pruner();

		$this->assertTrue($pruner->run(fixtures::NOW, 3)['backlog'], 'cannot know yet whether more follows');
		$this->assertSame(10, (int) $this->config['uflagmey_pp_cursor']);
		$this->assertTrue($pruner->run(fixtures::NOW, 3)['backlog']);
		$this->assertSame(11, (int) $this->config['uflagmey_pp_cursor']);
		$this->assertFalse($pruner->run(fixtures::NOW, 3)['backlog'], 'nothing behind topic 11: cycle complete');
		$this->assertSame(0, (int) $this->config['uflagmey_pp_cursor']);
		$this->assertCount(6, fixtures::post_ids($this->db));
	}

	public function test_count_forums_before_the_cursor_wait_for_the_next_cycle()
	{
		$this->forum(1);
		$this->topic(1, 10);
		$this->forum(2);
		$this->topic(2, 20);
		$this->topic(2, 21);
		$pruner = $this->pruner();

		$pruner->run(fixtures::NOW, 4);   // topic 10 (3) + 1 of topic 20
		$this->assertSame(10, (int) $this->config['uflagmey_pp_cursor']);
		$pruner->run(fixtures::NOW, 4);   // 2 of topic 20 + 2 of topic 21
		$this->assertSame(20, (int) $this->config['uflagmey_pp_cursor']);

		// topic 10 gets a newer reply: its reply 104 is outside the newest 2 now, but forum 1 is done in this cycle
		fixtures::topic($this->db, 1, 13, [130 => 100]);
		$this->db->sql_query('UPDATE phpbb_posts SET topic_id = 10, post_time = ' . (fixtures::NOW - 40 * fixtures::DAY) . ' WHERE post_id = 130');
		$this->assertFalse($pruner->run(fixtures::NOW, 4)['backlog'], 'last post of topic 21, cycle complete');
		$this->assertContains(104, fixtures::post_ids($this->db), 'behind the cursor: next cycle');
		$this->assertSame(0, (int) $this->config['uflagmey_pp_cursor']);

		$pruner->run(fixtures::NOW, 4);
		$this->assertNotContains(104, fixtures::post_ids($this->db));
	}

	public function test_cursor_topic_gone_starts_the_cycle_over()
	{
		$this->forum(1);
		$this->topic(1, 10);
		$this->topic(1, 11);
		$this->config['uflagmey_pp_cursor'] = 99;

		$this->pruner()->run(fixtures::NOW, 100);
		$this->assertCount(6, fixtures::post_ids($this->db), 'both topics pruned from the start');
		$this->assertSame(0, (int) $this->config['uflagmey_pp_cursor']);
	}

	public function test_dry_run_and_preview_count_everything_and_never_touch_the_cursor()
	{
		$this->forum(1, 1);
		for ($tid = 100; $tid < 350; $tid++)
		{
			$this->topic(1, $tid);
		}
		$this->config['uflagmey_pp_cursor'] = 123;

		$preview = $this->pruner()->preview(1, fixtures::NOW);
		$this->assertSame(750, $preview['num'], '250 topics x 3, counted in chunks of ' . pruner::COUNT_CHUNK);
		$this->assertSame(fixtures::NOW - 90 * fixtures::DAY, $preview['oldest']);
		$this->assertSame(fixtures::NOW - 70 * fixtures::DAY, $preview['newest']);
		$this->assertSame(123, (int) $this->config['uflagmey_pp_cursor'], 'the preview does not touch the cursor');
		$this->assertSame([1 => 750], $this->pruner()->run(fixtures::NOW, 10)['dryrun']);
		$this->assertSame(0, (int) $this->config['uflagmey_pp_cursor'], 'a run without backlog ends the cycle');
	}

	public function test_dry_run_forum_beside_an_active_backlog_keeps_the_cursor_moving_only_by_the_active_forum()
	{
		$this->forum(1, 1);
		$this->topic(1, 10);
		$this->forum(2);
		$this->topic(2, 20);
		$this->topic(2, 21);
		$pruner = $this->pruner();

		$result = $pruner->run(fixtures::NOW, 4);
		$this->assertTrue($result['backlog']);
		$this->assertSame([], $result['dryrun'], 'dry-run forums are counted when the cycle closes');
		$this->assertSame(20, (int) $this->config['uflagmey_pp_cursor'], 'topic 20 finished in the active forum; topic 10 (dry run) does not count');
		$result = $pruner->run(fixtures::NOW, 4);
		$this->assertFalse($result['backlog']);
		$this->assertSame([1 => 3], $result['dryrun']);
		$this->assertSame(0, (int) $this->config['uflagmey_pp_cursor']);
	}

	public function test_no_cursor_config_value_yet()
	{
		// update incomplete (m4_cursor not run): no notice, the value is created when it is first needed
		$this->config = new \phpbb\config\config(['num_posts' => 1000, 'uflagmey_pp_schema' => 3]);
		$this->forum(1);
		$this->topic(1, 10);
		$this->topic(1, 11);

		$this->assertTrue($this->pruner()->run(fixtures::NOW, 3)['backlog']);
		$this->assertSame(10, (int) $this->config['uflagmey_pp_cursor']);
	}
}
