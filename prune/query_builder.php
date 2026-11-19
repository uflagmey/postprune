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

	const RULE_AGE = 0;
	const RULE_COUNT = 1;

	/** @var int Range of the keep count X (USINT column uflagmey_pp_keep_count) */
	const KEEP_COUNT_MIN = 1;
	const KEEP_COUNT_MAX = 65535;

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
	 * @param bool $keep_count	Schema has the rule and keep count columns (schema::keep_count()); without
	 *							them, rows carry no rule and everything uses the age rule (beta4)
	 * @return array
	 */
	public function forums_query($topic_scope = false, $keep_count = false)
	{
		return [
			'SELECT'	=> 'f.forum_id, f.forum_name, ' . $this->settings_columns($topic_scope, $keep_count),
			'FROM'		=> [$this->forums_table => 'f'],
			'WHERE'		=> 'f.uflagmey_pp_mode > ' . self::MODE_OFF . '
				AND f.forum_type = ' . (int) $this->forum_post,
			'ORDER_BY'	=> 'f.forum_id ASC',
		];
	}

	/**
	 * Stored settings of one forum (ACP form: values for settings missing from the POST).
	 *
	 * @param int	$forum_id
	 * @param bool	$topic_scope	Include the scope column (schema::topic_scope())
	 * @param bool	$keep_count		Include the rule and keep count columns (schema::keep_count())
	 * @return array
	 */
	public function forum_settings_query($forum_id, $topic_scope = false, $keep_count = false)
	{
		return [
			'SELECT'	=> $this->settings_columns($topic_scope, $keep_count),
			'FROM'		=> [$this->forums_table => 'f'],
			'WHERE'		=> 'f.forum_id = ' . (int) $forum_id,
		];
	}

	/**
	 * Per-forum setting columns available in the given schema state.
	 *
	 * @param bool $topic_scope
	 * @param bool $keep_count
	 * @return string
	 */
	protected function settings_columns($topic_scope, $keep_count)
	{
		return 'f.uflagmey_pp_mode, f.uflagmey_pp_days, f.uflagmey_pp_keep_first, f.uflagmey_pp_skip_sticky, f.uflagmey_pp_count_sync'
			. ($topic_scope ? ', f.uflagmey_pp_scope' : '')
			. ($topic_scope && $keep_count ? ', f.uflagmey_pp_rule, f.uflagmey_pp_keep_count' : '');
	}

	/**
	 * Number of forums with post pruning switched on, for cron::is_runnable(),
	 * which phpBB calls on ordinary page requests. Uses only columns that exist
	 * since 1.0.0-beta1, so a stale cache after a database restore cannot break
	 * the board's pages.
	 *
	 * @return array
	 */
	public function active_forums_count_query()
	{
		$query = $this->forums_query();
		$query['SELECT'] = 'COUNT(f.forum_id) AS num_forums';
		unset($query['ORDER_BY']);

		return $query;
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
	 * @param array	$bounds	Rule "count" only: boundaries from boundary() per topic_id
	 * @return array
	 */
	public function candidates_query(array $forum, $now, array $bounds = [])
	{
		return [
			'SELECT'	=> 'p.post_id, p.post_visibility',
			'FROM'		=> [$this->posts_table => 'p', $this->topics_table => 't'],
			'WHERE'		=> $this->candidates_where($forum, $now, $bounds),
			'ORDER_BY'	=> 'p.post_time ASC, p.post_id ASC',
		];
	}

	/**
	 * Number of candidate posts of one forum (used by the dry run).
	 *
	 * @param array	$forum	Row from forums_query()
	 * @param int	$now
	 * @param array	$bounds	Rule "count" only: boundaries from boundary() per topic_id
	 * @return array
	 */
	public function count_query(array $forum, $now, array $bounds = [])
	{
		return [
			'SELECT'	=> 'COUNT(p.post_id) AS num_posts',
			'FROM'		=> [$this->posts_table => 'p', $this->topics_table => 't'],
			'WHERE'		=> $this->candidates_where($forum, $now, $bounds),
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
	 * Deletion rule of a forum row: RULE_COUNT only if the row carries the rule
	 * column (see forums_query()), otherwise RULE_AGE as in beta4.
	 *
	 * @param array $forum
	 * @return int
	 */
	public function rule(array $forum)
	{
		return (isset($forum['uflagmey_pp_rule']) && (int) $forum['uflagmey_pp_rule'] === self::RULE_COUNT) ? self::RULE_COUNT : self::RULE_AGE;
	}

	/**
	 * Keep count X of a forum row, clamped to the valid range.
	 *
	 * @param array $forum
	 * @return int
	 */
	public function keep_count(array $forum)
	{
		$x = isset($forum['uflagmey_pp_keep_count']) ? (int) $forum['uflagmey_pp_keep_count'] : 100;

		return max(self::KEEP_COUNT_MIN, min(self::KEEP_COUNT_MAX, $x));
	}

	/**
	 * THE topic-level rule "is topic t subject to post pruning in this forum".
	 * Used by the cron candidates, the dry-run count and the topic notice.
	 * Scope "marked topics only": only marked topics, stickies/announcements
	 * included (the mark beats skip_sticky). Scope "whole forum": every topic,
	 * minus stickies/announcements if skip_sticky. Rule "count" exists only
	 * together with "marked topics only" (validated on save); a row that
	 * combines it with "whole forum" (set by hand) affects no topic at all.
	 *
	 * @param array $forum	Row from forums_query()
	 * @return string		SQL condition on the topics table alias t
	 */
	public function topic_where(array $forum)
	{
		$where = 't.forum_id = ' . (int) $forum['forum_id'];

		if ($this->rule($forum) === self::RULE_COUNT && $this->scope($forum) !== self::SCOPE_MARKED)
		{
			$where .= '
			AND 1 = 0';
		}
		else if ($this->scope($forum) === self::SCOPE_MARKED)
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
	 * Rule "count", step 1: the topics the rule applies to, with their start post.
	 * Same topics as marked_count_query() (topic_where() plus no shadow topics).
	 *
	 * @param array $forum	Row from forums_query()
	 * @return array
	 */
	public function count_topics_query(array $forum)
	{
		return [
			'SELECT'	=> 't.topic_id, t.topic_first_post_id',
			'FROM'		=> [$this->topics_table => 't'],
			'WHERE'		=> $this->topic_where($forum) . '
				AND t.topic_moved_id = 0',
			'ORDER_BY'	=> 't.topic_id ASC',
		];
	}

	/**
	 * Rule "count", step 2: approved posts of one topic, newest first (post_time,
	 * then post_id). The caller reads the first X rows with sql_query_limit($sql, X)
	 * and takes the X-th as the boundary (boundary()); fewer than X rows: nothing
	 * to delete. Deliberately no offset: phpBB's Oracle driver returns one row too
	 * early with an offset, and window functions are not available everywhere.
	 * With keep_first the start post neither counts towards X nor is deleted.
	 *
	 * @param array	$forum	Row from forums_query()
	 * @param array	$topic	Row from count_topics_query()
	 * @return array
	 */
	public function boundary_query(array $forum, array $topic)
	{
		$where = 'p.topic_id = ' . (int) $topic['topic_id'] . '
			AND p.post_visibility = ' . ITEM_APPROVED;

		if (!empty($forum['uflagmey_pp_keep_first']))
		{
			$where .= '
			AND p.post_id <> ' . (int) $topic['topic_first_post_id'];
		}

		return [
			'SELECT'	=> 'p.post_time, p.post_id',
			'FROM'		=> [$this->posts_table => 'p'],
			'WHERE'		=> $where,
			'ORDER_BY'	=> 'p.post_time DESC, p.post_id DESC',
		];
	}

	/**
	 * Boundary from the rows of boundary_query() (at most X, newest first).
	 *
	 * @param array	$rows
	 * @param int	$keep_count	X
	 * @return array|null	[post_time, post_id] of the X-th newest approved post; null: fewer than X
	 */
	public function boundary(array $rows, $keep_count)
	{
		if (count($rows) < $keep_count)
		{
			return null;
		}

		$row = $rows[$keep_count - 1];

		return [(int) $row['post_time'], (int) $row['post_id']];
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
	 * Rule "age": older than the cutoff. Rule "count": per topic everything
	 * ordered before its boundary (post_time, then post_id), whatever the
	 * visibility; topics without a boundary (fewer than X approved posts) and
	 * an empty $bounds select nothing.
	 *
	 * @param array	$forum
	 * @param int	$now
	 * @param array	$bounds	[topic_id => [post_time, post_id]]
	 * @return string
	 */
	protected function candidates_where(array $forum, $now, array $bounds = [])
	{
		$where = 'p.topic_id = t.topic_id
			AND p.forum_id = ' . (int) $forum['forum_id'] . '
			AND ' . $this->topic_where($forum);

		if ($this->rule($forum) === self::RULE_COUNT)
		{
			$terms = [];
			foreach ($bounds as $topic_id => $bound)
			{
				$time = (int) $bound[0];
				$terms[] = '(p.topic_id = ' . (int) $topic_id . ' AND (p.post_time < ' . $time
					. ' OR (p.post_time = ' . $time . ' AND p.post_id < ' . (int) $bound[1] . ')))';
			}

			$where .= '
			AND ' . ($terms ? '(' . implode("\n\t\t\t\tOR ", $terms) . ')' : '1 = 0');
		}
		else
		{
			$where .= '
			AND p.post_time < ' . $this->cutoff($forum['uflagmey_pp_days'], $now);
		}

		if (!empty($forum['uflagmey_pp_keep_first']))
		{
			$where .= '
			AND p.post_id <> t.topic_first_post_id';
		}

		return $where;
	}
}
