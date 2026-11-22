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
use uflagmey\postprune\prune\settings;

/**
 * rc1 (R-11, Validation Policy): default values in one place (settings::DEFAULTS); the published migrations keep
 * their literal values and must match them. New builder queries (joins through sql_build_query()) return the
 * same rows as the former raw SQL.
 */
class defaults_test extends \PHPUnit\Framework\TestCase
{
	protected function migration($class, $db)
	{
		return new $class(new \phpbb\config\config([]), $db, new \phpbb\db\tools\tools($db), './', 'php', 'phpbb_');
	}

	public function test_migration_defaults_match_settings_defaults()
	{
		$db = fixtures::db();
		$columns = [];
		foreach (['\uflagmey\postprune\migrations\v10x\m1_initial', '\uflagmey\postprune\migrations\v10x\m2_topic_scope', '\uflagmey\postprune\migrations\v10x\m3_keep_count'] as $class)
		{
			$schema = $this->migration($class, $db)->update_schema();
			foreach ($schema['add_columns']['phpbb_forums'] ?? [] as $column => $definition)
			{
				$columns[$column] = $definition[1];
			}
		}

		$this->assertSame(settings::DEFAULTS, array_intersect_key($columns, settings::DEFAULTS));
		$this->assertSame(array_keys(settings::DEFAULTS), array_keys(array_intersect_key(settings::DEFAULTS, $columns)), 'every default has a column');
	}

	public function test_core_constants_and_day()
	{
		$qb = fixtures::query_builder();
		$this->assertStringContainsString('f.forum_type = ' . FORUM_POST, $qb->forums_query()['WHERE']);
		$this->assertSame(1000 - 2 * query_builder::DAY, $qb->cutoff(2, 1000));
		$this->assertSame(settings::DEFAULTS['uflagmey_pp_keep_count'], $qb->keep_count([]));
	}

	public function test_join_queries_through_sql_build_query()
	{
		$db = fixtures::db();
		fixtures::forum($db, 1, 'A', FORUM_POST);
		fixtures::forum($db, 2, 'B', FORUM_POST);
		fixtures::topic($db, 1, 10, [100 => 5], POST_NORMAL, ['uflagmey_pp_marked' => 1]);
		fixtures::topic($db, 1, 11, [110 => 5]);
		fixtures::topic($db, 2, 20, [200 => 5], POST_NORMAL, ['uflagmey_pp_marked' => 1]);
		$qb = fixtures::query_builder();

		$query = $qb->marked_with_forum_query(0, 2);
		$query['WHERE'] .= ' AND ' . $db->sql_in_set('t.topic_id', [10, 11, 20]);
		$rows = $db->sql_fetchrowset($db->sql_query($db->sql_build_query('SELECT', $query)));
		$this->assertEquals($rows, $db->sql_fetchrowset($db->sql_query($db->sql_build_query('SELECT', $qb->marked_with_forum_query(1)))));
		$this->assertEquals([['topic_id' => 10, 'topic_title' => 'T10', 'forum_name' => 'A']], $rows);

		$row = $db->sql_fetchrow($db->sql_query($db->sql_build_query('SELECT', $qb->topic_with_forum_query(20))));
		$this->assertEquals([20, 2, 'T20', 0, 1, 'B', FORUM_POST], array_values($row));
	}
}
