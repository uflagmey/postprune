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
 * beta3: per-forum scope (whole forum / marked topics only) and the topic mark.
 * Existing forum settings stay untouched; scope starts at 0 (whole forum, as before).
 * The config value uflagmey_pp_schema tells the code that these columns exist (see prune\schema).
 */
class m2_topic_scope extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['uflagmey_pp_schema'])
			&& $this->db_tools->sql_column_exists($this->table_prefix . 'forums', 'uflagmey_pp_scope')
			&& $this->db_tools->sql_column_exists($this->table_prefix . 'topics', 'uflagmey_pp_marked');
	}

	public static function depends_on()
	{
		return ['\uflagmey\postprune\migrations\v10x\m1_initial'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'forums' => [
					'uflagmey_pp_scope'		=> ['TINT:1', 0],
				],
				$this->table_prefix . 'topics' => [
					'uflagmey_pp_marked'	=> ['BOOL', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'forums' => [
					'uflagmey_pp_scope',
				],
				$this->table_prefix . 'topics' => [
					'uflagmey_pp_marked',
				],
			],
		];
	}

	public function update_data()
	{
		// config.add is reverted (removed) automatically on revert/purge.
		return [
			['config.add', ['uflagmey_pp_schema', 2]],
		];
	}
}
