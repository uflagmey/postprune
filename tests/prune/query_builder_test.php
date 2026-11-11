<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune\tests\prune;

use uflagmey\postprune\prune\query_builder;

/**
 * Query builder: SQL arrays (shape) and their semantics on a real phpBB DB driver (SQLite).
 */
class query_builder_test extends \PHPUnit\Framework\TestCase
{
	public function test_defaults_match_phpbb_constants()
	{
		$qb = fixtures::query_builder();
		$where = $qb->forums_query()['WHERE'];
		$this->assertStringContainsString('f.forum_type = ' . FORUM_POST, $where);

		$candidates = $qb->candidates_query(['forum_id' => 1, 'uflagmey_pp_days' => 30, 'uflagmey_pp_keep_first' => 0, 'uflagmey_pp_skip_sticky' => 1], fixtures::NOW);
		$this->assertStringContainsString('t.topic_type = ' . POST_NORMAL, $candidates['WHERE']);
	}

	public function test_forums_query_shape()
	{
		$q = fixtures::query_builder()->forums_query();
		$this->assertSame(['phpbb_forums' => 'f'], $q['FROM']);
		$this->assertStringContainsString('f.uflagmey_pp_mode > 0', $q['WHERE']);
		$this->assertSame('f.forum_id ASC', $q['ORDER_BY']);
	}

	public function cutoff_data()
	{
		return [
			'30 days'			=> [30, fixtures::NOW - 30 * 86400],
			'1 day'				=> [1, fixtures::NOW - 86400],
			'0 clamps to 1'		=> [0, fixtures::NOW - 86400],
			'negative clamps'	=> [-5, fixtures::NOW - 86400],
			'string input'		=> ['7', fixtures::NOW - 7 * 86400],
		];
	}

	/**
	 * @dataProvider cutoff_data
	 */
	public function test_cutoff($days, $expected)
	{
		$this->assertSame($expected, fixtures::query_builder()->cutoff($days, fixtures::NOW));
	}

	public function where_data()
	{
		return [
			'keep_first + skip_sticky'	=> [1, 1, true, true],
			'keep_first only'			=> [1, 0, true, false],
			'skip_sticky only'			=> [0, 1, false, true],
			'neither'					=> [0, 0, false, false],
		];
	}

	/**
	 * @dataProvider where_data
	 */
	public function test_candidates_where_options($keep_first, $skip_sticky, $has_first, $has_type)
	{
		$forum = ['forum_id' => 7, 'uflagmey_pp_days' => 30, 'uflagmey_pp_keep_first' => $keep_first, 'uflagmey_pp_skip_sticky' => $skip_sticky];
		$qb = fixtures::query_builder();

		foreach ([$qb->candidates_query($forum, fixtures::NOW), $qb->count_query($forum, fixtures::NOW)] as $q)
		{
			$this->assertStringContainsString('p.forum_id = 7', $q['WHERE']);
			$this->assertStringContainsString('p.post_time < ' . (fixtures::NOW - 30 * 86400), $q['WHERE']);
			$this->assertSame($has_first, strpos($q['WHERE'], 'p.post_id <> t.topic_first_post_id') !== false);
			$this->assertSame($has_type, strpos($q['WHERE'], 't.topic_type =') !== false);
			$this->assertStringNotContainsString('post_visibility =', $q['WHERE'], 'visibility must not matter');
		}
		$this->assertSame('p.post_time ASC, p.post_id ASC', $qb->candidates_query($forum, fixtures::NOW)['ORDER_BY']);
	}

	public function test_forums_query_selects_only_post_forums_with_mode()
	{
		$db = fixtures::db();
		fixtures::forum($db, 1, 'cat', FORUM_CAT, ['uflagmey_pp_mode' => 2]);
		fixtures::forum($db, 2, 'off', FORUM_POST, ['uflagmey_pp_mode' => 0]);
		fixtures::forum($db, 3, 'dry', FORUM_POST, ['uflagmey_pp_mode' => 1]);
		fixtures::forum($db, 4, 'link', FORUM_LINK, ['uflagmey_pp_mode' => 2]);
		fixtures::forum($db, 5, 'active', FORUM_POST, ['uflagmey_pp_mode' => 2]);

		$result = $db->sql_query($db->sql_build_query('SELECT', fixtures::query_builder()->forums_query()));
		$rows = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		$this->assertSame([3, 5], array_map('intval', array_column($rows, 'forum_id')));
		$this->assertSame('dry', $rows[0]['forum_name']);
	}

