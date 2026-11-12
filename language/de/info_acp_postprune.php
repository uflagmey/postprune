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
	'LOG_UFLAGMEY_PP_DRYRUN_TOPIC'	=> [
		0	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» Im %3$d markierten Thema im Forum „%1$s“ würde kein Beitrag gelöscht',
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Thema im Forum „%1$s“ würde gelöscht',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ würden gelöscht',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_TOPICS'	=> [
		0	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» In den %3$d markierten Themen im Forum „%1$s“ würde kein Beitrag gelöscht',
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Themen im Forum „%1$s“ würde gelöscht',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ würden gelöscht',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_NO_TOPICS'	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» Forum „%1$s“: keine markierten Themen, es würde nichts gelöscht',
	'LOG_UFLAGMEY_PP_PRUNED_TOPIC'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Thema im Forum „%1$s“ gelöscht',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ gelöscht',
	],
	'LOG_UFLAGMEY_PP_PRUNED_TOPICS'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Themen im Forum „%1$s“ gelöscht',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ gelöscht',
	],
	'LOG_UFLAGMEY_PP_SCHEMA_MISMATCH'	=> '<strong>Post Prune: Datenbankschema passt nicht zum Cache – bitte Cache leeren</strong>',
	'LOG_UFLAGMEY_PP_PRUNED_NO_TOPICS'	=> '<strong>Beitrags-Pruning</strong><br>» Forum „%1$s“: keine markierten Themen, es wurde nichts gelöscht',
]);
