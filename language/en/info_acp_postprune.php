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
]);
