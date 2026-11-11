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
	 * After the migrations: make sure the schema marker of m2 exists.
	 *
	 * m2 writes uflagmey_pp_schema with config.add, which skips a name that is
	 * already in the (cached) config. If a database backup from before the
	 * update is restored while phpBB still caches the marker, the next enable
	 * installs m2 without the marker row. Re-enabling the extension repairs
	 * that: m2 installed, its columns present, no marker row in the database.
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
		if ($this->migrator->migration_state('\uflagmey\postprune\migrations\v10x\m2_topic_scope') === false)
		{
			return;
		}

		$db = $this->container->get('dbal.conn');
		$db_tools = $this->container->get('dbal.tools');
		$prefix = $this->container->getParameter('core.table_prefix');
		if (!$db_tools->sql_column_exists($prefix . 'forums', 'uflagmey_pp_scope') || !$db_tools->sql_column_exists($prefix . 'topics', 'uflagmey_pp_marked'))
		{
			return;
		}

		$sql = 'SELECT config_value
			FROM ' . $prefix . "config
			WHERE config_name = 'uflagmey_pp_schema'";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if ($row === false)
		{
			// Drop the stale cached value first, otherwise config::set() would not insert the row.
			$config = $this->container->get('config');
			$config->delete('uflagmey_pp_schema');
			$config->set('uflagmey_pp_schema', 2);
		}
	}
}
