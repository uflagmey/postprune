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

	/**
	 * Forum 3: off, marked topics only, keep the newest 2; topic 10 (marked) with 5 posts.
	 *
	 * @param array	$get		GET values
	 * @param bool	$a_forum
	 * @param int	$schema
	 * @return listener
	 */
	protected function listener(array $get, $a_forum = true, $schema = 3)
	{
		$this->db = fixtures::db();
		fixtures::forum($this->db, 3, 'A', FORUM_POST, ['uflagmey_pp_mode' => 0, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_rule' => 1, 'uflagmey_pp_keep_count' => 2]);
		fixtures::topic($this->db, 3, 10, [101 => 5, 102 => 4, 103 => 3, 104 => 2, 105 => 1], POST_NORMAL, ['uflagmey_pp_marked' => 1]);
		$config = new \phpbb\config\config(['uflagmey_pp_schema' => $schema]);

		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('is_set')->willReturnCallback(function ($name) use ($get) {
			return array_key_exists($name, $get);
		});
		$request->method('variable')->willReturnCallback(function ($name, $default) use ($get) {
			return array_key_exists($name, $get) ? $get[$name] : $default;
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
		$user->method('format_date')->willReturnCallback(function ($time) {
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

	public function test_valid_request_shows_the_stored_settings_and_the_count()
	{
		$data = $this->edit_form($this->listener(['uflagmey_pp_preview' => 1, 'hash' => generate_link_hash('uflagmey_pp_preview.3'), 'uflagmey_pp_keep_count' => 1]));

		$day = function ($days) {
			return gmdate('Y-m-d', fixtures::NOW - $days * fixtures::DAY);
		};
		$this->assertSame('UFLAGMEY_PP_PREVIEW_LINE|UFLAGMEY_PP_MODE_OFF · UFLAGMEY_PP_SAVED_MARKED|1 · UFLAGMEY_PP_SAVED_KEEP_COUNT|2 · UFLAGMEY_PP_SAVED_KEEP_FIRST'
			. '|UFLAGMEY_PP_PREVIEW_RESULT|2|2|' . $day(4) . '|' . $day(3), $data['UFLAGMEY_PP_PREVIEW_RESULT'],
			'posts 102 and 103 (keep the newest 2 besides the start post); the GET value keep_count=1 does not count');
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
}
