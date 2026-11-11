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

	const SCOPE_FORUM = 0;
	const SCOPE_MARKED = 1;

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
	 * @param bool $topic_scope	Schema has the scope column (schema::topic_scope()); without it,
	 *							rows carry no scope and everything behaves like scope 0 (beta2)
	 * @return array
	 */
	public function forums_query($topic_scope = false)
	{
		return [
			'SELECT'	=> 'f.forum_id, f.forum_name, f.uflagmey_pp_mode, f.uflagmey_pp_days, f.uflagmey_pp_keep_first, f.uflagmey_pp_skip_sticky, f.uflagmey_pp_count_sync'
				. ($topic_scope ? ', f.uflagmey_pp_scope' : ''),
			'FROM'		=> [$this->forums_table => 'f'],
			'WHERE'		=> 'f.uflagmey_pp_mode > ' . self::MODE_OFF . '
				AND f.forum_type = ' . (int) $this->forum_post,
			'ORDER_BY'	=> 'f.forum_id ASC',
		];
	}

	/**
	 * Topics of one forum that are marked for pruning (ACP list). Only valid
	 * with schema::topic_scope().
	 *
	 * @param int	$forum_id
	 * @return array
	 */
	public function marked_topics_query($forum_id)
	{
		return [
			'SELECT'	=> 't.topic_id, t.topic_title',
			'FROM'		=> [$this->topics_table => 't'],
			'WHERE'		=> 't.forum_id = ' . (int) $forum_id . '
				AND t.uflagmey_pp_marked = 1
				AND t.topic_moved_id = 0',
			'ORDER_BY'	=> 't.topic_title ASC, t.topic_id ASC',
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
	 * Scope of a forum row: SCOPE_MARKED only if the row carries the scope
	 * column (see forums_query()), otherwise SCOPE_FORUM as in beta2.
	 *
	 * @param array $forum
	 * @return int
	 */
	public function scope(array $forum)
	{
		return (isset($forum['uflagmey_pp_scope']) && (int) $forum['uflagmey_pp_scope'] === self::SCOPE_MARKED) ? self::SCOPE_MARKED : self::SCOPE_FORUM;
	}

	/**
	 * THE topic-level rule "is topic t subject to post pruning in this forum".
	 * Used by the cron candidates, the dry-run count and the topic notice.
	 * Scope "marked topics only": only marked topics, stickies/announcements
	 * included (the mark beats skip_sticky). Scope "whole forum": every topic,
	 * minus stickies/announcements if skip_sticky.
	 *
	 * @param array $forum	Row from forums_query()
	 * @return string		SQL condition on the topics table alias t
	 */
	public function topic_where(array $forum)
	{
		$where = 't.forum_id = ' . (int) $forum['forum_id'];

		if ($this->scope($forum) === self::SCOPE_MARKED)
		{
			$where .= '
			AND t.uflagmey_pp_marked = 1';
		}
		else if (!empty($forum['uflagmey_pp_skip_sticky']))
		{
			$where .= '
			AND t.topic_type = ' . $this->post_normal;
		}

		return $where;
	}

	/**
	 * Does topic_where() hold for one topic? (topic notice; same rule as the cron)
	 *
	 * @param array	$forum		Forum settings (forum_id + uflagmey_pp_* incl. scope if available)
	 * @param int	$topic_id
	 * @return array
	 */
	public function topic_applies_query(array $forum, $topic_id)
	{
		$where = 't.topic_id = ' . (int) $topic_id . '
			AND ' . $this->topic_where($forum);

		return [
			'SELECT'	=> 't.topic_id',
			'FROM'		=> [$this->topics_table => 't'],
			'WHERE'		=> $where,
		];
	}

	/**
	 * Number of marked topics of a forum (logs for scope "marked topics only").
	 * Fixed rule: the topic count in the DRYRUN/PRUNED_TOPIC(S) log entries is
	 * the number of MARKED topics in the forum, whether or not each of them has
	 * old posts right now (shadow topics are not counted).
	 *
	 * @param array $forum
	 * @return array
	 */
	public function marked_count_query(array $forum)
	{
		return [
			'SELECT'	=> 'COUNT(t.topic_id) AS num_topics',
			'FROM'		=> [$this->topics_table => 't'],
			'WHERE'		=> 't.forum_id = ' . (int) $forum['forum_id'] . '
				AND t.uflagmey_pp_marked = 1
				AND t.topic_moved_id = 0',
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
			AND ' . $this->topic_where($forum) . '
			AND p.post_time < ' . $this->cutoff($forum['uflagmey_pp_days'], $now);

		if (!empty($forum['uflagmey_pp_keep_first']))
		{
			$where .= '
			AND p.post_id <> t.topic_first_post_id';
		}

		return $where;
	}
}