	/**
	 * @return array [ids from candidates_query, count from count_query]
	 */
	protected function select(array $settings, array $marked = [])
	{
		$db = fixtures::db();
		fixtures::forum($db, 1, 'A', FORUM_POST, $settings);
		fixtures::forum($db, 2, 'other', FORUM_POST);
		// normal topic: start 90d, replies 80d, 30d+1s (old), exactly 30d (boundary, not old), 5d
		fixtures::topic($db, 1, 10, [100 => 90, 101 => 80, 102 => 30 + 1 / 86400, 103 => 30, 104 => 5]);
		// visibility does not matter: unapproved, soft-deleted, reapprove
		fixtures::topic($db, 1, 11, [110 => 60, 111 => [50, ITEM_UNAPPROVED], 112 => [45, ITEM_DELETED], 113 => [44, ITEM_REAPPROVE]]);
		fixtures::topic($db, 1, 12, [120 => 90, 121 => 80], POST_STICKY);
		fixtures::topic($db, 1, 13, [130 => 90, 131 => 80], POST_ANNOUNCE);
		fixtures::topic($db, 1, 14, [140 => 90, 141 => 80], POST_GLOBAL);
		// same age as 101 but a higher id: tie broken by post_id
		fixtures::topic($db, 1, 15, [150 => 70, 151 => 80]);
		// another forum, old
		fixtures::topic($db, 2, 20, [200 => 90, 201 => 80]);
		if ($marked)
		{
			$db->sql_query('UPDATE phpbb_topics SET uflagmey_pp_marked = 1 WHERE ' . $db->sql_in_set('topic_id', $marked));
		}

		$forum = array_merge(['forum_id' => 1, 'uflagmey_pp_days' => 30, 'uflagmey_pp_keep_first' => 1, 'uflagmey_pp_skip_sticky' => 1], $settings);
		$qb = fixtures::query_builder();

		$result = $db->sql_query($db->sql_build_query('SELECT', $qb->candidates_query($forum, fixtures::NOW)));
		$ids = array_map('intval', array_column($db->sql_fetchrowset($result), 'post_id'));
		$db->sql_freeresult($result);

		$result = $db->sql_query($db->sql_build_query('SELECT', $qb->count_query($forum, fixtures::NOW)));
		$count = (int) $db->sql_fetchfield('num_posts');
		$db->sql_freeresult($result);

		return [$ids, $count];
	}

	public function test_candidates_keep_first_and_skip_sticky()
	{
		list($ids, $count) = $this->select(['uflagmey_pp_keep_first' => 1, 'uflagmey_pp_skip_sticky' => 1]);
		// oldest first; 101 and 151 both 80d -> by post_id; 103 (exactly 30d) and 104 are not old
		$this->assertSame([101, 151, 111, 112, 113, 102], $ids);
		$this->assertSame(count($ids), $count);
	}

	public function test_candidates_without_keep_first()
	{
		list($ids) = $this->select(['uflagmey_pp_keep_first' => 0, 'uflagmey_pp_skip_sticky' => 1]);
		$this->assertSame([100, 101, 151, 150, 110, 111, 112, 113, 102], $ids);
	}

	public function test_candidates_without_skip_sticky()
	{
		list($ids) = $this->select(['uflagmey_pp_keep_first' => 1, 'uflagmey_pp_skip_sticky' => 0]);
		$this->assertSame([101, 121, 131, 141, 151, 111, 112, 113, 102], $ids);
	}

	public function test_candidates_never_cross_forums()
	{
		list($ids) = $this->select(['uflagmey_pp_keep_first' => 0, 'uflagmey_pp_skip_sticky' => 0]);
		$this->assertEmpty(array_intersect([200, 201], $ids));
		$this->assertNotContains(103, $ids, 'exactly N days old is not older than N days');
		$this->assertNotContains(104, $ids);
	}

	public function test_marked_topics_query_lists_only_marked_topics_of_the_forum_by_title()
	{
		$db = fixtures::db();
		fixtures::forum($db, 1, 'A', FORUM_POST);
		fixtures::forum($db, 2, 'B', FORUM_POST);
		fixtures::topic($db, 1, 10, [100 => 1], POST_NORMAL, ['topic_title' => 'Zeta', 'uflagmey_pp_marked' => 1]);
		fixtures::topic($db, 1, 11, [110 => 1], POST_STICKY, ['topic_title' => 'Alpha', 'uflagmey_pp_marked' => 1]);
		fixtures::topic($db, 1, 12, [120 => 1], POST_NORMAL, ['topic_title' => 'Beta']);
		fixtures::topic($db, 1, 13, [130 => 1], POST_NORMAL, ['topic_title' => 'Shadow', 'uflagmey_pp_marked' => 1, 'topic_moved_id' => 99]);
		fixtures::topic($db, 2, 20, [200 => 1], POST_NORMAL, ['topic_title' => 'Other forum', 'uflagmey_pp_marked' => 1]);

		$result = $db->sql_query($db->sql_build_query('SELECT', fixtures::query_builder()->marked_topics_query(1)));
		$rows = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		$this->assertSame([[11, 'Alpha'], [10, 'Zeta']], array_map(function ($r) {
			return [(int) $r['topic_id'], $r['topic_title']];
		}, $rows));
	}

