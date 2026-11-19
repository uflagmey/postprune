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
use uflagmey\postprune\tests\prune\fixtures;

/**
 * ACP forum form: request_data takes our settings only from the POST; settings missing from
 * the POST keep the forum's stored values on edit (save and redisplay) and are defaults on add.
 */
class listener_test extends \PHPUnit\Framework\TestCase
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/**
	 * Fresh in-memory board with forum 3 (dry run, marked topics only, 90 days, all switches flipped).
	 */
	protected function seed()
	{
		$this->db = fixtures::db();
		fixtures::forum($this->db, 3, 'A', FORUM_POST, [
			'uflagmey_pp_mode'			=> 1,
			'uflagmey_pp_days'			=> 90,
			'uflagmey_pp_keep_first'	=> 0,
			'uflagmey_pp_skip_sticky'	=> 0,
			'uflagmey_pp_count_sync'	=> 1,
			'uflagmey_pp_scope'			=> 1,
		]);
	}

	/**
	 * @param array	$post		Posted fields
	 * @param bool	$current	Current container (db + query builder) and schema marker $schema
	 * @param int	$schema		Schema marker (2 = beta3/beta4, 3 = beta5 keep count)
	 * @return listener
	 */
	protected function listener(array $post, $current = true, $schema = 2)
	{
		$this->seed();
		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('is_set_post')->willReturnCallback(function ($name) use ($post) {
			return array_key_exists($name, $post);
		});
		$request->method('variable')->willReturnCallback(function ($name, $default) use ($post) {
			if (!array_key_exists($name, $post))
			{
				return $default;
			}
			$value = $post[$name];
			settype($value, gettype($default));
			return $value;
		});
		$language = $this->createMock(\phpbb\language\language::class);
		$language->method('lang')->willReturnCallback(function () {
			return implode('|', func_get_args());
		});

		return $current
			? new listener($language, $request, new \phpbb\config\config(['uflagmey_pp_schema' => $schema]), $this->db, fixtures::query_builder())
			: new listener($language, $request);
	}

	/**
	 * @return array forum_data after core.acp_manage_forums_request_data
	 */
	protected function request_data(listener $listener, $action, array $forum_data)
	{
		$event = new \phpbb\event\data(['action' => $action, 'forum_data' => $forum_data]);
		$listener->request_data($event);

		return $event['forum_data'];
	}

	protected function settings(array $forum_data)
	{
		$out = [];
		foreach (['uflagmey_pp_mode', 'uflagmey_pp_scope', 'uflagmey_pp_days', 'uflagmey_pp_keep_first', 'uflagmey_pp_skip_sticky', 'uflagmey_pp_count_sync'] as $key)
		{
			$out[$key] = array_key_exists($key, $forum_data) ? $forum_data[$key] : null;
		}

		return $out;
	}

	public function test_edit_with_posted_settings_takes_the_post()
	{
		$post = ['uflagmey_pp_mode' => '2', 'uflagmey_pp_scope' => '0', 'uflagmey_pp_days' => '45',
			'uflagmey_pp_keep_first' => '1', 'uflagmey_pp_skip_sticky' => '1', 'uflagmey_pp_count_sync' => '0'];
		$data = $this->request_data($this->listener($post), 'edit', ['forum_id' => 3, 'forum_name' => 'A']);

		$this->assertSame([
			'uflagmey_pp_mode' => 2, 'uflagmey_pp_scope' => 0, 'uflagmey_pp_days' => 45,
			'uflagmey_pp_keep_first' => 1, 'uflagmey_pp_skip_sticky' => 1, 'uflagmey_pp_count_sync' => 0,
		], $this->settings($data));
		$this->assertSame('A', $data['forum_name']);
	}

	public function test_edit_without_our_fields_keeps_the_stored_values()
	{
		$data = $this->request_data($this->listener(['forum_name' => 'A']), 'edit', ['forum_id' => 3]);

		$this->assertSame([
			'uflagmey_pp_mode' => 1, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_days' => 90,
			'uflagmey_pp_keep_first' => 0, 'uflagmey_pp_skip_sticky' => 0, 'uflagmey_pp_count_sync' => 1,
		], $this->settings($data));
	}

	public function test_edit_with_some_fields_missing_keeps_only_those()
	{
		// e.g. a form rendered before the scope field existed
		$post = ['uflagmey_pp_mode' => '2', 'uflagmey_pp_days' => '10', 'uflagmey_pp_keep_first' => '1',
			'uflagmey_pp_skip_sticky' => '1', 'uflagmey_pp_count_sync' => '0'];
		$data = $this->request_data($this->listener($post), 'edit', ['forum_id' => 3]);

		$this->assertSame([
			'uflagmey_pp_mode' => 2, 'uflagmey_pp_scope' => 1, 'uflagmey_pp_days' => 10,
			'uflagmey_pp_keep_first' => 1, 'uflagmey_pp_skip_sticky' => 1, 'uflagmey_pp_count_sync' => 0,
		], $this->settings($data));
	}

	public function test_add_without_our_fields_uses_the_defaults()
	{
		$data = $this->request_data($this->listener(['forum_name' => 'New']), 'add', ['parent_id' => 0]);

		$this->assertSame([
			'uflagmey_pp_mode' => 0, 'uflagmey_pp_scope' => 0, 'uflagmey_pp_days' => 30,
			'uflagmey_pp_keep_first' => 1, 'uflagmey_pp_skip_sticky' => 1, 'uflagmey_pp_count_sync' => 0,
		], $this->settings($data));
	}

	public function test_add_with_posted_settings_takes_the_post()
	{
		$data = $this->request_data($this->listener(['uflagmey_pp_mode' => '1', 'uflagmey_pp_days' => '7']), 'add', ['parent_id' => 0]);

		$this->assertSame(1, $data['uflagmey_pp_mode']);
		$this->assertSame(7, $data['uflagmey_pp_days']);
		$this->assertSame(1, $data['uflagmey_pp_keep_first']);
	}

	public function test_edit_of_an_unknown_forum_without_our_fields_sets_nothing()
	{
		$data = $this->request_data($this->listener([]), 'edit', ['forum_id' => 99]);

		$this->assertSame(array_fill_keys(array_keys($this->settings([])), null), $this->settings($data));
	}

	public function test_outdated_container_edit_without_our_fields_leaves_the_columns_alone()
	{
		// Old container (no db / query builder): keys stay unset, so core does not write the columns.
		$data = $this->request_data($this->listener([], false), 'edit', ['forum_id' => 3]);

		$this->assertSame(array_fill_keys(array_keys($this->settings([])), null), $this->settings($data));
	}

	public function test_forum_settings_query_reads_one_forum()
	{
		$this->seed();
		$qb = fixtures::query_builder();
		$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $qb->forum_settings_query(3, true)));
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		$this->assertSame(['1', '90', '0', '0', '1', '1'], array_map('strval', array_values($row)));

		$this->assertStringNotContainsString('uflagmey_pp_scope', $qb->forum_settings_query(3, false)['SELECT']);
	}

	/**
	 * @return array errors after core.acp_manage_forums_validate_data
	 */
	protected function validate(listener $listener, array $forum_data)
	{
		$event = new \phpbb\event\data(['forum_data' => $forum_data, 'errors' => []]);
		$listener->validate_data($event);

		return $event['errors'];
	}

	public function test_schema_3_edit_without_rule_fields_keeps_the_stored_values()
	{
		$listener = $this->listener(['uflagmey_pp_mode' => '1'], true, 3);	// seeds a fresh board
		$this->db->sql_query('UPDATE phpbb_forums SET uflagmey_pp_rule = 1, uflagmey_pp_keep_count = 250 WHERE forum_id = 3');
		$data = $this->request_data($listener, 'edit', ['forum_id' => 3]);

		$this->assertSame(1, $data['uflagmey_pp_rule']);
		$this->assertSame(250, $data['uflagmey_pp_keep_count']);
		$this->assertSame(1, $data['uflagmey_pp_scope']);
	}

	public function test_schema_3_add_gets_rule_defaults_schema_2_has_no_rule()
	{
		$data = $this->request_data($this->listener([], true, 3), 'add', ['parent_id' => 0]);
		$this->assertSame(0, $data['uflagmey_pp_rule']);
		$this->assertSame(100, $data['uflagmey_pp_keep_count']);

		$data = $this->request_data($this->listener(['uflagmey_pp_rule' => '1'], true, 2), 'add', ['parent_id' => 0]);
		$this->assertArrayNotHasKey('uflagmey_pp_rule', $data, 'without marker 3 the rule is not taken, even if posted');
	}

	public function test_rule_count_requires_scope_marked()
	{
		$base = ['uflagmey_pp_mode' => 1, 'uflagmey_pp_days' => 30, 'uflagmey_pp_keep_count' => 100];
		$listener = $this->listener([], true, 3);

		$this->assertSame(['UFLAGMEY_PP_ERR_RULE_SCOPE'], $this->validate($listener, $base + ['uflagmey_pp_rule' => 1, 'uflagmey_pp_scope' => 0]));
		$this->assertSame([], $this->validate($listener, $base + ['uflagmey_pp_rule' => 1, 'uflagmey_pp_scope' => 1]));
		$this->assertSame([], $this->validate($listener, $base + ['uflagmey_pp_rule' => 0, 'uflagmey_pp_scope' => 0]));
		$this->assertSame(['UFLAGMEY_PP_ERR_RULE'], $this->validate($listener, $base + ['uflagmey_pp_rule' => 2, 'uflagmey_pp_scope' => 1]));
	}

	public function test_keep_count_range()
	{
		$listener = $this->listener([], true, 3);
		$base = ['uflagmey_pp_mode' => 1, 'uflagmey_pp_days' => 30, 'uflagmey_pp_rule' => 1, 'uflagmey_pp_scope' => 1];
		foreach ([1 => [], 65535 => [], 0 => ['UFLAGMEY_PP_ERR_KEEP_COUNT|1|65535'], 65536 => ['UFLAGMEY_PP_ERR_KEEP_COUNT|1|65535']] as $x => $errors)
		{
			$this->assertSame($errors, $this->validate($listener, $base + ['uflagmey_pp_keep_count' => $x]), "X = $x");
		}
	}
}
