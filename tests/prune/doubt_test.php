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
use uflagmey\postprune\prune\settings;

/**
 * "When in doubt, delete nothing" (rc1, R-03 / SR-04): a forum whose stored mode, scope or rule this
 * version does not know, or whose row carries a scope/rule the schema marker does not cover yet
 * (update incomplete, database restored, downgrade and back), is skipped by cron, dry run and preview,
 * with one critical log entry per cycle. Never a fallback to a broader rule.
 */
class doubt_test extends \PHPUnit\Framework\TestCase
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
		fixtures::$delete_calls = [];
	}

	protected function pruner($tools = true)
	{
		$log = $this->createMock(\phpbb\log\log_interface::class);
		$log->method('add')->willReturnCallback(function ($mode, $user_id, $ip, $operation, $time, $data) {
			$this->logs[] = [$mode, $operation, $data];
			return 1;
		});
		$user = $this->createMock(\phpbb\user::class);
		$user->data = ['user_id' => 2];
		$user->ip = '10.0.0.1';

		return new pruner($this->db, $this->config, $log, $user, fixtures::query_builder(), './', 'php', $tools ? new \phpbb\db\tools\tools($this->db) : null);
	}

	/**
	 * Forum $fid with one marked topic: start 100 d, replies 90..40 d (all older than 30 days, more than X = 2).
	 */
	protected function forum($fid, array $settings)
	{
		fixtures::forum($this->db, $fid, 'F' . $fid, FORUM_POST, $settings);
		fixtures::topic($this->db, $fid, $fid * 10, [$fid * 100 => 100, $fid * 100 + 1 => 90, $fid * 100 + 2 => 80, $fid * 100 + 3 => 70, $fid * 100 + 4 => 60, $fid * 100 + 5 => 40],
			POST_NORMAL, ['uflagmey_pp_marked' => 1]);
	}

	protected function posts($fid)
	{
		$result = $this->db->sql_query('SELECT COUNT(*) AS n FROM phpbb_posts WHERE forum_id = ' . (int) $fid);
		$n = (int) $this->db->sql_fetchfield('n');
		$this->db->sql_freeresult($result);

		return $n;
	}

	public function test_doubt_rule()
	{
		$row = ['uflagmey_pp_mode' => '2', 'uflagmey_pp_scope' => '1', 'uflagmey_pp_rule' => '1'];
		$this->assertFalse(settings::doubt($row, 3));
		$this->assertFalse(settings::doubt([], 0), 'columns not read: nothing to doubt');
		$this->assertFalse(settings::doubt(['uflagmey_pp_mode' => '1', 'uflagmey_pp_scope' => '1', 'uflagmey_pp_rule' => '0'], 2), 'schema 2 knows scope; rule 0 is the age rule');
		$this->assertFalse(settings::doubt(['uflagmey_pp_mode' => '0', 'uflagmey_pp_scope' => '0'], 1));

		$this->assertTrue(settings::doubt(['uflagmey_pp_mode' => '3'] + $row, 3), 'unknown mode');
		$this->assertTrue(settings::doubt(['uflagmey_pp_scope' => '2'] + $row, 3), 'unknown scope');
		$this->assertTrue(settings::doubt(['uflagmey_pp_rule' => '7'] + $row, 3), 'unknown rule');
		$this->assertTrue(settings::doubt($row, 2), 'rule "count" stored, schema marker 2');
		$this->assertTrue(settings::doubt(['uflagmey_pp_mode' => '2', 'uflagmey_pp_scope' => '1'], 1), 'scope "marked" stored, schema marker 1');
	}

	public function test_marker_2_with_stored_rule_count_skips_the_forum_and_logs_once()
	{
		$this->config['uflagmey_pp_schema'] = 2;
		$this->forum(1, ['uflagmey_pp_mode' => 2, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 1, 'uflagmey_pp_keep_count' => 2]);
		$this->forum(2, ['uflagmey_pp_mode' => 2, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 0]);

		$result = $this->pruner()->run(fixtures::NOW, 1000);

		$this->assertSame(6, $this->posts(1), 'nothing deleted, no fallback to the age rule');
		$this->assertSame(1, $this->posts(2), 'forum 2 (age rule, marker 2 knows it) is pruned as usual: only the start post stays');
		$this->assertSame([2 => 5], $result['pruned']);
		$this->assertSame([
			['admin', 'LOG_UFLAGMEY_PP_PRUNED_TOPIC', ['F2', 5, 1]],
			['critical', 'LOG_UFLAGMEY_PP_SKIPPED', ['F1']],
		], $this->logs, 'deletions are logged at once, skipped forums when the cycle closes');
	}

	public function test_unknown_stored_values_skip_cron_and_dry_run()
	{
		$this->forum(1, ['uflagmey_pp_mode' => 2, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 7]);
		$this->forum(2, ['uflagmey_pp_mode' => 1, 'uflagmey_pp_scope' => 5]);
		$this->forum(3, ['uflagmey_pp_mode' => 3, 'uflagmey_pp_scope' => 0]);

		$result = $this->pruner()->run(fixtures::NOW, 1000);

		$this->assertSame([6, 6, 6], [$this->posts(1), $this->posts(2), $this->posts(3)]);
		$this->assertSame(['backlog' => false, 'pruned' => [], 'dryrun' => []], $result);
		$this->assertSame([
			['critical', 'LOG_UFLAGMEY_PP_SKIPPED', ['F1']],
			['critical', 'LOG_UFLAGMEY_PP_SKIPPED', ['F2']],
			['critical', 'LOG_UFLAGMEY_PP_SKIPPED', ['F3']],
		], $this->logs);
		$this->assertSame([], fixtures::$delete_calls);
	}

	public function test_skip_is_logged_once_per_cycle_in_the_closing_run()
	{
		$this->forum(1, ['uflagmey_pp_mode' => 2, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 7]);
		$this->forum(2, ['uflagmey_pp_mode' => 2, 'uflagmey_pp_scope' => 1]);
		$pruner = $this->pruner();

		$first = $pruner->run(fixtures::NOW, 3);
		$this->assertTrue($first['backlog']);
		$this->assertSame([], array_filter($this->logs, function ($l) {
			return $l[1] === 'LOG_UFLAGMEY_PP_SKIPPED';
		}), 'not in a run that leaves a backlog');

		$second = $pruner->run(fixtures::NOW, 3);
		$this->assertFalse($second['backlog']);
		$this->assertCount(1, array_filter($this->logs, function ($l) {
			return $l[1] === 'LOG_UFLAGMEY_PP_SKIPPED';
		}));
		$this->assertSame(6, $this->posts(1));
	}

	public function test_marker_1_with_stored_scope_marked_does_not_fall_back_to_the_whole_forum()
	{
		$this->config['uflagmey_pp_schema'] = 1;
		$this->forum(1, ['uflagmey_pp_mode' => 2, 'uflagmey_pp_scope' => 1]);
		fixtures::topic($this->db, 1, 11, [150 => 100, 151 => 90], POST_NORMAL, ['uflagmey_pp_marked' => 0]);

		$this->pruner()->run(fixtures::NOW, 1000);

		$this->assertSame(8, $this->posts(1), 'neither the marked nor the unmarked topic loses a post');
		$this->assertSame([['critical', 'LOG_UFLAGMEY_PP_SKIPPED', ['F1']]], $this->logs);
	}

	public function test_preview_names_the_doubt_and_counts_nothing()
	{
		$this->forum(1, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 7]);

		$preview = $this->pruner()->preview(1, fixtures::NOW);
		$this->assertTrue($preview['doubt']);
		$this->assertSame(0, $preview['num']);

		$preview = $this->pruner()->preview(1, fixtures::NOW, ['uflagmey_pp_rule' => 0]);
		$this->assertFalse($preview['doubt'], 'a valid rule from the form replaces the unknown stored one');
		$this->assertSame(5, $preview['num']);
		$this->assertSame([], $this->logs, 'the preview writes no log');
	}

	public function test_preview_with_marker_2_and_stored_rule_count()
	{
		$this->config['uflagmey_pp_schema'] = 2;
		$this->forum(1, ['uflagmey_pp_mode' => 1, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 1, 'uflagmey_pp_keep_count' => 2]);

		$this->assertTrue($this->pruner()->preview(1, fixtures::NOW)['doubt']);
	}

	public function test_without_db_tools_marker_below_3_reads_no_rule_column()
	{
		// Outdated container (files uploaded, cache not purged): the pruner is built without dbal.tools and
		// cannot see columns the marker does not announce; the update notice in the ACP asks to finish the update.
		$this->config['uflagmey_pp_schema'] = 2;
		$this->forum(1, ['uflagmey_pp_mode' => 1, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 0]);

		$result = $this->pruner(false)->run(fixtures::NOW, 1000);
		$this->assertSame([1 => 5], $result['dryrun']);
	}
}
