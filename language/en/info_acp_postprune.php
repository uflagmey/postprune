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
		1	=> '<strong>Post pruning dry run</strong><br />» %2$d post in forum “%1$s” would be deleted',
		2	=> '<strong>Post pruning dry run</strong><br />» %2$d posts in forum “%1$s” would be deleted',
	],
	'LOG_UFLAGMEY_PP_PRUNED'	=> [
		1	=> '<strong>Post pruning</strong><br />» %2$d post deleted in forum “%1$s”',
		2	=> '<strong>Post pruning</strong><br />» %2$d posts deleted in forum “%1$s”',
	],
]);
