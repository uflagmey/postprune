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
 * rc1: cursor of the cron under rule "count" (prune\pruner::CURSOR): the last topic the active run
 * finished, so the next run continues behind it; 0 at the start of a cycle. Dynamic (changes on
 * every run with a backlog, not cached). No schema change, so no new schema marker.
 */
class m4_cursor extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['uflagmey_pp_cursor']);
	}

	public static function depends_on()
	{
		return ['\uflagmey\postprune\migrations\v10x\m3_keep_count'];
	}

	public function update_data()
	{
		return [
			['config.add', ['uflagmey_pp_cursor', 0, true]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['uflagmey_pp_cursor']],
		];
	}
}
