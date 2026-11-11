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
		0	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» Im Forum „%1$s“ würde kein Beitrag gelöscht',
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag im Forum „%1$s“ würde gelöscht',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge im Forum „%1$s“ würden gelöscht',
	],
	'LOG_UFLAGMEY_PP_PRUNED'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag im Forum „%1$s“ gelöscht',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge im Forum „%1$s“ gelöscht',
	],
	'LOG_UFLAGMEY_PP_TOPIC_MARKED'			=> '<strong>Beitrags-Pruning: Thema markiert</strong><br>» „%1$s“ im Forum „%2$s“',
	'LOG_UFLAGMEY_PP_TOPIC_UNMARKED'		=> '<strong>Beitrags-Pruning: Markierung entfernt</strong><br>» „%1$s“ im Forum „%2$s“',
	'LOG_UFLAGMEY_PP_TOPIC_UNMARKED_MOVED'	=> '<strong>Beitrags-Pruning: Markierung beim Verschieben entfernt</strong><br>» „%1$s“ von Forum „%2$s“ nach „%3$s“',
]);
