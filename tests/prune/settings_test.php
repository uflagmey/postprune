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

use uflagmey\postprune\prune\settings;

/**
 * The rules of the per-forum settings (beta5): one function for saving and preview.
 */
class settings_test extends \PHPUnit\Framework\TestCase
{
	protected function valid()
	{
		return [
			'uflagmey_pp_mode'			=> 2,
			'uflagmey_pp_scope'			=> 1,
			'uflagmey_pp_rule'			=> 1,
			'uflagmey_pp_days'			=> 30,
			'uflagmey_pp_keep_count'	=> 100,
			'uflagmey_pp_keep_first'	=> 1,
			'uflagmey_pp_skip_sticky'	=> 0,
			'uflagmey_pp_count_sync'	=> 1,
		];
	}

	protected function keys(array $settings)
	{
		return array_map(function ($error) {
			return $error[0];
		}, settings::errors($settings));
	}

	public function test_valid_settings_and_bounds()
	{
		$this->assertSame([], settings::errors($this->valid()));
		$this->assertSame([], settings::errors(['uflagmey_pp_days' => 1, 'uflagmey_pp_keep_count' => 65535]));
		$this->assertSame([], settings::errors(['uflagmey_pp_days' => 65535, 'uflagmey_pp_keep_count' => 1]));
		$this->assertSame([], settings::errors([]), 'only keys present are checked');
	}

	public function test_each_rule()
	{
		$cases = [
			['uflagmey_pp_mode', 3, 'UFLAGMEY_PP_ERR_MODE'],
			['uflagmey_pp_mode', -1, 'UFLAGMEY_PP_ERR_MODE'],
			['uflagmey_pp_scope', 2, 'UFLAGMEY_PP_ERR_SCOPE'],
			['uflagmey_pp_rule', 5, 'UFLAGMEY_PP_ERR_RULE'],
			['uflagmey_pp_days', 0, 'UFLAGMEY_PP_ERR_DAYS'],
			['uflagmey_pp_days', 65536, 'UFLAGMEY_PP_ERR_DAYS'],
			['uflagmey_pp_keep_count', 0, 'UFLAGMEY_PP_ERR_KEEP_COUNT'],
			['uflagmey_pp_keep_count', 65536, 'UFLAGMEY_PP_ERR_KEEP_COUNT'],
			['uflagmey_pp_keep_first', 7, 'UFLAGMEY_PP_ERR_KEEP_FIRST'],
			['uflagmey_pp_skip_sticky', 2, 'UFLAGMEY_PP_ERR_SKIP_STICKY'],
			['uflagmey_pp_count_sync', -1, 'UFLAGMEY_PP_ERR_COUNT_SYNC'],
		];
		foreach ($cases as list($key, $value, $error))
		{
			$this->assertSame([$error], $this->keys([$key => $value] + ($key === 'uflagmey_pp_scope' ? ['uflagmey_pp_rule' => 0] : []) + $this->valid()), "$key = $value");
		}
	}

	public function test_rule_count_needs_marked_topics()
	{
		$this->assertSame(['UFLAGMEY_PP_ERR_RULE_SCOPE'], $this->keys(['uflagmey_pp_scope' => 0] + $this->valid()));
		$this->assertSame(['UFLAGMEY_PP_ERR_RULE_SCOPE'], $this->keys(['uflagmey_pp_rule' => 1]), 'no scope: whole forum');
		$this->assertSame([], $this->keys(['uflagmey_pp_scope' => 0, 'uflagmey_pp_rule' => 0] + $this->valid()));
	}

	public function test_arguments_for_the_messages()
	{
		$this->assertSame([['UFLAGMEY_PP_ERR_DAYS', 65535], ['UFLAGMEY_PP_ERR_KEEP_COUNT', 1, 65535]],
			settings::errors(['uflagmey_pp_days' => 0, 'uflagmey_pp_keep_count' => 0]));
	}
}
