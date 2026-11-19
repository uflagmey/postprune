<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune;

/**
 * Extension base class
 */
class ext extends \phpbb\extension\base
{
	/**
	 * Requires phpBB 3.3.3 or newer (3.3.x) and PHP 7.2 or newer.
	 *
	 * @return bool
	 */
	public function is_enableable()
	{
		$config = $this->container->get('config');

		return phpbb_version_compare($config['version'], '3.3.3', '>=')
			&& phpbb_version_compare($config['version'], '4.0.0-dev', '<')
			&& version_compare(PHP_VERSION, '7.2.0', '>=');
	}

	/**
	 * After the migrations: make sure the schema marker matches the installed migrations.
	 *
	 * m2 writes uflagmey_pp_schema with config.add, which skips a name that is
	 * already in the (cached) config, and m3 raises it with config.update, which
	 * only changes an existing row. If a database backup from before the update
	 * is restored while phpBB still caches the marker, the next enable installs
	 * m2/m3 without a marker row. Re-enabling the extension repairs that: the
	 * marker becomes 2 (m2 installed, its columns present) or 3 (also m3 with
	 * its columns), also when a lower value is left in the database.
	 *
	 * @param mixed $old_state
	 * @return mixed
	 */
	public function enable_step($old_state)
	{
		$state = parent::enable_step($old_state);

		if ($state === false)
		{
			$this->repair_schema_marker();
		}

		return $state;
	}

	/**
	 * @return void
	 */
	protected function repair_schema_marker()
	{
		$db_tools = $this->container->get('dbal.tools');
		$prefix = $this->container->getParameter('core.table_prefix');

		$target = 0;
		if ($this->migrator->migration_state('\uflagmey\postprune\migrations\v10x\m2_topic_scope') !== false
			&& $db_tools->sql_column_exists($prefix . 'forums', 'uflagmey_pp_scope') && $db_tools->sql_column_exists($prefix . 'topics', 'uflagmey_pp_marked'))
		{
			$target = 2;
			if ($this->migrator->migration_state('\uflagmey\postprune\migrations\v10x\m3_keep_count') !== false
				&& $db_tools->sql_column_exists($prefix . 'forums', 'uflagmey_pp_rule') && $db_tools->sql_column_exists($prefix . 'forums', 'uflagmey_pp_keep_count'))
			{
				$target = 3;
			}
		}

		if (!$target)
		{
			return;
		}

		$db = $this->container->get('dbal.conn');
		$sql = 'SELECT config_value
			FROM ' . $prefix . "config
			WHERE config_name = 'uflagmey_pp_schema'";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		$config = $this->container->get('config');
		if ($row === false)
		{
			// Drop the stale cached value first, otherwise config::set() would not insert the row.
			$config->delete('uflagmey_pp_schema');
			$config->set('uflagmey_pp_schema', $target);
		}
		else if ((int) $row['config_value'] < $target)
		{
			$config->set('uflagmey_pp_schema', $target);
		}
	}
}
