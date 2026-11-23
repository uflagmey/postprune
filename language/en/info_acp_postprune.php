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
		0	=> '<strong>Dry run: post pruning</strong><br>» No posts in forum “%1$s” would be deleted',
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post in forum “%1$s” would be deleted',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in forum “%1$s” would be deleted',
	],
	'LOG_UFLAGMEY_PP_PRUNED'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post deleted in forum “%1$s”',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in forum “%1$s”',
	],
	'LOG_UFLAGMEY_PP_TOPIC_MARKED'			=> '<strong>Post pruning: topic marked</strong><br>» “%1$s” in forum “%2$s”',
	'LOG_UFLAGMEY_PP_TOPIC_UNMARKED'		=> '<strong>Post pruning: mark removed</strong><br>» “%1$s” in forum “%2$s”',
	'LOG_UFLAGMEY_PP_TOPIC_UNMARKED_MOVED'	=> '<strong>Post pruning: mark removed on move</strong><br>» “%1$s” from forum “%2$s” to “%3$s”',
	'LOG_UFLAGMEY_PP_TOPICS_UNMARKED_MOVED'	=> [
		2	=> '<strong>Post pruning: marks removed on move</strong><br>» %1$d topics from forum “%2$s” to “%3$s”',
	],
	'LOG_UFLAGMEY_PP_TOPICS_UNMARKED_MOVED_MIXED'	=> [
		2	=> '<strong>Post pruning: marks removed on move</strong><br>» %1$d topics from %2$d forums to “%3$s”',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_TOPIC'	=> [
		0	=> '<strong>Dry run: post pruning</strong><br>» No posts in %3$d marked topic in forum “%1$s” would be deleted',
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post in %3$d marked topic in forum “%1$s” would be deleted',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topic in forum “%1$s” would be deleted',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_TOPICS'	=> [
		0	=> '<strong>Dry run: post pruning</strong><br>» No posts in %3$d marked topics in forum “%1$s” would be deleted',
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post in %3$d marked topics in forum “%1$s” would be deleted',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topics in forum “%1$s” would be deleted',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_NO_TOPICS'	=> '<strong>Dry run: post pruning</strong><br>» Forum “%1$s”: no marked topics, nothing would be deleted',
	'LOG_UFLAGMEY_PP_PRUNED_TOPIC'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post deleted in %3$d marked topic in forum “%1$s”',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topic in forum “%1$s”',
	],
	'LOG_UFLAGMEY_PP_PRUNED_TOPICS'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post deleted in %3$d marked topics in forum “%1$s”',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topics in forum “%1$s”',
	],
	'LOG_UFLAGMEY_PP_SCHEMA_MISMATCH'	=> '<strong>Post Prune: database schema does not match the cache – please purge the cache</strong>',
	'LOG_UFLAGMEY_PP_SKIPPED'			=> '<strong>Post Prune: deletion rule unknown or update incomplete – forum skipped</strong><br>» Forum “%1$s”: this version does not understand the stored settings, nothing was deleted. Check and save the forum settings, or finish the update (disable and re-enable the extension).',
	'LOG_UFLAGMEY_PP_SQL_ERROR'			=> '<strong>Post Prune: database error – run ended, next attempt in the next cycle</strong><br>» %s',
	'LOG_UFLAGMEY_PP_DELETE_ERROR'		=> '<strong>Post Prune: database error while deleting – run ended, next attempt in the next cycle</strong><br>» Forum “%1$s”, candidates of this run: %2$d. Part of them may already be deleted at this point; if so, resynchronise the forum’s counters (ACP → Manage forums → “Resynchronise”).<br>» %3$s',
	'LOG_UFLAGMEY_PP_PRUNED_NO_TOPICS'	=> '<strong>Post pruning</strong><br>» Forum “%1$s”: no marked topics, nothing deleted',
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_TOPIC'	=> [
		0	=> '<strong>Dry run: post pruning</strong><br>» No posts in %3$d marked topic in forum “%1$s” would be deleted (keeping the newest %4$d per topic)',
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post in %3$d marked topic in forum “%1$s” would be deleted (keeping the newest %4$d per topic)',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topic in forum “%1$s” would be deleted (keeping the newest %4$d per topic)',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_TOPICS'	=> [
		0	=> '<strong>Dry run: post pruning</strong><br>» No posts in %3$d marked topics in forum “%1$s” would be deleted (keeping the newest %4$d per topic)',
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post in %3$d marked topics in forum “%1$s” would be deleted (keeping the newest %4$d per topic)',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topics in forum “%1$s” would be deleted (keeping the newest %4$d per topic)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_TOPIC'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post deleted in %3$d marked topic in forum “%1$s” (keeping the newest %4$d per topic)',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topic in forum “%1$s” (keeping the newest %4$d per topic)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_TOPICS'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post deleted in %3$d marked topics in forum “%1$s” (keeping the newest %4$d per topic)',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topics in forum “%1$s” (keeping the newest %4$d per topic)',
	],
	// soft-deleted posts among them (rc1): same text, their number is the last value
	'LOG_UFLAGMEY_PP_DRYRUN_SOFT'	=> [
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post (soft-deleted) in forum “%1$s” would be deleted',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in forum “%1$s” would be deleted, %3$d of them soft-deleted',
	],
	'LOG_UFLAGMEY_PP_PRUNED_SOFT'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post (soft-deleted) deleted in forum “%1$s”',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in forum “%1$s”, %3$d of them soft-deleted',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_TOPIC_SOFT'	=> [
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post (soft-deleted) in %3$d marked topic in forum “%1$s” would be deleted',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topic in forum “%1$s” would be deleted, %4$d of them soft-deleted',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_TOPICS_SOFT'	=> [
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post (soft-deleted) in %3$d marked topics in forum “%1$s” would be deleted',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topics in forum “%1$s” would be deleted, %4$d of them soft-deleted',
	],
	'LOG_UFLAGMEY_PP_PRUNED_TOPIC_SOFT'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post (soft-deleted) deleted in %3$d marked topic in forum “%1$s”',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topic in forum “%1$s”, %4$d of them soft-deleted',
	],
	'LOG_UFLAGMEY_PP_PRUNED_TOPICS_SOFT'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post (soft-deleted) deleted in %3$d marked topics in forum “%1$s”',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topics in forum “%1$s”, %4$d of them soft-deleted',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_TOPIC_SOFT'	=> [
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post (soft-deleted) in %3$d marked topic in forum “%1$s” would be deleted (keeping the newest %4$d per topic)',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topic in forum “%1$s” would be deleted, %5$d of them soft-deleted (keeping the newest %4$d per topic)',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_TOPICS_SOFT'	=> [
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post (soft-deleted) in %3$d marked topics in forum “%1$s” would be deleted (keeping the newest %4$d per topic)',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topics in forum “%1$s” would be deleted, %5$d of them soft-deleted (keeping the newest %4$d per topic)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_TOPIC_SOFT'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post (soft-deleted) deleted in %3$d marked topic in forum “%1$s” (keeping the newest %4$d per topic)',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topic in forum “%1$s”, %5$d of them soft-deleted (keeping the newest %4$d per topic)',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_TOPICS_SOFT'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post (soft-deleted) deleted in %3$d marked topics in forum “%1$s” (keeping the newest %4$d per topic)',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topics in forum “%1$s”, %5$d of them soft-deleted (keeping the newest %4$d per topic)',
	],
	// rule "count" with minimum age (rc1): X 4th value, N (days) 5th, soft-deleted 6th; the _KEEP_ keys above stay for older entries
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_AGE_TOPIC'	=> [
		0	=> '<strong>Dry run: post pruning</strong><br>» No posts in %3$d marked topic in forum “%1$s” would be deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post in %3$d marked topic in forum “%1$s” would be deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topic in forum “%1$s” would be deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_AGE_TOPICS'	=> [
		0	=> '<strong>Dry run: post pruning</strong><br>» No posts in %3$d marked topics in forum “%1$s” would be deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post in %3$d marked topics in forum “%1$s” would be deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topics in forum “%1$s” would be deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPIC'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post deleted in %3$d marked topic in forum “%1$s” (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topic in forum “%1$s” (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPICS'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post deleted in %3$d marked topics in forum “%1$s” (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topics in forum “%1$s” (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_AGE_TOPIC_SOFT'	=> [
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post (soft-deleted) in %3$d marked topic in forum “%1$s” would be deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topic in forum “%1$s” would be deleted, %6$d of them soft-deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
	],
	'LOG_UFLAGMEY_PP_DRYRUN_KEEP_AGE_TOPICS_SOFT'	=> [
		1	=> '<strong>Dry run: post pruning</strong><br>» %2$d post (soft-deleted) in %3$d marked topics in forum “%1$s” would be deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
		2	=> '<strong>Dry run: post pruning</strong><br>» %2$d posts in %3$d marked topics in forum “%1$s” would be deleted, %6$d of them soft-deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPIC_SOFT'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post (soft-deleted) deleted in %3$d marked topic in forum “%1$s” (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topic in forum “%1$s”, %6$d of them soft-deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
	],
	'LOG_UFLAGMEY_PP_PRUNED_KEEP_AGE_TOPICS_SOFT'	=> [
		1	=> '<strong>Post pruning</strong><br>» %2$d post (soft-deleted) deleted in %3$d marked topics in forum “%1$s” (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
		2	=> '<strong>Post pruning</strong><br>» %2$d posts deleted in %3$d marked topics in forum “%1$s”, %6$d of them soft-deleted (keeping the newest %4$d per topic, deleting older ones after %5$d day(s))',
	],
]);
