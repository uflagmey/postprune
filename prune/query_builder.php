<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune\prune;

/**
 * Builds the SQL arrays (for $db->sql_build_query('SELECT', ...)) that select
 * the forums to process and the candidate posts. Pure: no database access,
 * so it can be unit-tested without booting phpBB.
 */
class query_builder
{
	const MODE_OFF = 0;
	const MODE_DRYRUN = 1;
	const MODE_ACTIVE = 2;

	/** @var string */
	protected $posts_table;

	/** @var string */
	protected $topics_table;

	/** @var string */
	protected $forums_table;

	/** @var int phpBB FORUM_POST */
	protected $forum_post;

	/** @var int phpBB POST_NORMAL */
	protected $post_normal;

	/**
	 * @param string	$posts_table
	 * @param string	$topics_table
	 * @param string	$forums_table
	 * @param int		$forum_post		Value of FORUM_POST
	 * @param int		$post_normal	Value of POST_NORMAL
	 */
	public function __construct($posts_table, $topics_table, $forums_table, $forum_post = 1, $post_normal = 0)
	{
		$this->posts_table = $posts_table;
		$this->topics_table = $topics_table;
		$this->forums_table = $forums_table;
		$this->forum_post = (int) $forum_post;
		$this->post_normal = (int) $post_normal;
	}

	/**
	 * Forums with post pruning switched on. Only real post forums: categories
	 * and links are ignored even if a mode is stored for them.
	 *
	 * @return array
	 */
	public function forums_query()
	{
		return [
			'SELECT'	=> 'f.forum_id, f.forum_name, f.uflagmey_pp_mode, f.uflagmey_pp_days, f.uflagmey_pp_keep_first, f.uflagmey_pp_skip_sticky, f.uflagmey_pp_count_sync',
			'FROM'		=> [$this->forums_table => 'f'],
			'WHERE'		=> 'f.uflagmey_pp_mode > ' . self::MODE_OFF . '
				AND f.forum_type = ' . $this->forum_post,
			'ORDER_BY'	=> 'f.forum_id ASC',
		];
	}

	/**
	 * Unix timestamp: posts with post_time strictly below are old enough.
	 *
	 * @param int	$days
	 * @param int	$now
	 * @return int
	 */
	public function cutoff($days, $now)
	{
		return (int) $now - max(1, (int) $days) * 86400;
	}

	/**
	 * Candidate posts of one forum, oldest first. Visibility does not matter.
	 *
	 * @param array	$forum	Row from forums_query()
	 * @param int	$now
	 * @return array
	 */
	public function candidates_query(array $forum, $now)
	{
		return [
			'SELECT'	=> 'p.post_id, p.post_visibility',
			'FROM'		=> [$this->posts_table => 'p', $this->topics_table => 't'],
			'WHERE'		=> $this->candidates_where($forum, $now),
			'ORDER_BY'	=> 'p.post_time ASC, p.post_id ASC',
		];
	}

	/**
	 * Number of candidate posts of one forum (used by the dry run).
	 *
	 * @param array	$forum	Row from forums_query()
	 * @param int	$now
	 * @return array
	 */
	public function count_query(array $forum, $now)
	{
		return [
			'SELECT'	=> 'COUNT(p.post_id) AS num_posts',
			'FROM'		=> [$this->posts_table => 'p', $this->topics_table => 't'],
			'WHERE'		=> $this->candidates_where($forum, $now),
		];
	}

	/**
	 * @param array	$forum
	 * @param int	$now
	 * @return string
	 */
	protected function candidates_where(array $forum, $now)
	{
		$where = 'p.topic_id = t.topic_id
			AND p.forum_id = ' . (int) $forum['forum_id'] . '
			AND p.post_time < ' . $this->cutoff($forum['uflagmey_pp_days'], $now);

		if (!empty($forum['uflagmey_pp_keep_first']))
		{
			$where .= '
			AND p.post_id <> t.topic_first_post_id';
		}

		if (!empty($forum['uflagmey_pp_skip_sticky']))
		{
			$where .= '
			AND t.topic_type = ' . $this->post_normal;
		}

		return $where;
	}
}
