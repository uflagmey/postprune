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
 * The rules for the per-forum settings, in one place: used when the ACP forum
 * form is saved and by the ACP preview with the values in the form.
 * Static, no service: an outdated container after a file upload still finds it.
 */
class settings
{
	/** @var int Range of the post age in days (USINT column uflagmey_pp_days) */
	const DAYS_MIN = 1;
	const DAYS_MAX = 65535;

	/** @var array Settings the ACP preview takes from the form; mode and count_sync do not change the count */
	const PREVIEW_KEYS = [
		'uflagmey_pp_scope',
		'uflagmey_pp_rule',
		'uflagmey_pp_days',
		'uflagmey_pp_keep_count',
		'uflagmey_pp_keep_first',
		'uflagmey_pp_skip_sticky',
	];

	/** @var array Yes/no settings and the language key of their error */
	const SWITCHES = [
		'uflagmey_pp_keep_first'	=> 'UFLAGMEY_PP_ERR_KEEP_FIRST',
		'uflagmey_pp_skip_sticky'	=> 'UFLAGMEY_PP_ERR_SKIP_STICKY',
		'uflagmey_pp_count_sync'	=> 'UFLAGMEY_PP_ERR_COUNT_SYNC',
	];

	/**
	 * Errors of a set of settings. Only the keys present are checked, so the
	 * same rules apply to the complete forum data when saving and to the
	 * preview values (stored settings overlaid with the form).
	 *
	 * @param array $settings	uflagmey_pp_* => value
	 * @return array	List of [language key, argument, …], empty if valid
	 */
	public static function errors(array $settings)
	{
		$errors = [];
		$int = function ($key) use ($settings) {
			return (int) $settings[$key];
		};

		if (array_key_exists('uflagmey_pp_mode', $settings)
			&& !in_array($int('uflagmey_pp_mode'), [query_builder::MODE_OFF, query_builder::MODE_DRYRUN, query_builder::MODE_ACTIVE], true))
		{
			$errors[] = ['UFLAGMEY_PP_ERR_MODE'];
		}

		if (array_key_exists('uflagmey_pp_scope', $settings)
			&& !in_array($int('uflagmey_pp_scope'), [query_builder::SCOPE_FORUM, query_builder::SCOPE_MARKED], true))
		{
			$errors[] = ['UFLAGMEY_PP_ERR_SCOPE'];
		}

		if (array_key_exists('uflagmey_pp_days', $settings)
			&& ($int('uflagmey_pp_days') < self::DAYS_MIN || $int('uflagmey_pp_days') > self::DAYS_MAX))
		{
			$errors[] = ['UFLAGMEY_PP_ERR_DAYS', self::DAYS_MAX];
		}

		if (array_key_exists('uflagmey_pp_rule', $settings))
		{
			$rule = $int('uflagmey_pp_rule');
			if (!in_array($rule, [query_builder::RULE_AGE, query_builder::RULE_COUNT], true))
			{
				$errors[] = ['UFLAGMEY_PP_ERR_RULE'];
			}
			else if ($rule === query_builder::RULE_COUNT && (int) ($settings['uflagmey_pp_scope'] ?? query_builder::SCOPE_FORUM) !== query_builder::SCOPE_MARKED)
			{
				$errors[] = ['UFLAGMEY_PP_ERR_RULE_SCOPE'];
			}
		}

		if (array_key_exists('uflagmey_pp_keep_count', $settings)
			&& ($int('uflagmey_pp_keep_count') < query_builder::KEEP_COUNT_MIN || $int('uflagmey_pp_keep_count') > query_builder::KEEP_COUNT_MAX))
		{
			$errors[] = ['UFLAGMEY_PP_ERR_KEEP_COUNT', query_builder::KEEP_COUNT_MIN, query_builder::KEEP_COUNT_MAX];
		}

		foreach (self::SWITCHES as $key => $error)
		{
			if (array_key_exists($key, $settings) && !in_array($int($key), [0, 1], true))
			{
				$errors[] = [$error];
			}
		}

		return $errors;
	}

	/**
	 * "When in doubt, delete nothing" (rc1): does this version NOT understand a stored forum row?
	 * True for an unknown mode, scope or rule value, and for a scope/rule other than the default
	 * stored in a row whose schema marker does not cover that setting yet (update incomplete,
	 * database restored, downgrade and back). Such a forum is skipped by cron, dry run, preview
	 * and topic notice; never a fallback to a broader rule. Only the keys present are checked.
	 *
	 * @param array	$forum	Forum row (uflagmey_pp_* columns as read from the database)
	 * @param int	$schema	schema::version()
	 * @return bool
	 */
	public static function doubt(array $forum, $schema)
	{
		$known = [
			'uflagmey_pp_mode'	=> [[query_builder::MODE_OFF, query_builder::MODE_DRYRUN, query_builder::MODE_ACTIVE], 0],
			'uflagmey_pp_scope'	=> [[query_builder::SCOPE_FORUM, query_builder::SCOPE_MARKED], schema::TOPIC_SCOPE],
			'uflagmey_pp_rule'	=> [[query_builder::RULE_AGE, query_builder::RULE_COUNT], schema::KEEP_COUNT],
		];

		foreach ($known as $key => list($values, $since))
		{
			if (!array_key_exists($key, $forum) || $forum[$key] === null)
			{
				continue;
			}

			$value = (int) $forum[$key];
			if (!in_array($value, $values, true) || ((int) $schema < $since && $value !== $values[0]))
			{
				return true;
			}
		}

		return false;
	}
}
