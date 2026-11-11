<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune\migrations\v10x;

/**
 * Per-forum prune settings and global cron config.
 */
class m1_initial extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'forums', 'uflagmey_pp_mode');
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'forums' => [
					'uflagmey_pp_mode'			=> ['TINT:1', 0],
					'uflagmey_pp_days'			=> ['USINT', 30],
					'uflagmey_pp_keep_first'	=> ['BOOL', 1],
					'uflagmey_pp_skip_sticky'	=> ['BOOL', 1],
					'uflagmey_pp_count_sync'	=> ['BOOL', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'forums' => [
					'uflagmey_pp_mode',
					'uflagmey_pp_days',
					'uflagmey_pp_keep_first',
					'uflagmey_pp_skip_sticky',
					'uflagmey_pp_count_sync',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['uflagmey_pp_last_run', 0, true]],
			['config.add', ['uflagmey_pp_gc', 86400]],
			['config.add', ['uflagmey_pp_batch', 200]],
		];
	}
}
