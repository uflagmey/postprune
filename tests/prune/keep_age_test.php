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
 * Minimum age under rule "count" (rc1, R-02 / SR-01): a post is deleted only if it is outside the
 * newest X approved posts of its topic AND older than the post age in days. A burst of new replies
 * therefore deletes nothing that is younger than N days. Same rule for cron, dry run and preview.
 */
class keep_age_test extends \PHPUnit\Framework\TestCase
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

	/**
	 * Forum 1 "K": rule count, scope marked, keep_first, mode Off (each test switches it).
	 */
	protected function forum($x, $days, $keep_first = 1)
	{
		fixtures::forum($this->db, 1, 'K', FORUM_POST, [
			'uflagmey_pp_mode'			=> 0,
			'uflagmey_pp_scope'			=> 1,
			'uflagmey_pp_rule'			=> 1,
			'uflagmey_pp_keep_count'	=> $x,
			'uflagmey_pp_days'			=> $days,
			'uflagmey_pp_keep_first'	=> $keep_first,
		]);
	}

	protected function marked_topic($topic_id, array $posts)
	{
		fixtures::topic($this->db, 1, $topic_id, $posts, POST_NORMAL, ['uflagmey_pp_marked' => 1]);
	}

	/**
	 * Preview, dry run (log number), then active run on the same data.
	 *
	 * @return array [preview num, dry-run num, deleted]
	 */
	protected function preview_dry_active()
	{
		$preview = $this->pruner()->preview(1, fixtures::NOW)['num'];
		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_mode = 1');
		$dry = $this->pruner()->run(fixtures::NOW, 1000)['dryrun'][1];
		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_mode = 2');
		$before = count(fixtures::post_ids($this->db));
		$this->pruner()->run(fixtures::NOW, 1000);
		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_mode = 0');

		return [$preview, $dry, $before - count(fixtures::post_ids($this->db))];
	}

	public function test_burst_of_new_replies_deletes_only_what_is_older_than_n_days()
	{
		// V-01 of the security review: start 200 d, 5 replies 50/40/30/20/10 d, X = 5, N = 30;
		// then 5 new replies (spam, age 0): only the replies older than 30 days may go.
		$this->forum(5, 30);
		$this->marked_topic(10, [100 => 200, 101 => 50, 102 => 40, 103 => 30, 104 => 20, 105 => 10,
			106 => 0.004, 107 => 0.003, 108 => 0.002, 109 => 0.001, 110 => 0]);

		$this->assertSame([2, 2, 2], $this->preview_dry_active());
		$this->assertSame([100, 103, 104, 105, 106, 107, 108, 109, 110], fixtures::post_ids($this->db),
			'start post, the replies of 30/20/10 days (not older than N) and the 5 new ones stay');
	}

	public function test_exactly_n_days_stays_one_second_older_goes()
	{
		$this->forum(1, 30, 0);
		$this->marked_topic(10, [100 => 30 + 1 / 86400, 101 => 30, 102 => 1]);

		$this->assertSame([1, 1, 1], $this->preview_dry_active());
		$this->assertSame([101, 102], fixtures::post_ids($this->db), 'post_time = now - N days is not older than N days');
	}

	public function test_x_binds_alone_when_everything_is_older_than_n()
	{
		$this->forum(3, 1);
		$this->marked_topic(10, [100 => 90, 101 => 80, 102 => 70, 103 => 60, 104 => 50, 105 => 40]);

		$this->assertSame([2, 2, 2], $this->preview_dry_active());
		$this->assertSame([100, 103, 104, 105], fixtures::post_ids($this->db));
	}

	public function test_age_binds_alone_when_everything_outside_x_is_younger_than_n()
	{
		$this->forum(3, 100);
		$this->marked_topic(10, [100 => 90, 101 => 80, 102 => 70, 103 => 60, 104 => 50, 105 => 40]);

		$this->assertSame([0, 0, 0], $this->preview_dry_active());
		$this->assertSame([100, 101, 102, 103, 104, 105], fixtures::post_ids($this->db));
	}

	public function test_combined_both_conditions_and_two_topics()
	{
		$this->forum(2, 45);
		// topic 10: outside X = 101..103 (60/50/40 d) -> 101, 102 older than 45 d go, 103 stays
		$this->marked_topic(10, [100 => 90, 101 => 60, 102 => 50, 103 => 40, 104 => 30, 105 => 20]);
		// topic 11: outside X = 201 (100 d) goes; 202 is inside X although older than 45 d
		$this->marked_topic(11, [200 => 120, 201 => 100, 202 => 80, 203 => 10]);

		$this->assertSame([3, 3, 3], $this->preview_dry_active());
		$this->assertSame([100, 103, 104, 105, 200, 202, 203], fixtures::post_ids($this->db));
	}

	public function test_log_keys_name_x_and_n()
	{
		$this->forum(2, 45);
		$this->marked_topic(10, [100 => 90, 101 => 60, 102 => [50, ITEM_DELETED], 103 => 40, 104 => 30, 105 => 20]);
		$this->marked_topic(11, [200 => 9, 201 => 8]);

		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_mode = 1');
		$this->pruner()->run(fixtures::NOW, 1000);
		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_mode = 2');
		$this->pruner()->run(fixtures::NOW, 1000);

		$this->assertSame([
			['LOG_UFLAGMEY_PP_DRYRUN_KEEP_AGE_TOPICS_SOFT', ['K', 2, 2, 2, 45, 1]],
			['LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPICS_SOFT', ['K', 2, 2, 2, 45, 1]],
		], $this->logs);

		$this->logs = [];
		$this->db->sql_query('UPDATE phpbb_topics SET uflagmey_pp_marked = 0 WHERE topic_id = 11');
		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_mode = 1');
		$this->pruner()->run(fixtures::NOW, 1000);
		$this->assertSame([['LOG_UFLAGMEY_PP_DRYRUN_KEEP_AGE_TOPIC', ['K', 0, 1, 2, 45]]], $this->logs);
	}

	public function test_candidates_where_of_rule_count_carries_the_cutoff()
	{
		$qb = fixtures::query_builder();
		$forum = ['forum_id' => 1, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 1, 'uflagmey_pp_keep_count' => 5, 'uflagmey_pp_days' => 30, 'uflagmey_pp_keep_first' => 0];
		$cutoff = $qb->cutoff(30, fixtures::NOW);

		foreach ([$qb->candidates_query($forum, fixtures::NOW, [7 => [100, 9]]), $qb->count_query($forum, fixtures::NOW, [7 => [100, 9]])] as $query)
		{
			$this->assertStringContainsString('p.post_time < ' . $cutoff, $query['WHERE']);
			$this->assertStringNotContainsString('OVER', strtoupper($query['WHERE']), 'no window functions');
		}
	}
}
