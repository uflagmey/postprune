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
	'UFLAGMEY_PP_SETTINGS'				=> 'Beitrags-Pruning (einzelne Beiträge)',
	'UFLAGMEY_PP_SETTINGS_EXPLAIN'		=> 'Löscht in diesem Forum einzelne Beiträge samt Dateianhängen automatisch und endgültig, sobald sie älter als die eingestellte Anzahl Tage sind. Anders als das Themen-Pruning oben arbeitet dies auf Beitragsebene.',
	'UFLAGMEY_PP_MODE'					=> 'Modus Beitrags-Pruning',
	'UFLAGMEY_PP_MODE_EXPLAIN'			=> 'Aus: Es passiert nichts. Trockenlauf: Es wird nur im Administrations-Protokoll vermerkt, wie viele Beiträge gelöscht würden. Aktiv: Beiträge werden endgültig gelöscht.',
	'UFLAGMEY_PP_MODE_OFF'				=> 'Aus',
	'UFLAGMEY_PP_MODE_DRYRUN'			=> 'Trockenlauf',
	'UFLAGMEY_PP_MODE_ACTIVE'			=> 'Aktiv',
	'UFLAGMEY_PP_DAYS'					=> 'Alter der Beiträge',
	'UFLAGMEY_PP_DAYS_EXPLAIN'			=> 'Beiträge, die älter als diese Anzahl Tage sind, werden gelöscht.',
	'UFLAGMEY_PP_KEEP_FIRST'			=> 'Ersten Beitrag eines Themas behalten',
	'UFLAGMEY_PP_KEEP_FIRST_EXPLAIN'	=> 'Wenn aktiviert, bleibt der erste Beitrag jedes Themas erhalten und damit das Thema selbst. Wenn deaktiviert, verschwindet ein Thema, sobald alle seine Beiträge alt sind.',
	'UFLAGMEY_PP_SKIP_STICKY'			=> 'Wichtige Themen und Bekanntmachungen ausnehmen',
	'UFLAGMEY_PP_SKIP_STICKY_EXPLAIN'	=> 'Wenn aktiviert, werden Beiträge in wichtigen Themen, Bekanntmachungen und globalen Bekanntmachungen nie gelöscht.',
	'UFLAGMEY_PP_COUNT_SYNC'			=> 'Beitragszähler der Benutzer reduzieren',
	'UFLAGMEY_PP_COUNT_SYNC_EXPLAIN'	=> 'Wenn aktiviert, wird der Beitragszähler des Verfassers für jeden gelöschten Beitrag reduziert. Standard ist Nein, wie beim Themen-Pruning von phpBB.',
	'UFLAGMEY_PP_ERR_MODE'				=> 'Beitrags-Pruning: Ungültiger Modus.',
	'UFLAGMEY_PP_ERR_DAYS'				=> 'Beitrags-Pruning: Das Alter der Beiträge muss zwischen 1 und %d Tagen liegen.',
	'UFLAGMEY_PP_UPDATE_PENDING'		=> 'Das Update von Post Prune ist noch nicht abgeschlossen. Bitte die Erweiterung einmal deaktivieren und wieder aktivieren (ACP » Anpassen » Erweiterungen verwalten), um das Update abzuschließen. Die Einstellungen bleiben dabei erhalten. Bis dahin arbeitet die Erweiterung wie in der Vorversion.',
	'UFLAGMEY_PP_SCOPE'				=> 'Geltungsbereich',
	'UFLAGMEY_PP_SCOPE_EXPLAIN'		=> 'Ganzes Forum: Alle Themen dieses Forums werden gekürzt. Nur markierte Themen: Nur Themen, die ein Administrator in der Themenansicht über die Moderations-Werkzeuge markiert hat, z. B. ein Sammelthema. Markierte Themen werden dabei auch dann gekürzt, wenn sie wichtig oder Bekanntmachungen sind. „Wichtige Themen und Bekanntmachungen ausnehmen“ gilt nur für „Ganzes Forum“.',
	'UFLAGMEY_PP_SCOPE_FORUM'			=> 'Ganzes Forum',
	'UFLAGMEY_PP_SCOPE_MARKED'			=> 'Nur markierte Themen',
	'UFLAGMEY_PP_MARKED_TOPICS'		=> 'Markierte Themen',
	'UFLAGMEY_PP_MARKED_TOPICS_EXPLAIN'	=> 'Themen dieses Forums, die zum automatischen Kürzen markiert sind.',
	'UFLAGMEY_PP_MARKED_NONE'			=> 'Keine Themen markiert. Markieren in der Themenansicht über die Moderations-Werkzeuge.',
	'UFLAGMEY_PP_MARKED_INACTIVE'		=> [
		1 => 'In diesem Forum ist %d Thema markiert. Die Markierung wirkt erst, wenn der Geltungsbereich „Nur markierte Themen“ eingestellt ist.',
		2 => 'In diesem Forum sind %d Themen markiert. Die Markierungen wirken erst, wenn der Geltungsbereich „Nur markierte Themen“ eingestellt ist.',
	],
	'UFLAGMEY_PP_ERR_SCOPE'			=> 'Beitrags-Pruning: Ungültiger Geltungsbereich.',
	'UFLAGMEY_PP_QM_MARK'				=> 'Automatisch kürzen: einschalten',
	'UFLAGMEY_PP_QM_UNMARK'				=> 'Automatisch kürzen: ausschalten',
	'UFLAGMEY_PP_QM_MARK_CONFIRM'		=> 'Willst du das Thema „%s“ zum automatischen Kürzen markieren? Alte Beiträge darin werden dann endgültig gelöscht, sobald im Forum der Geltungsbereich „Nur markierte Themen“ eingestellt und das Beitrags-Pruning aktiv ist.',
	'UFLAGMEY_PP_QM_UNMARK_CONFIRM'		=> 'Willst du beim Thema „%s“ die Markierung zum automatischen Kürzen entfernen?',
	'UFLAGMEY_PP_QM_MARKED'				=> 'Das Thema ist jetzt zum automatischen Kürzen markiert.',
	'UFLAGMEY_PP_QM_UNMARKED'			=> 'Die Markierung zum automatischen Kürzen wurde entfernt.',
	'UFLAGMEY_PP_QM_ALREADY_MARKED'		=> 'Das Thema ist bereits zum automatischen Kürzen markiert.',
	'UFLAGMEY_PP_QM_NOT_MARKED'			=> 'Das Thema ist nicht zum automatischen Kürzen markiert.',
	'UFLAGMEY_PP_QM_NOT_POSSIBLE'		=> 'Dieses Thema kann nicht zum automatischen Kürzen markiert werden.',
	'UFLAGMEY_PP_NOTICE'				=> [
		1	=> 'Beiträge in diesem Thema, die älter als %d Tag sind, werden automatisch gelöscht.',
		2	=> 'Beiträge in diesem Thema, die älter als %d Tage sind, werden automatisch gelöscht.',
	],
	'UFLAGMEY_PP_NOTICE_KEEP_FIRST'		=> 'Der erste Beitrag bleibt erhalten.',
	'UFLAGMEY_PP_NOTICE_DRYRUN'			=> [
		1	=> 'Trockenlauf aktiv: Beiträge in diesem Thema, die älter als %d Tag sind, würden gelöscht. Nur für Administratoren sichtbar.',
		2	=> 'Trockenlauf aktiv: Beiträge in diesem Thema, die älter als %d Tage sind, würden gelöscht. Nur für Administratoren sichtbar.',
	],
	'UFLAGMEY_PP_NOTICE_KEEP_FIRST_DRYRUN'	=> 'Der erste Beitrag bliebe erhalten.',
]);
