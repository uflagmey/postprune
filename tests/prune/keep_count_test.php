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
use uflagmey\postprune\prune\query_builder;

/**
 * Deletion rule "count" (beta5): per marked topic keep the newest X approved posts
 * (post_time, then post_id), delete everything ordered before the X-th newest one,
 * whatever its visibility. With keep_first the start post neither counts nor goes.
 * Since rc1 also older than the post age (minimum age, see keep_age_test).
 */
class keep_count_test extends \PHPUnit\Framework\TestCase
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
		$this->config = new \phpbb\config\config(['num_posts' => 1000, 'uflagmey_pp_schema' => 3]);
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

		return new pruner($this->db, $this->config, $log, $user, fixtures::query_builder(), './', 'php');
	}

	/**
	 * Forum 1 "K": rule count, scope marked, X, keep_first. Post age (minimum age, rc1) 1 day:
	 * every post of topic() but the newest is older, so only X binds here (see keep_age_test).
	 */
	protected function forum($x = 100, $keep_first = 1, $mode = 2, array $extra = [])
	{
		fixtures::forum($this->db, 1, 'K', FORUM_POST, array_merge([
			'uflagmey_pp_mode'			=> $mode,
			'uflagmey_pp_scope'			=> 1,
			'uflagmey_pp_rule'			=> 1,
			'uflagmey_pp_keep_count'	=> $x,
			'uflagmey_pp_keep_first'	=> $keep_first,
			'uflagmey_pp_days'			=> 1,
		], $extra));
	}

	/**
	 * Marked topic with $n posts, post ids $topic_id * 1000 + 1..n, one minute apart (oldest first).
	 *
	 * @param callable|null $visibility	fn(int $i) => visibility of the i-th post (1-based)
	 */
	protected function topic($topic_id, $n, $visibility = null, $marked = 1)
	{
		$posts = [];
		for ($i = 1; $i <= $n; $i++)
		{
			$posts[$topic_id * 1000 + $i] = [($n - $i) / 1440 + 1, $visibility ? $visibility($i) : ITEM_APPROVED];
		}
		fixtures::topic($this->db, 1, $topic_id, $posts, POST_NORMAL, ['uflagmey_pp_marked' => $marked]);
	}

	protected function ids($topic_id)
	{
		$result = $this->db->sql_query('SELECT post_id FROM phpbb_posts WHERE topic_id = ' . (int) $topic_id . ' ORDER BY post_id');
		$ids = array_map('intval', array_column($this->db->sql_fetchrowset($result), 'post_id'));
		$this->db->sql_freeresult($result);

		return $ids;
	}

	protected function cycle($batch = 1000)
	{
		$pruner = $this->pruner();
		$runs = 0;
		do
		{
			$result = $pruner->run(fixtures::NOW, $batch);
			$runs++;
		}
		while ($result['backlog'] && $runs < 50);

		return $runs;
	}

	public function test_150_posts_keep_100_plus_start_post_80_unchanged()
	{
		$this->forum();
		$this->topic(10, 150);
		$this->topic(11, 80);
		$this->cycle();

		$this->assertSame(array_merge([10001], range(10051, 10150)), $this->ids(10));
		$this->assertSame(range(11001, 11080), $this->ids(11));
		$this->assertSame([['LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPICS', ['K', 49, 2, 100, 1]]], $this->logs);
	}

	public function test_boundary_exactly_x_and_x_plus_one()
	{
		$this->forum(100, 1);
		$this->topic(10, 100);
		$this->topic(11, 101);
		$this->cycle();

		$this->assertSame(range(10001, 10100), $this->ids(10), 'exactly X');
		$this->assertSame(range(11001, 11101), $this->ids(11), 'X + start post');
	}

	public function test_without_keep_first_the_start_post_counts_and_goes()
	{
		$this->forum(100, 0);
		$this->topic(10, 101);
		$this->cycle();

		$this->assertSame(range(10002, 10101), $this->ids(10));
	}

	public function test_status_mix_unapproved_and_deleted_do_not_count_soft_deleted_go_when_older()
	{
		// every 3rd post unapproved, every 5th (not 3rd) soft-deleted
		$vis = function ($i) {
			return $i % 3 === 0 ? ITEM_UNAPPROVED : ($i % 5 === 0 ? ITEM_DELETED : ITEM_APPROVED);
		};
		$this->forum(10, 1);
		$this->topic(10, 60, $vis);
		$this->cycle();

		$left = $this->ids(10);
		$approved = array_values(array_filter(range(2, 60), function ($i) use ($vis) {
			return $vis($i) === ITEM_APPROVED;
		}));
		$boundary = 10000 + $approved[count($approved) - 10];
		$waiting = array_map(function ($i) {
			return 10000 + $i;
		}, array_values(array_filter(range(2, $boundary - 10001), function ($i) use ($vis) {
			return $vis($i) === ITEM_UNAPPROVED;
		})));
		$this->assertSame(array_merge([10001], $waiting, range($boundary, 10060)), $left,
			'start post + the older posts waiting for approval (never deleted) + everything from the 10th newest approved on');
		$this->assertSame(10, count(array_filter($left, function ($id) use ($vis) {
			return $id !== 10001 && $vis($id - 10000) === ITEM_APPROVED;
		})));
	}

	public function test_equal_post_time_is_decided_by_post_id()
	{
		$this->forum(3, 0);
		// six posts with the same time, ids not in insertion order
		fixtures::topic($this->db, 1, 10, [505 => 5, 501 => 5, 503 => 5, 502 => 5, 506 => 5, 504 => 5], POST_NORMAL, ['uflagmey_pp_marked' => 1]);
		$this->cycle();
		$this->assertSame([504, 505, 506], $this->ids(10));
	}

	public function test_backlog_over_several_runs_ends_with_exactly_x()
	{
		$this->forum(100, 1);
		$this->topic(10, 400);
		$this->topic(11, 250);
		$runs = $this->cycle(60);

		$this->assertSame(8, $runs, '(299 + 149) / 60');
		$this->assertSame(array_merge([10001], range(10301, 10400)), $this->ids(10));
		$this->assertSame(array_merge([11001], range(11151, 11250)), $this->ids(11));
	}

	public function test_dry_run_count_equals_following_active_run()
	{
		$this->forum(20, 1, 1);
		$this->topic(10, 75, function ($i) {
			return $i % 4 === 0 ? ITEM_UNAPPROVED : ITEM_APPROVED;
		});
		$this->topic(11, 21);
		$this->topic(12, 5);
		$result = $this->pruner()->run(fixtures::NOW, 1000);
		$dry = $result['dryrun'][1];
		$this->assertSame([['LOG_UFLAGMEY_PP_DRYRUN_KEEP_AGE_TOPICS', ['K', $dry, 3, 20, 1]]], $this->logs);

		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_mode = 2');
		$before = count(fixtures::post_ids($this->db));
		$this->cycle(7);
		$this->assertSame($dry, $before - count(fixtures::post_ids($this->db)));
		$this->assertGreaterThan(0, $dry);
	}

	public function test_unmarked_topics_and_single_topic_log_key()
	{
		$this->forum(10, 1);
		$this->topic(10, 30);
		$this->topic(11, 30, null, 0);
		$this->cycle();

		$this->assertCount(11, $this->ids(10));
		$this->assertCount(30, $this->ids(11), 'not marked');
		$this->assertSame([['LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPIC', ['K', 19, 1, 10, 1]]], $this->logs);
	}

	public function test_rule_count_with_scope_forum_affects_nothing()
	{
		$this->forum(10, 1, 1, ['uflagmey_pp_scope' => 0]);
		$this->topic(10, 30);
		$result = $this->pruner()->run(fixtures::NOW, 1000);

		$this->assertSame([1 => 0], $result['dryrun']);
		$this->assertSame([['LOG_UFLAGMEY_PP_DRYRUN', ['K', 0]]], $this->logs);
	}

	public function test_without_schema_marker_3_the_age_rule_applies()
	{
		$this->config['uflagmey_pp_schema'] = 2;
		$this->forum(10, 1, 2, ['uflagmey_pp_days' => 30]);
		fixtures::topic($this->db, 1, 10, [100 => 90, 101 => 60, 102 => 40, 103 => 5], POST_NORMAL, ['uflagmey_pp_marked' => 1]);
		$this->topic(11, 30);
		$this->cycle();

		$this->assertSame([100, 103], $this->ids(10), 'age rule: posts older than 30 days, start post kept');
		$this->assertCount(30, $this->ids(11), 'young posts stay');
		$this->assertSame([['LOG_UFLAGMEY_PP_PRUNED_TOPICS', ['K', 2, 2]]], $this->logs);
	}

	public function test_query_builder_boundary_and_clamp()
	{
		$qb = fixtures::query_builder();
		$rows = [['post_time' => 30, 'post_id' => 9], ['post_time' => 20, 'post_id' => 8], ['post_time' => 20, 'post_id' => 7]];
		$this->assertSame([20, 7], $qb->boundary($rows, 3));
		$this->assertSame([30, 9], $qb->boundary($rows, 1));
		$this->assertNull($qb->boundary($rows, 4));

		$this->assertSame(1, $qb->keep_count(['uflagmey_pp_keep_count' => 0]));
		$this->assertSame(65535, $qb->keep_count(['uflagmey_pp_keep_count' => 70000]));
		$this->assertSame(query_builder::RULE_AGE, $qb->rule([]));
		$this->assertStringNotContainsString('OFFSET', strtoupper($qb->boundary_query(['uflagmey_pp_keep_first' => 1], ['topic_id' => 1])['WHERE']));
		$this->assertStringNotContainsString('uflagmey_pp_rule', $qb->forums_query(true, false)['SELECT']);
		$this->assertStringContainsString('uflagmey_pp_rule', $qb->forums_query(true, true)['SELECT']);
	}

	public function test_status_mix_fewer_than_x_approved_loses_nothing_exactly_x_loses_only_older_soft_deleted()
	{
		$this->forum(10, 1);
		// topic 10: start + 18 replies, every 2nd reply unapproved/soft-deleted: 9 approved replies < X
		$this->topic(10, 19, function ($i) {
			return ($i === 1 || $i % 2 === 1) ? ITEM_APPROVED : ($i % 4 === 0 ? ITEM_UNAPPROVED : ITEM_DELETED);
		});
		// topic 11: start + 6 older unapproved/soft-deleted replies + exactly X = 10 approved replies
		$this->topic(11, 17, function ($i) {
			return ($i >= 2 && $i <= 7) ? ($i % 2 === 0 ? ITEM_UNAPPROVED : ITEM_DELETED) : ITEM_APPROVED;
		});
		$this->cycle();

		$this->assertSame(range(10001, 10019), $this->ids(10), 'fewer than X approved: nothing goes, not even unapproved or soft-deleted posts');
		$this->assertSame(array_merge([11001, 11002, 11004, 11006], range(11008, 11017)), $this->ids(11),
			'exactly X approved: all kept; of the older non-approved posts only the soft-deleted go, unapproved ones stay');
	}
}
