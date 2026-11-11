<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune\prune;

/**
 * Database schema version of the extension, written by the migrations into the
 * config value uflagmey_pp_schema. When beta3 files are uploaded over an active
 * beta2 without re-enabling the extension, the beta3 columns do not exist yet:
 * every use of scope/marked must ask topic_scope() first and otherwise keep the
 * beta2 behaviour. Plain class (no service), so it also works while phpBB still
 * runs the container compiled for beta2.
 */
class schema
{
	/** @var int Schema version that brings forum scope and topic marks (m2_topic_scope) */
	const TOPIC_SCOPE = 2;

	/**
	 * @param \phpbb\config\config|null $config	null: the service was built by an
	 *											outdated container, so the update is incomplete
	 * @return int
	 */
	public static function version($config)
	{
		return ($config instanceof \phpbb\config\config) ? (int) $config['uflagmey_pp_schema'] : 0;
	}

	/**
	 * Forum scope and topic marks may be used.
	 *
	 * @param \phpbb\config\config|null $config
	 * @return bool
	 */
	public static function topic_scope($config)
	{
		return self::version($config) >= self::TOPIC_SCOPE;
	}
}
