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
	 * Requires phpBB 3.3.x and PHP 7.2 or newer.
	 *
	 * @return bool
	 */
	public function is_enableable()
	{
		$config = $this->container->get('config');

		return phpbb_version_compare($config['version'], '3.3.0', '>=')
			&& phpbb_version_compare($config['version'], '4.0.0-dev', '<')
			&& version_compare(PHP_VERSION, '7.2.0', '>=');
	}
}
