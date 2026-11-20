<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune\tests\event;

use uflagmey\postprune\event\listener;
use uflagmey\postprune\prune\pruner;
use uflagmey\postprune\tests\prune\fixtures;

/**
 * ACP preview link (beta5): own GET action of acp_forums with a link hash, only for a_forum,
 * only with schema marker 3; the result names the STORED settings. (The AJAX answer ends the
 * request with exit and is covered by gate P13.)
 */
class preview_listener_test extends \PHPUnit\Framework\TestCase
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var array */
	protected $logs = [];

	/** @var array format_date() arguments after the time */
	protected $date_calls = [];

	/**
	 * Forum 3: off, marked topics only, keep the newest 2; topic 10 (marked) with 5 posts.
	 *
	 * @param array	$get		GET values
	 * @param bool	$a_forum
	 * @param int	$schema
	 * @param array	$post		POST values
	 * @return listener
	 */
	protected function listener(array $get, $a_forum = true, $schema = 3, array $post = [])
	{
		$this->db = fixtures::db();
		fixtures::forum($this->db, 3, 'A', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 1, 'uflagmey_pp_keep_count' => 2]);
		fixtures::topic($this->db, 3, 10, [101 => 5, 102 => 4, 103 => 3, 104 => 2, 105 => 1], POST_NORMAL, ['uflagmey_pp_marked' => 1]);
		$config = new \phpbb\config\config(['uflagmey_pp_schema' => $schema]);

		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('is_set')->willReturnCallback(function ($name, $type = \phpbb\request\request_interface::REQUEST) use ($get, $post) {
			return ($type !== \phpbb\request\request_interface::POST && array_key_exists($name, $get))
				|| ($type !== \phpbb\request\request_interface::GET && array_key_exists($name, $post));
		});
		$request->method('is_set_post')->willReturnCallback(function ($name) use ($post) {
			return array_key_exists($name, $post);
		});
		// like phpBB: POST over GET, cast to the type of the default
		$request->method('variable')->willReturnCallback(function ($name, $default) use ($get, $post) {
			$all = $post + $get;
			if (!array_key_exists($name, $all))
			{
				return $default;
			}
			$value = $all[$name];
			settype($value, gettype($default));
			return $value;
		});
		$request->method('is_ajax')->willReturn(false);

		$language = $this->createMock(\phpbb\language\language::class);
		$language->method('lang')->willReturnCallback(function () {
			return implode('|', func_get_args());
		});
		$auth = $this->createMock(\phpbb\auth\auth::class);
		$auth->method('acl_get')->willReturnCallback(function ($option) use ($a_forum) {
			return $option === 'a_forum' && $a_forum;
		});
		$user = $this->createMock(\phpbb\user::class);
		$user->method('format_date')->willReturnCallback(function ($time, $format = false, $forcedate = false) {
			$this->date_calls[] = [$format, $forcedate];
			return gmdate('Y-m-d', $time);
		});
		$log = $this->createMock(\phpbb\log\log_interface::class);
		$log->method('add')->willReturnCallback(function () {
			$this->logs[] = func_get_args();
			return 1;
		});
		$pruner = new pruner($this->db, $config, $log, $user, fixtures::query_builder(), './', 'php');

		return new listener($language, $request, $config, $this->db, fixtures::query_builder(), './../', 'php', $auth, $user, $pruner, 'adm/');
	}

	/**
	 * initialise_data + display_form of acp_forums for "edit forum 3" (GET).
	 *
	 * @return array template_data
	 */
	protected function edit_form(listener $listener, $forum_id = 3)
	{
		$event = new \phpbb\event\data(['action' => 'edit', 'update' => false, 'forum_id' => $forum_id, 'forum_data' => ['forum_id' => $forum_id]]);
		$listener->initialise_data($event);
		$event = new \phpbb\event\data(['action' => 'edit', 'forum_id' => $forum_id, 'forum_data' => $event['forum_data'], 'template_data' => []]);
		$listener->display_form($event);

		return $event['template_data'];
	}

	/**
	 * Runs $fn and returns the message of an E_USER_WARNING raised by it ('' if none).
	 */
	protected function warning(callable $fn)
	{
		$message = '';
		set_error_handler(function ($errno, $errstr) use (&$message) {
			$message = $errstr;
			throw new \RuntimeException('rejected');
		}, E_USER_WARNING);
		try
		{
			$fn();
		}
		catch (\RuntimeException $e)
		{
		}
		finally
		{
			restore_error_handler();
		}

		return $message;
	}

	public function test_edit_form_has_the_preview_link_with_a_hash_for_this_forum()
	{
		$data = $this->edit_form($this->listener([]));

		$this->assertStringStartsWith('./../adm/index.php?i=acp_forums&amp;mode=manage&amp;action=edit&amp;f=3&amp;uflagmey_pp_preview=1&amp;hash=', $data['U_UFLAGMEY_PP_PREVIEW']);
		$this->assertStringEndsWith('hash=' . generate_link_hash('uflagmey_pp_preview.3'), $data['U_UFLAGMEY_PP_PREVIEW']);
		$this->assertSame('', $data['UFLAGMEY_PP_PREVIEW_RESULT'], 'no result before the click');
	}

	public function test_valid_request_shows_the_count_of_the_stored_settings()
	{
		$data = $this->edit_form($this->listener(['uflagmey_pp_preview' => 1, 'hash' => generate_link_hash('uflagmey_pp_preview.3'), 'uflagmey_pp_keep_count' => 1]));

		$day = function ($days) {
			return gmdate('Y-m-d', fixtures::NOW - $days * fixtures::DAY);
		};
		$this->assertSame('UFLAGMEY_PP_PREVIEW_RESULT|2|2|' . $day(4) . '|' . $day(3), $data['UFLAGMEY_PP_PREVIEW_RESULT'],
			'posts 102 and 103 (keep the newest 2 besides the start post); the GET value keep_count=1 does not count');
		$this->assertSame([[false, true], [false, true]], $this->date_calls, 'board/user date format with time, never "Today"');
		$this->assertSame([], $this->logs, 'no log entry');
		$this->assertCount(5, fixtures::post_ids($this->db));
	}

	public function test_wrong_hash_or_hash_of_another_forum_is_rejected()
	{
		foreach (['deadbeef', generate_link_hash('uflagmey_pp_preview.4'), ''] as $hash)
		{
			$listener = $this->listener(['uflagmey_pp_preview' => 1, 'hash' => $hash]);
			$this->assertSame('FORM_INVALID', $this->warning(function () use ($listener) {
				$this->edit_form($listener);
			}), "hash '$hash'");
		}
	}

	public function test_without_a_forum_rejected_even_with_a_valid_hash()
	{
		$listener = $this->listener(['uflagmey_pp_preview' => 1, 'hash' => generate_link_hash('uflagmey_pp_preview.3')], false);
		$this->assertSame('FORM_INVALID', $this->warning(function () use ($listener) {
			$this->edit_form($listener);
		}));
	}

	public function test_without_schema_marker_3_no_link_and_rejected()
	{
		$data = $this->edit_form($this->listener([], true, 2));
		$this->assertArrayNotHasKey('U_UFLAGMEY_PP_PREVIEW', $data);
		$this->assertTrue($data['S_UFLAGMEY_PP_UPDATE_PENDING']);

		$listener = $this->listener(['uflagmey_pp_preview' => 1, 'hash' => generate_link_hash('uflagmey_pp_preview.3')], true, 2);
		$this->assertSame('FORM_INVALID', $this->warning(function () use ($listener) {
			$this->edit_form($listener);
		}));
	}

	public function test_outdated_container_has_no_preview()
	{
		$language = $this->createMock(\phpbb\language\language::class);
		$request = $this->createMock(\phpbb\request\request_interface::class);
		$listener = new listener($language, $request, new \phpbb\config\config(['uflagmey_pp_schema' => 3]), fixtures::db(), fixtures::query_builder());
		$event = new \phpbb\event\data(['action' => 'edit', 'forum_id' => 3, 'forum_data' => ['forum_id' => 3], 'template_data' => []]);
		$listener->display_form($event);

		$this->assertArrayNotHasKey('U_UFLAGMEY_PP_PREVIEW', $event['template_data']);
	}

	public function test_saved_line_shows_the_database_not_the_form()
	{
		$listener = $this->listener([]);
		// as after a validation error: core redisplays the posted values
		$posted = ['forum_id' => 3, 'uflagmey_pp_mode' => 2, 'uflagmey_pp_scope' => 0, 'uflagmey_pp_rule' => 0, 'uflagmey_pp_days' => 7, 'uflagmey_pp_keep_count' => 9];
		$event = new \phpbb\event\data(['action' => 'edit', 'forum_id' => 3, 'forum_data' => $posted, 'template_data' => []]);
		$listener->display_form($event);

		$this->assertSame('UFLAGMEY_PP_MODE_OFF · UFLAGMEY_PP_SAVED_MARKED|1 · UFLAGMEY_PP_SAVED_KEEP_COUNT|2 · UFLAGMEY_PP_SAVED_KEEP_FIRST', $event['template_data']['UFLAGMEY_PP_SAVED']);
		$this->assertSame('UFLAGMEY_PP_SAVED_FOR|A', $event['template_data']['UFLAGMEY_PP_SAVED_FOR'], 'label with the forum name');
		$this->assertSame(2, $event['template_data']['UFLAGMEY_PP_MODE'], 'the form itself shows the posted value');
		$this->assertSame(9, $event['template_data']['UFLAGMEY_PP_KEEP_COUNT']);
	}

	public function test_saved_line_scope_forum_age_rule_with_all_switches()
	{
		$listener = $this->listener([]);
		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_mode = 1, uflagmey_pp_scope = 0, uflagmey_pp_rule = 0, uflagmey_pp_days = 1, uflagmey_pp_keep_first = 0, uflagmey_pp_skip_sticky = 0, uflagmey_pp_count_sync = 1');
		$data = $this->edit_form($listener);

		$this->assertSame('UFLAGMEY_PP_MODE_DRYRUN · UFLAGMEY_PP_SCOPE_FORUM · UFLAGMEY_PP_SAVED_DAYS|1 · UFLAGMEY_PP_SAVED_KEEP_FIRST_NO · UFLAGMEY_PP_SAVED_SKIP_STICKY_NO · UFLAGMEY_PP_SAVED_COUNT_SYNC', $data['UFLAGMEY_PP_SAVED']);
	}

	public function test_no_saved_line_for_a_new_forum_or_without_schema_marker_3()
	{
		$listener = $this->listener([]);
		$event = new \phpbb\event\data(['action' => 'add', 'forum_id' => 0, 'forum_data' => [], 'template_data' => []]);
		$listener->display_form($event);
		$this->assertArrayNotHasKey('UFLAGMEY_PP_SAVED', $event['template_data']);
		$this->assertArrayNotHasKey('UFLAGMEY_PP_SAVED_FOR', $event['template_data']);

		$data = $this->edit_form($this->listener([], true, 2));
		$this->assertArrayNotHasKey('UFLAGMEY_PP_SAVED', $data);
	}

	/**
	 * Preview with the values in the form (POST, as the JavaScript of the button sends it).
	 */
	protected function form_preview(array $post)
	{
		$hash = generate_link_hash('uflagmey_pp_preview.3');
		$data = $this->edit_form($this->listener(['uflagmey_pp_preview' => 1, 'hash' => $hash], true, 3, ['uflagmey_pp_preview' => 1, 'uflagmey_pp_form' => 1] + $post));

		return $data['UFLAGMEY_PP_PREVIEW_RESULT'];
	}

	public function test_form_values_count_and_are_marked_not_saved()
	{
		$result = $this->form_preview(['uflagmey_pp_keep_count' => '1']);

		$day = function ($days) {
			return gmdate('Y-m-d', fixtures::NOW - $days * fixtures::DAY);
		};
		$this->assertSame('UFLAGMEY_PP_PREVIEW_UNSAVED|UFLAGMEY_PP_PREVIEW_RESULT|3|3|' . $day(4) . '|' . $day(2), $result,
			'keep the newest 1 besides the start post: posts 102, 103, 104');
		$this->assertSame([], $this->logs, 'no log entry');
		$this->assertCount(5, fixtures::post_ids($this->db));
		$this->assertSame('0,1,1,2', implode(',', $this->db->sql_fetchrow($this->db->sql_query('SELECT uflagmey_pp_mode, uflagmey_pp_scope, uflagmey_pp_rule, uflagmey_pp_keep_count FROM phpbb_forums WHERE forum_id = 3'))),
			'forum row unchanged');
	}

	public function test_form_values_equal_to_the_stored_ones_give_the_plain_result()
	{
		$stored = ['uflagmey_pp_scope' => '1', 'uflagmey_pp_rule' => '1', 'uflagmey_pp_days' => '30', 'uflagmey_pp_keep_count' => '2', 'uflagmey_pp_keep_first' => '1', 'uflagmey_pp_skip_sticky' => '1'];
		$full = $this->form_preview($stored);
		$this->assertStringStartsWith('UFLAGMEY_PP_PREVIEW_RESULT|2|2|', $full, 'same count as the stored settings, no marker');

		$this->assertSame($full, $this->form_preview(['uflagmey_pp_keep_count' => '2']), 'fields missing from the POST keep the stored value');
		$this->assertSame($full, $this->form_preview(['uflagmey_pp_mode' => '2', 'uflagmey_pp_count_sync' => '1'] + $stored), 'mode and post counts do not count');
	}

	public function test_invalid_form_values_give_the_errors_of_saving_and_no_count()
	{
		$cases = [
			'UFLAGMEY_PP_ERR_RULE_SCOPE'		=> ['uflagmey_pp_scope' => '0'],
			'UFLAGMEY_PP_ERR_KEEP_COUNT|1|65535'	=> ['uflagmey_pp_keep_count' => '0'],
			'UFLAGMEY_PP_ERR_DAYS|65535'		=> ['uflagmey_pp_days' => ''],
			'UFLAGMEY_PP_ERR_KEEP_FIRST'		=> ['uflagmey_pp_keep_first' => '7'],
			'UFLAGMEY_PP_ERR_SKIP_STICKY'		=> ['uflagmey_pp_skip_sticky' => '2'],
			'UFLAGMEY_PP_ERR_RULE'				=> ['uflagmey_pp_rule' => '9'],
			'UFLAGMEY_PP_ERR_SCOPE'				=> ['uflagmey_pp_scope' => '5', 'uflagmey_pp_rule' => '0'],
		];
		foreach ($cases as $error => $post)
		{
			$result = $this->form_preview($post);
			$this->assertSame('<span class="error" id="uflagmey_pp_preview_error">' . $error . '</span>', $result, json_encode($post));
		}
		$this->assertSame([], $this->logs);
	}

	public function test_form_values_by_get_are_rejected()
	{
		$listener = $this->listener(['uflagmey_pp_preview' => 1, 'uflagmey_pp_form' => 1, 'uflagmey_pp_keep_count' => 1, 'hash' => generate_link_hash('uflagmey_pp_preview.3')]);
		$this->assertSame('FORM_INVALID', $this->warning(function () use ($listener) {
			$this->edit_form($listener);
		}));
	}

	public function test_form_preview_needs_a_forum_and_the_hash()
	{
		$post = ['uflagmey_pp_preview' => 1, 'uflagmey_pp_form' => 1, 'uflagmey_pp_keep_count' => '1'];
		foreach ([[['hash' => 'deadbeef'], true], [['hash' => generate_link_hash('uflagmey_pp_preview.3')], false]] as list($get, $a_forum))
		{
			$listener = $this->listener(['uflagmey_pp_preview' => 1] + $get, $a_forum, 3, $post);
			$this->assertSame('FORM_INVALID', $this->warning(function () use ($listener) {
				$this->edit_form($listener);
			}));
		}
	}

	public function test_saving_rejects_a_manipulated_switch()
	{
		$listener = $this->listener([]);
		$data = ['forum_id' => 3, 'uflagmey_pp_mode' => 0, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 1, 'uflagmey_pp_days' => 30, 'uflagmey_pp_keep_count' => 2,
			'uflagmey_pp_keep_first' => 7, 'uflagmey_pp_skip_sticky' => 1, 'uflagmey_pp_count_sync' => 3];
		$event = new \phpbb\event\data(['forum_data' => $data, 'errors' => []]);
		$listener->validate_data($event);

		$this->assertSame(['UFLAGMEY_PP_ERR_KEEP_FIRST', 'UFLAGMEY_PP_ERR_COUNT_SYNC'], $event['errors']);
	}
}
