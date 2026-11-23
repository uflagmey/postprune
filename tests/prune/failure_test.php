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
 * Error handling in the cron (rc1, R-09 / SR-10, V-07): a missing column is a schema mismatch ("purge the
 * cache"), any other SQL error gets its own entry with the database's message; an SQL error inside
 * delete_posts() ends the run cleanly instead of ending the request with the cron lock still held.
 */
class failure_test extends \PHPUnit\Framework\TestCase
{
	/** @var \phpbb\db\driver\sqlite3 */
	protected $db;

	/** @var array */
	protected $logs;

	/** @var \phpbb\config\config */
	protected $config;

	// phpcs:ignore PhpbbCodingStandard.NamingConventions.LowercaseUnderscoredFunctions.NotAllowed -- PHPUnit hook name
	protected function setUp(): void
	{
		$this->db = fixtures::db();
		$this->logs = [];
		fixtures::$delete_calls = [];
		fixtures::forum($this->db, 1, 'A', FORUM_POST, ['uflagmey_pp_mode' => 2]);
		fixtures::topic($this->db, 1, 10, [100 => 90, 101 => 80, 102 => 70]);
	}

	protected function pruner($tools = true)
	{
		$log = $this->createMock(\phpbb\log\log_interface::class);
		$log->method('add')->willReturnCallback(function ($mode, $user_id, $ip, $operation, $time, $data) {
			$this->logs[] = [$mode, $operation, $data];
			return 1;
		});
		$this->config = $this->config ?: new \phpbb\config\config(['num_posts' => 1000, 'uflagmey_pp_schema' => 3, 'uflagmey_pp_cursor' => 0]);

		return new pruner($this->db, $this->config, $log, $this->createMock(\phpbb\user::class), fixtures::query_builder(), './', 'php', $tools ? new \phpbb\db\tools\tools($this->db) : null);
	}

	protected function result_ok(array $result)
	{
		$this->assertFalse($result['backlog'], 'the cycle closes (last_run), phpBB releases its lock');
	}

	public function test_other_sql_error_is_named_with_the_database_message()
	{
		$this->db->sql_query('ALTER TABLE phpbb_posts RENAME TO phpbb_posts_gone');
		$this->result_ok($this->pruner()->run(fixtures::NOW, 100));

		$this->assertCount(1, $this->logs);
		$this->assertSame(['critical', 'LOG_UFLAGMEY_PP_SQL_ERROR'], array_slice($this->logs[0], 0, 2));
		$this->assertStringContainsString('no such table', $this->logs[0][2][0]);
	}

	public function test_missing_column_stays_a_schema_mismatch()
	{
		$this->db->sql_query('ALTER TABLE phpbb_forums DROP COLUMN uflagmey_pp_rule');
		$this->result_ok($this->pruner()->run(fixtures::NOW, 100));

		$this->assertSame([['critical', 'LOG_UFLAGMEY_PP_SCHEMA_MISMATCH', []]], $this->logs);
	}

	public function test_without_db_tools_every_select_error_is_a_schema_mismatch_as_before()
	{
		$this->db->sql_query('ALTER TABLE phpbb_posts RENAME TO phpbb_posts_gone');
		$this->result_ok($this->pruner(false)->run(fixtures::NOW, 100));

		$this->assertSame([['critical', 'LOG_UFLAGMEY_PP_SCHEMA_MISMATCH', []]], $this->logs);
	}

	public function test_sql_error_inside_delete_posts_ends_the_run_cleanly()
	{
		$this->db->sql_query("CREATE TRIGGER no_delete BEFORE DELETE ON phpbb_posts BEGIN SELECT RAISE(ABORT, 'P25 delete refused'); END");
		$this->result_ok($this->pruner()->run(fixtures::NOW, 100));

		$this->assertCount(1, fixtures::$delete_calls, 'delete_posts() was called');
		$this->assertSame([100, 101, 102], fixtures::post_ids($this->db), 'nothing deleted');
		$this->assertCount(1, $this->logs);
		$this->assertSame(['critical', 'LOG_UFLAGMEY_PP_DELETE_ERROR'], array_slice($this->logs[0], 0, 2));
		$this->assertSame(['A', 2], array_slice($this->logs[0][2], 0, 2), 'forum name and number of candidates of this run (start post protected)');
		$this->assertStringContainsString('P25 delete refused', $this->logs[0][2][2]);
		$this->assertSame(1000, (int) $this->config['num_posts'], 'no num_posts correction after the error');

		// the temporary error handler is gone again
		$current = set_error_handler(function () {
			return false;
		});
		restore_error_handler();
		$scope = ($current instanceof \Closure) ? (new \ReflectionFunction($current))->getClosureScopeClass() : null;
		$this->assertFalse($scope !== null && $scope->getName() === pruner::class, 'error handler of the pruner still active');
	}

	/**
	 * B-3: after an SQL error the cursor is 0 again. sql_failure() closes the cycle; a cursor left behind would
	 * make the next cycle skip the count forums before the cursor's forum and the topics up to it.
	 */
	public function test_sql_error_resets_the_cursor()
	{
		$this->config = new \phpbb\config\config(['num_posts' => 1000, 'uflagmey_pp_schema' => 3, 'uflagmey_pp_cursor' => 10]);
		$this->db->sql_query('ALTER TABLE phpbb_posts RENAME TO phpbb_posts_gone');
		$this->result_ok($this->pruner()->run(fixtures::NOW, 100));

		$this->assertSame('LOG_UFLAGMEY_PP_SQL_ERROR', $this->logs[0][1]);
		$this->assertSame(0, (int) $this->config['uflagmey_pp_cursor']);
	}

	public function test_error_inside_delete_posts_resets_the_cursor()
	{
		$this->config = new \phpbb\config\config(['num_posts' => 1000, 'uflagmey_pp_schema' => 3, 'uflagmey_pp_cursor' => 10]);
		$this->db->sql_query("CREATE TRIGGER no_delete BEFORE DELETE ON phpbb_posts BEGIN SELECT RAISE(ABORT, 'refused'); END");
		$this->result_ok($this->pruner()->run(fixtures::NOW, 100));

		$this->assertSame('LOG_UFLAGMEY_PP_DELETE_ERROR', $this->logs[0][1]);
		$this->assertSame(0, (int) $this->config['uflagmey_pp_cursor']);
	}

	public function test_schema_mismatch_resets_the_cursor()
	{
		$this->config = new \phpbb\config\config(['num_posts' => 1000, 'uflagmey_pp_schema' => 3, 'uflagmey_pp_cursor' => 10]);
		$this->db->sql_query('ALTER TABLE phpbb_forums DROP COLUMN uflagmey_pp_rule');
		$this->result_ok($this->pruner()->run(fixtures::NOW, 100));

		$this->assertSame('LOG_UFLAGMEY_PP_SCHEMA_MISMATCH', $this->logs[0][1]);
		$this->assertSame(0, (int) $this->config['uflagmey_pp_cursor']);
	}
}
