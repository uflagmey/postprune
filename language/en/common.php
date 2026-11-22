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
	'UFLAGMEY_PP_SETTINGS_EXPLAIN'		=> 'Automatically and permanently deletes individual posts in this forum, including their attachments, according to the configured deletion rule (by age or by number). Unlike topic pruning above, this works on single posts. Posts waiting for approval are never deleted. Soft-deleted posts are deleted too and named separately in the preview and the log.',
	'UFLAGMEY_PP_MODE'					=> 'Post pruning mode',
	'UFLAGMEY_PP_MODE_EXPLAIN'			=> 'Off: nothing happens. Dry run: only writes to the admin log how many posts would be deleted. Active: posts are deleted permanently.',
	'UFLAGMEY_PP_MODE_OFF'				=> 'Off',
	'UFLAGMEY_PP_MODE_DRYRUN'			=> 'Dry run',
	'UFLAGMEY_PP_MODE_ACTIVE'			=> 'Active',
	'UFLAGMEY_PP_DAYS'					=> 'Post age',
	'UFLAGMEY_PP_DAYS_EXPLAIN'			=> 'Posts older than this number of days are deleted.',
	'UFLAGMEY_PP_DAYS_EXPLAIN_COUNT'	=> 'With “By number” this is the minimum age: of the posts outside the newest “Number to keep”, only those older than this number of days are deleted. Many new replies at once therefore delete nothing younger.',
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
	'UFLAGMEY_PP_MARKED_TOPIC_ID'		=> 'Topic %d (no read permission for this forum)',
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
	'UFLAGMEY_PP_RULE'					=> 'Deletion rule',
	'UFLAGMEY_PP_RULE_EXPLAIN'			=> 'By age: posts older than the configured number of days are deleted. By number: per topic the newest approved posts are kept, older ones are deleted once they are older than “Post age”. “By number” requires the scope “Marked topics only”.',
	'UFLAGMEY_PP_RULE_AGE'				=> 'By age',
	'UFLAGMEY_PP_RULE_COUNT'			=> 'By number',
	'UFLAGMEY_PP_KEEP_COUNT'			=> 'Number to keep',
	'UFLAGMEY_PP_KEEP_COUNT_EXPLAIN'	=> 'This many of the newest approved posts are kept per topic. Older ones are only deleted once they are older than “Post age”. With “Keep first post” the first post stays in addition.',
	'UFLAGMEY_PP_ONLY_RULE_COUNT'		=> 'Only applies with the deletion rule “By number”.',
	'UFLAGMEY_PP_ERR_RULE'				=> 'Post pruning: invalid deletion rule.',
	'UFLAGMEY_PP_ERR_RULE_SCOPE'		=> 'Post pruning: the deletion rule “By number” requires the scope “Marked topics only”.',
	'UFLAGMEY_PP_ERR_KEEP_COUNT'		=> 'Post pruning: the number of posts to keep must be between %1$d and %2$d.',
	'UFLAGMEY_PP_ERR_KEEP_FIRST'		=> 'Post pruning: invalid value for “Keep first post of each topic”.',
	'UFLAGMEY_PP_ERR_SKIP_STICKY'		=> 'Post pruning: invalid value for “Skip stickies and announcements”.',
	'UFLAGMEY_PP_ERR_COUNT_SYNC'		=> 'Post pruning: invalid value for “Reduce user post counts”.',
	'UFLAGMEY_PP_NOTICE_COUNT'			=> [
		1	=> 'The newest post in this topic is kept.',
		2	=> 'The newest %d posts in this topic are kept.',
	],
	'UFLAGMEY_PP_NOTICE_COUNT_KEEP_FIRST'	=> [
		1	=> 'The newest post in this topic is kept, as well as the first post.',
		2	=> 'The newest %d posts in this topic are kept, as well as the first post.',
	],
	'UFLAGMEY_PP_NOTICE_COUNT_DRYRUN'	=> [
		1	=> 'Dry run active: the newest post in this topic would be kept.',
		2	=> 'Dry run active: the newest %d posts in this topic would be kept.',
	],
	'UFLAGMEY_PP_NOTICE_COUNT_KEEP_FIRST_DRYRUN'	=> [
		1	=> 'Dry run active: the newest post in this topic would be kept, as well as the first post.',
		2	=> 'Dry run active: the newest %d posts in this topic would be kept, as well as the first post.',
	],
	'UFLAGMEY_PP_NOTICE_COUNT_AGE'		=> [
		1	=> 'Older posts are deleted once they are older than %d day.',
		2	=> 'Older posts are deleted once they are older than %d days.',
	],
	'UFLAGMEY_PP_NOTICE_COUNT_AGE_DRYRUN'	=> [
		1	=> 'Older posts would be deleted once they are older than %d day. Only visible to administrators.',
		2	=> 'Older posts would be deleted once they are older than %d days. Only visible to administrators.',
	],
	'UFLAGMEY_PP_PREVIEW'				=> 'Preview',
	'UFLAGMEY_PP_PREVIEW_EXPLAIN'		=> 'Calculates right away what a dry run would delete, with the values in the form, even if they are not saved yet, in every mode, also “Off”. Nothing is deleted and nothing is logged. Only saved values are used for deleting, by the cron. Without JavaScript the saved settings are used.',
	'UFLAGMEY_PP_PREVIEW_BUTTON'		=> 'Calculate dry run now',
	'UFLAGMEY_PP_PREVIEW_RESULT'		=> [
		0	=> '→ No posts would be deleted.',
		1	=> '→ <strong>%2$s post</strong> would be deleted (date: %3$s)',
		2	=> '→ <strong>%2$s posts</strong> would be deleted (oldest: %3$s, newest to be deleted: %4$s)',
	],
	'UFLAGMEY_PP_PREVIEW_UNSAVED'		=> '%s – <strong>with the values in the form, not saved yet</strong><br>Only saved values are used for deleting. “Submit” saves them.',
	'UFLAGMEY_PP_PREVIEW_RESULT_SOFT'	=> [
		1	=> '→ <strong>%2$s post</strong> (soft-deleted) would be deleted (date: %3$s)',
		2	=> '→ <strong>%2$s posts</strong> would be deleted, %5$s of them soft-deleted (oldest: %3$s, newest to be deleted: %4$s)',
	],
	'UFLAGMEY_PP_PREVIEW_NOT_POST_FORUM'	=> '→ Not a post forum, post pruning does not process it.',
	'UFLAGMEY_PP_PREVIEW_DOUBT'			=> '→ Stored setting unknown or update incomplete: the cron skips this forum and deletes nothing. Check the settings and save them with “Submit”, or finish the update.',
	'UFLAGMEY_PP_SAVED_DOUBT'			=> '<strong>setting unknown – forum is skipped</strong>',
	'UFLAGMEY_PP_PREVIEW_FAILED'		=> 'The preview could not be calculated (database error).',
	'UFLAGMEY_PP_THOUSANDS_SEP'			=> ',',
	'UFLAGMEY_PP_SAVED_MARKED'			=> 'Marked topics only (%d)',
	'UFLAGMEY_PP_SAVED_DAYS'			=> [
		1	=> '%d day',
		2	=> '%d days',
	],
	'UFLAGMEY_PP_SAVED_KEEP_COUNT'		=> [
		1	=> 'keep only the newest post per topic',
		2	=> 'keep the newest %d per topic',
	],
	'UFLAGMEY_PP_SAVED_KEEP_AGE'		=> [
		1	=> 'delete older ones after %d day',
		2	=> 'delete older ones after %d days',
	],
	'UFLAGMEY_PP_PREVIEW_RULE'			=> 'Counted with: %s',
	'UFLAGMEY_PP_SAVED_KEEP_FIRST'		=> 'keep first post',
	'UFLAGMEY_PP_SAVED_KEEP_FIRST_NO'	=> 'first post not kept',
	'UFLAGMEY_PP_SAVED_SKIP_STICKY'		=> 'without stickies and announcements',
	'UFLAGMEY_PP_SAVED_SKIP_STICKY_NO'	=> 'including stickies and announcements',
	'UFLAGMEY_PP_SAVED_COUNT_SYNC'		=> 'reduce post counts',
	'UFLAGMEY_PP_SAVE_HINT'				=> 'Changes are only saved with “Submit” at the end of the form.',
	'UFLAGMEY_PP_SAVED_FOR'				=> 'Saved for “%s”',
	'UFLAGMEY_PP_SAVED_EXPLAIN'			=> 'The settings currently saved. If the fields below differ, those changes are not saved yet.',
]);
