<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune\tests\cron;

use uflagmey\postprune\cron\task\prune;

/**
 * rc1 (R-10 / SR-11): on every page request the core asks is_runnable() && should_run(); the database is only
 * asked (pruner::has_forums()) when the task is due.
 */
class prune_task_test extends \PHPUnit\Framework\TestCase
{
	protected function task($last_run, $has_forums_calls)
	{
		$pruner = $this->createMock(\uflagmey\postprune\prune\pruner::class);
		$pruner->expects($this->exactly($has_forums_calls))->method('has_forums')->willReturn(true);
		$config = new \phpbb\config\config(['uflagmey_pp_last_run' => $last_run, 'uflagmey_pp_gc' => 86400]);

		return new prune($config, $pruner);
	}

	public function test_not_due_asks_no_database()
	{
		$task = $this->task(time() - 60, 0);
		$this->assertFalse($task->is_runnable() && $task->should_run());
	}

	public function test_due_asks_the_database_once()
	{
		$task = $this->task(time() - 2 * 86400, 1);
		$this->assertTrue($task->is_runnable() && $task->should_run());
	}
}