	public function test_scope_marked_only_marked_topics_sticky_included_keep_first_kept()
	{
		// topic 12 is a sticky: skip_sticky=1 does not apply to marked topics; topic 20 (other forum) is marked too
		list($ids, $count) = $this->select(['uflagmey_pp_scope' => 1, 'uflagmey_pp_keep_first' => 1, 'uflagmey_pp_skip_sticky' => 1], [10, 12, 20]);
		$this->assertSame([101, 121, 102], $ids);
		$this->assertSame(3, $count);
	}

	public function test_scope_marked_without_keep_first_takes_start_posts()
	{
		list($ids) = $this->select(['uflagmey_pp_scope' => 1, 'uflagmey_pp_keep_first' => 0, 'uflagmey_pp_skip_sticky' => 1], [10, 12]);
		$this->assertSame([100, 120, 101, 121, 102], $ids);
	}

	public function test_scope_marked_without_marks_selects_nothing()
	{
		list($ids, $count) = $this->select(['uflagmey_pp_scope' => 1], [20]);
		$this->assertSame([], $ids);
		$this->assertSame(0, $count);
	}

	public function test_scope_forum_ignores_marks_exactly_like_beta2()
	{
		$beta2 = [101, 151, 111, 112, 113, 102];
		list($ids) = $this->select(['uflagmey_pp_scope' => 0, 'uflagmey_pp_keep_first' => 1, 'uflagmey_pp_skip_sticky' => 1], [10, 12]);
		$this->assertSame($beta2, $ids, 'scope 0');
		list($ids) = $this->select(['uflagmey_pp_keep_first' => 1, 'uflagmey_pp_skip_sticky' => 1], [10, 12]);
		$this->assertSame($beta2, $ids, 'no scope column (update not complete) = beta2, even with scope-1 marks present');
	}

	public function test_topic_where_is_the_single_topic_rule()
	{
		$qb = fixtures::query_builder();
		$this->assertSame('t.forum_id = 4', preg_replace('/\s+/', ' ', $qb->topic_where(['forum_id' => 4, 'uflagmey_pp_skip_sticky' => 0])));
		$this->assertSame('t.forum_id = 4 AND t.topic_type = ' . POST_NORMAL, preg_replace('/\s+/', ' ', $qb->topic_where(['forum_id' => 4, 'uflagmey_pp_skip_sticky' => 1])));
		$this->assertSame('t.forum_id = 4 AND t.uflagmey_pp_marked = 1', preg_replace('/\s+/', ' ', $qb->topic_where(['forum_id' => 4, 'uflagmey_pp_skip_sticky' => 1, 'uflagmey_pp_scope' => 1])));
		$this->assertStringContainsString($qb->topic_where(['forum_id' => 4, 'uflagmey_pp_scope' => 1]), $qb->candidates_query(['forum_id' => 4, 'uflagmey_pp_days' => 30, 'uflagmey_pp_scope' => 1], fixtures::NOW)['WHERE']);
	}

	public function test_forums_query_selects_scope_only_with_topic_scope()
	{
		$qb = fixtures::query_builder();
		$this->assertStringNotContainsString('uflagmey_pp_scope', $qb->forums_query()['SELECT']);
		$this->assertStringContainsString('f.uflagmey_pp_scope', $qb->forums_query(true)['SELECT']);
	}

	public function test_marked_count_query_ignores_shadow_topics_and_other_forums()
	{
		$db = fixtures::db();
		fixtures::topic($db, 1, 10, [100 => 1], POST_NORMAL, ['uflagmey_pp_marked' => 1]);
		fixtures::topic($db, 1, 11, [110 => 1], POST_STICKY, ['uflagmey_pp_marked' => 1]);
		fixtures::topic($db, 1, 12, [120 => 1], POST_NORMAL, ['uflagmey_pp_marked' => 1, 'topic_moved_id' => 99]);
		fixtures::topic($db, 1, 13, [130 => 1]);
		fixtures::topic($db, 2, 20, [200 => 1], POST_NORMAL, ['uflagmey_pp_marked' => 1]);
		$result = $db->sql_query($db->sql_build_query('SELECT', fixtures::query_builder()->marked_count_query(['forum_id' => 1])));
		$this->assertSame(2, (int) $db->sql_fetchfield('num_topics'));
		$db->sql_freeresult($result);
	}
}
