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
 * ACP preview (beta5): pruner::preview() counts like the dry run, from the stored settings,
 * in every mode, and changes nothing (no deletion, no log entry).
 */
class preview_test extends \PHPUnit\Framework\TestCase
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
	 * Marked topic with $n posts one hour apart, the newest $newest_days old; post ids $topic_id * 1000 + i.
	 */
	protected function topic($forum_id, $topic_id, $n, $newest_days = 1, $marked = 1, $type = POST_NORMAL)
	{
		$posts = [];
		for ($i = 1; $i <= $n; $i++)
		{
			$posts[$topic_id * 1000 + $i] = [$newest_days + ($n - $i) / 24, ITEM_APPROVED];
		}
		fixtures::topic($this->db, $forum_id, $topic_id, $posts, $type, ['uflagmey_pp_marked' => $marked]);
	}

	protected function dry_run()
	{
		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_mode = 1');
		$this->logs = [];
		$result = $this->pruner()->run(fixtures::NOW, 1000);

		return $result['dryrun'];
	}

	public function test_rule_count_in_mode_off_equals_the_following_dry_run_and_changes_nothing()
	{
		fixtures::forum($this->db, 1, 'K', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 1, 'uflagmey_pp_keep_count' => 10]);
		$this->topic(1, 10, 40);
		$this->topic(1, 11, 12);
		$this->topic(1, 12, 5);
		$this->topic(1, 13, 30, 1, 0);
		$before = fixtures::post_ids($this->db);

		$preview = $this->pruner()->preview(1, fixtures::NOW);

		$this->assertSame(29 + 1, $preview['num'], 'K10: 39 replies, keep 10 -> 29; K11: 11 replies -> 1; K12 too short; K13 not marked');
		$this->assertSame(3, $preview['topics']);
		$this->assertTrue($preview['post_forum']);
		$this->assertSame(0, (int) $preview['forum']['uflagmey_pp_mode']);
		$this->assertSame(fixtures::NOW - (int) round((1 + 38 / 24) * fixtures::DAY), $preview['oldest'], 'oldest candidate: K10 post 2');
		$this->assertSame(fixtures::NOW - (int) round((1 + 10 / 24) * fixtures::DAY), $preview['newest'], 'newest candidates: K10 post 30 and K11 post 2');
		$this->assertSame($before, fixtures::post_ids($this->db));
		$this->assertSame([], $this->logs);
		$this->assertSame([], fixtures::$delete_calls);

		$this->assertSame([1 => $preview['num']], $this->dry_run());
	}

	public function test_age_rule_whole_forum_and_marked_topics()
	{
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 2, 'uflagmey_pp_days' => 2]);
		$this->topic(1, 10, 72);
		$this->topic(1, 11, 30, 1, 0, POST_STICKY);
		fixtures::forum($this->db, 2, 'B', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_days' => 2, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_keep_first' => 0]);
		$this->topic(2, 20, 72);
		$this->topic(2, 21, 72, 1, 0);

		$a = $this->pruner()->preview(1, fixtures::NOW);
		$b = $this->pruner()->preview(2, fixtures::NOW);

		$this->assertSame(46, $a['num'], 'posts older than 2 days (47 of 72), start post kept, sticky skipped');
		$this->assertNull($a['topics']);
		$this->assertSame(47, $b['num'], 'marked topic only, start post goes too');
		$this->assertSame(1, $b['topics']);
		$this->assertSame([], $this->logs);
		$this->assertSame([1 => 46, 2 => 47], $this->dry_run());
	}

	public function test_unknown_forum_category_and_no_marked_topic()
	{
		fixtures::forum($this->db, 1, 'Cat', FORUM_CAT, ['uflagmey_pp_mode' => 2, 'uflagmey_pp_days' => 1]);
		fixtures::forum($this->db, 2, 'M', FORUM_POST, ['uflagmey_pp_mode' => 1, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_days' => 1]);
		$this->topic(2, 20, 10, 5, 0);

		$this->assertNull($this->pruner()->preview(99, fixtures::NOW));

		$cat = $this->pruner()->preview(1, fixtures::NOW);
		$this->assertFalse($cat['post_forum']);
		$this->assertSame(0, $cat['num']);

		$none = $this->pruner()->preview(2, fixtures::NOW);
		$this->assertSame(0, $none['topics']);
		$this->assertSame(0, $none['num']);
		$this->assertSame([], $this->logs);
	}

	public function test_without_schema_marker_3_the_rule_columns_are_not_read()
	{
		$this->config['uflagmey_pp_schema'] = 2;
		fixtures::forum($this->db, 1, 'K', FORUM_POST, ['uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 1, 'uflagmey_pp_keep_count' => 1, 'uflagmey_pp_days' => 3]);
		$this->topic(1, 10, 96);

		$preview = $this->pruner()->preview(1, fixtures::NOW);

		$this->assertArrayNotHasKey('uflagmey_pp_rule', $preview['forum']);
		$this->assertSame(46, $preview['num'], 'age rule, 3 days: 47 of 96 posts are older, start post kept');
	}
}
