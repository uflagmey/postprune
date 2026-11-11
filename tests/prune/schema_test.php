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

use uflagmey\postprune\prune\schema;

/**
 * Schema marker: beta3 features only with uflagmey_pp_schema >= 2 and a current container.
 */
class schema_test extends \PHPUnit\Framework\TestCase
{
	public function test_marker_missing_is_beta2()
	{
		$this->assertSame(0, schema::version(new \phpbb\config\config([])));
		$this->assertFalse(schema::topic_scope(new \phpbb\config\config([])));
	}

	public function test_marker_2_enables_topic_scope()
	{
		$this->assertTrue(schema::topic_scope(new \phpbb\config\config(['uflagmey_pp_schema' => '2'])));
		$this->assertTrue(schema::topic_scope(new \phpbb\config\config(['uflagmey_pp_schema' => 3])));
		$this->assertFalse(schema::topic_scope(new \phpbb\config\config(['uflagmey_pp_schema' => 1])));
	}

	public function test_outdated_container_without_config_is_pending()
	{
		$this->assertFalse(schema::topic_scope(null));
	}
}
