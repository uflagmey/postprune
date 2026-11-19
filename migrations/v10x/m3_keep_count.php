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
 * beta5: per-forum deletion rule (by age / keep the newest X posts per topic) and X.
 * Existing forum settings stay untouched; the rule starts at 0 (by age, as before).
 * Schema marker 3 tells the code that these columns exist (see prune\schema).
 */
class m3_keep_count extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['uflagmey_pp_schema']) && (int) $this->config['uflagmey_pp_schema'] >= 3
			&& $this->db_tools->sql_column_exists($this->table_prefix . 'forums', 'uflagmey_pp_rule')
			&& $this->db_tools->sql_column_exists($this->table_prefix . 'forums', 'uflagmey_pp_keep_count');
	}

	public static function depends_on()
	{
		return ['\uflagmey\postprune\migrations\v10x\m2_topic_scope'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'forums' => [
					'uflagmey_pp_rule'			=> ['TINT:1', 0],
					'uflagmey_pp_keep_count'	=> ['USINT', 100],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'forums' => [
					'uflagmey_pp_rule',
					'uflagmey_pp_keep_count',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['uflagmey_pp_schema', 3]],
		];
	}

	public function revert_data()
	{
		// config.update has no automatic reverse; back to the m2 marker (m2's revert then removes the value).
		return [
			['config.update', ['uflagmey_pp_schema', 2]],
		];
	}
}
