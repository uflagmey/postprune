<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune\cron\task;

/**
 * Cron task: prune old posts in forums with post pruning enabled.
 */
class prune extends \phpbb\cron\task\base
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \uflagmey\postprune\prune\pruner */
	protected $pruner;

	/**
	 * @param \phpbb\config\config				$config
	 * @param \uflagmey\postprune\prune\pruner	$pruner
	 */
	public function __construct(\phpbb\config\config $config, \uflagmey\postprune\prune\pruner $pruner)
	{
		$this->config = $config;
		$this->pruner = $pruner;
	}

	/**
	 * At least one post forum has post pruning switched on. phpBB asks this on
	 * ordinary page requests (page footer cron check), so it must work in any
	 * schema state (pruner::has_forums()).
	 *
	 * @return bool
	 */
	public function is_runnable()
	{
		return $this->pruner->has_forums();
	}

	/**
	 * @return bool
	 */
	public function should_run()
	{
		return (int) $this->config['uflagmey_pp_last_run'] < time() - (int) $this->config['uflagmey_pp_gc'];
	}

	/**
	 * last_run is only set once no backlog is left, so a backlog is worked
	 * off in portions on the following page requests.
	 */
	public function run()
	{
		$now = time();
		$result = $this->pruner->run($now, (int) $this->config['uflagmey_pp_batch']);

		if (!$result['backlog'])
		{
			$this->config->set('uflagmey_pp_last_run', $now, false);
		}
	}
}
