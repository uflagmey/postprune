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
	'UFLAGMEY_PP_UPDATE_PENDING'		=> 'The Post Prune update is not complete yet. Please disable the extension once and enable it again (ACP » Customise » Manage extensions) to complete the update. Your settings are kept. Until then the extension works as in the previous version.',
	'UFLAGMEY_PP_SCOPE'				=> 'Scope',
	'UFLAGMEY_PP_SCOPE_EXPLAIN'		=> 'Whole forum: all topics of this forum are pruned. Marked topics only: only topics an administrator has marked in the topic view via the moderator tools, e.g. a collection topic. Marked topics are pruned even if they are stickies or announcements. “Skip stickies and announcements” only applies to “Whole forum”.',
	'UFLAGMEY_PP_SCOPE_FORUM'			=> 'Whole forum',
	'UFLAGMEY_PP_SCOPE_MARKED'			=> 'Marked topics only',
	'UFLAGMEY_PP_MARKED_TOPICS'		=> 'Marked topics',
	'UFLAGMEY_PP_MARKED_TOPICS_EXPLAIN'	=> 'Topics of this forum that are marked for automatic pruning.',
	'UFLAGMEY_PP_MARKED_NONE'			=> 'No topics marked. Mark them in the topic view via the moderator tools.',
	'UFLAGMEY_PP_MARKED_INACTIVE'		=> [
		1 => '%d topic is marked in this forum. The mark only takes effect with the scope “Marked topics only”.',
		2 => '%d topics are marked in this forum. The marks only take effect with the scope “Marked topics only”.',
	],
	'UFLAGMEY_PP_ERR_SCOPE'			=> 'Post pruning: invalid scope.',
	'UFLAGMEY_PP_QM_MARK'				=> 'Prune automatically: switch on',
	'UFLAGMEY_PP_QM_UNMARK'				=> 'Prune automatically: switch off',
	'UFLAGMEY_PP_QM_MARK_CONFIRM'		=> 'Do you want to mark the topic “%s” for automatic pruning? Its old posts will then be deleted permanently once the forum uses the scope “Marked topics only” and post pruning is active.',
	'UFLAGMEY_PP_QM_UNMARK_CONFIRM'		=> 'Do you want to remove the automatic pruning mark from the topic “%s”?',
	'UFLAGMEY_PP_QM_MARKED'				=> 'The topic is now marked for automatic pruning.',
	'UFLAGMEY_PP_QM_UNMARKED'			=> 'The automatic pruning mark has been removed.',
	'UFLAGMEY_PP_QM_ALREADY_MARKED'		=> 'The topic is already marked for automatic pruning.',
	'UFLAGMEY_PP_QM_NOT_MARKED'			=> 'The topic is not marked for automatic pruning.',
	'UFLAGMEY_PP_QM_NOT_POSSIBLE'		=> 'This topic cannot be marked for automatic pruning.',
	'UFLAGMEY_PP_NOTICE'				=> [
		1	=> 'Posts in this topic older than %d day are deleted automatically.',
		2	=> 'Posts in this topic older than %d days are deleted automatically.',
	],
	'UFLAGMEY_PP_NOTICE_KEEP_FIRST'		=> 'The first post is kept.',
	'UFLAGMEY_PP_NOTICE_DRYRUN'			=> [
		1	=> 'Dry run active: posts in this topic older than %d day would be deleted. Only visible to administrators.',
		2	=> 'Dry run active: posts in this topic older than %d days would be deleted. Only visible to administrators.',
	],
	'UFLAGMEY_PP_NOTICE_KEEP_FIRST_DRYRUN'	=> 'The first post would be kept.',
]);
