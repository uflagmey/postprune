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
	'UFLAGMEY_PP_SETTINGS'				=> 'Post pruning (individual posts)',
	'UFLAGMEY_PP_SETTINGS_EXPLAIN'		=> 'Automatically and permanently deletes individual posts in this forum, including their attachments, once they are older than the configured number of days. Unlike topic pruning above, this works on single posts.',
	'UFLAGMEY_PP_MODE'					=> 'Post pruning mode',
	'UFLAGMEY_PP_MODE_EXPLAIN'			=> 'Off: nothing happens. Dry run: only writes to the admin log how many posts would be deleted. Active: posts are deleted permanently.',
	'UFLAGMEY_PP_MODE_OFF'				=> 'Off',
	'UFLAGMEY_PP_MODE_DRYRUN'			=> 'Dry run',
	'UFLAGMEY_PP_MODE_ACTIVE'			=> 'Active',
	'UFLAGMEY_PP_DAYS'					=> 'Post age',
	'UFLAGMEY_PP_DAYS_EXPLAIN'			=> 'Posts older than this number of days are deleted.',
	'UFLAGMEY_PP_KEEP_FIRST'			=> 'Keep first post of each topic',
	'UFLAGMEY_PP_KEEP_FIRST_EXPLAIN'	=> 'If enabled, the first post of every topic is kept, so the topic itself stays. If disabled, a topic disappears once all of its posts are old.',
	'UFLAGMEY_PP_SKIP_STICKY'			=> 'Skip stickies and announcements',
	'UFLAGMEY_PP_SKIP_STICKY_EXPLAIN'	=> 'If enabled, posts in stickies, announcements and global announcements are never deleted.',
	'UFLAGMEY_PP_COUNT_SYNC'			=> 'Reduce user post counts',
	'UFLAGMEY_PP_COUNT_SYNC_EXPLAIN'	=> 'If enabled, the post count of each author is reduced for every deleted post. Default is No, like phpBB’s own topic pruning.',
	'UFLAGMEY_PP_ERR_MODE'				=> 'Post pruning: invalid mode.',
	'UFLAGMEY_PP_ERR_DAYS'				=> 'Post pruning: the post age must be between 1 and %d days.',
]);
