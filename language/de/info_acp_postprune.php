<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'LOG_UFLAGMEY_PP_DRYRUN'	=> [
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br />» %2$d Beitrag im Forum „%1$s“ würde gelöscht',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br />» %2$d Beiträge im Forum „%1$s“ würden gelöscht',
	],
	'LOG_UFLAGMEY_PP_PRUNED'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br />» %2$d Beitrag im Forum „%1$s“ gelöscht',
		2	=> '<strong>Beitrags-Pruning</strong><br />» %2$d Beiträge im Forum „%1$s“ gelöscht',
	],
]);
