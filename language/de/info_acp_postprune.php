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
	'LOG_UFLAGMEY_PP_TOPICS_UNMARKED_MOVED'	=> [
		2	=> '<strong>Beitrags-Pruning: Markierungen beim Verschieben entfernt</strong><br>» %1$d Themen von Forum „%2$s“ nach „%3$s“',
	],
	'LOG_UFLAGMEY_PP_TOPICS_UNMARKED_MOVED_MIXED'	=> [
		2	=> '<strong>Beitrags-Pruning: Markierungen beim Verschieben entfernt</strong><br>» %1$d Themen aus %2$d Foren nach „%3$s“',
	],
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
	'LOG_UFLAGMEY_PP_SKIPPED'			=> '<strong>Post Prune: Löschregel unbekannt bzw. Update unvollständig – Forum übersprungen</strong><br>» Forum „%1$s“: Die gespeicherten Einstellungen versteht diese Version nicht, es wurde nichts gelöscht. Einstellungen des Forums prüfen und speichern bzw. das Update abschließen (Erweiterung deaktivieren und wieder aktivieren).',
	'LOG_UFLAGMEY_PP_SQL_ERROR'			=> '<strong>Post Prune: Datenbankfehler – Lauf beendet, nächster Versuch im nächsten Zyklus</strong><br>» %s',
	'LOG_UFLAGMEY_PP_PRUNED_NO_TOPICS'	=> '<strong>Beitrags-Pruning</strong><br>» Forum „%1$s“: keine markierten Themen, es wurde nichts gelöscht',
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_TOPIC'	=> [
		0	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» Im %3$d markierten Thema im Forum „%1$s“ würde kein Beitrag gelöscht (neueste %4$d je Thema behalten)',
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Thema im Forum „%1$s“ würde gelöscht (neueste %4$d je Thema behalten)',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ würden gelöscht (neueste %4$d je Thema behalten)',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_TOPICS'	=> [
		0	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» In den %3$d markierten Themen im Forum „%1$s“ würde kein Beitrag gelöscht (neueste %4$d je Thema behalten)',
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Themen im Forum „%1$s“ würde gelöscht (neueste %4$d je Thema behalten)',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ würden gelöscht (neueste %4$d je Thema behalten)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_TOPIC'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Thema im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten)',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_TOPICS'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Themen im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten)',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten)',
	],
	// soft-deleted posts among them (rc1): same text, their number is the last value
	'LOG_UFLAGMEY_PP_DRYRUN_SOFT'	=> [
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) im Forum „%1$s“ würde gelöscht',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge im Forum „%1$s“ würden gelöscht, davon %3$d soft-gelöscht',
	],
	'LOG_UFLAGMEY_PP_PRUNED_SOFT'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) im Forum „%1$s“ gelöscht',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge im Forum „%1$s“ gelöscht, davon %3$d soft-gelöscht',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_TOPIC_SOFT'	=> [
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Thema im Forum „%1$s“ würde gelöscht',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ würden gelöscht, davon %4$d soft-gelöscht',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_TOPICS_SOFT'	=> [
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Themen im Forum „%1$s“ würde gelöscht',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ würden gelöscht, davon %4$d soft-gelöscht',
	],
	'LOG_UFLAGMEY_PP_PRUNED_TOPIC_SOFT'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Thema im Forum „%1$s“ gelöscht',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ gelöscht, davon %4$d soft-gelöscht',
	],
	'LOG_UFLAGMEY_PP_PRUNED_TOPICS_SOFT'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Themen im Forum „%1$s“ gelöscht',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ gelöscht, davon %4$d soft-gelöscht',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_TOPIC_SOFT'	=> [
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Thema im Forum „%1$s“ würde gelöscht (neueste %4$d je Thema behalten)',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ würden gelöscht, davon %5$d soft-gelöscht (neueste %4$d je Thema behalten)',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_TOPICS_SOFT'	=> [
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Themen im Forum „%1$s“ würde gelöscht (neueste %4$d je Thema behalten)',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ würden gelöscht, davon %5$d soft-gelöscht (neueste %4$d je Thema behalten)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_TOPIC_SOFT'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Thema im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten)',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ gelöscht, davon %5$d soft-gelöscht (neueste %4$d je Thema behalten)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_TOPICS_SOFT'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Themen im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten)',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ gelöscht, davon %5$d soft-gelöscht (neueste %4$d je Thema behalten)',
	],
	// rule "count" with minimum age (rc1): X 4th value, N (days) 5th, soft-deleted 6th; the _KEEP_ keys above stay for older entries
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_AGE_TOPIC'	=> [
		0	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» Im %3$d markierten Thema im Forum „%1$s“ würde kein Beitrag gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Thema im Forum „%1$s“ würde gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ würden gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_AGE_TOPICS'	=> [
		0	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» In den %3$d markierten Themen im Forum „%1$s“ würde kein Beitrag gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Themen im Forum „%1$s“ würde gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ würden gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPIC'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Thema im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPICS'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag in %3$d markierten Themen im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_AGE_TOPIC_SOFT'	=> [
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Thema im Forum „%1$s“ würde gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ würden gelöscht, davon %6$d soft-gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_AGE_TOPICS_SOFT'	=> [
		1	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Themen im Forum „%1$s“ würde gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
		2	=> '<strong>Trockenlauf: Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ würden gelöscht, davon %6$d soft-gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPIC_SOFT'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Thema im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Thema im Forum „%1$s“ gelöscht, davon %6$d soft-gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPICS_SOFT'	=> [
		1	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beitrag (soft-gelöscht) in %3$d markierten Themen im Forum „%1$s“ gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
		2	=> '<strong>Beitrags-Pruning</strong><br>» %2$d Beiträge in %3$d markierten Themen im Forum „%1$s“ gelöscht, davon %6$d soft-gelöscht (neueste %4$d je Thema behalten, ältere nach %5$d Tag(en) löschen)',
	],
]);
