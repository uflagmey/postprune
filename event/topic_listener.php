<?php
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace uflagmey\postprune\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use uflagmey\postprune\prune\schema;

/**
 * Topic mark ("prune this topic"): quick-mod switch in the topic view for
 * administrators with a_forum, and removal of the mark when a topic leaves
 * its forum (MCP move, ACP user "move posts", ACP forum delete/convert with
 * moving the content).
 */
class topic_listener implements EventSubscriberInterface
{
	const ACTION_MARK = 'uflagmey_pp_mark';
	const ACTION_UNMARK = 'uflagmey_pp_unmark';

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $topics_table;

	/** @var string */
	protected $forums_table;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/** @var array Marked topics of a forum whose content is being moved in the ACP */
	protected $moving = [];

	/**
	 * @param \phpbb\auth\auth					$auth
	 * @param \phpbb\config\config				$config
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\language\language			$language
	 * @param \phpbb\log\log_interface			$log
	 * @param \phpbb\request\request_interface	$request
	 * @param \phpbb\user						$user
	 * @param string							$topics_table
	 * @param string							$forums_table
	 * @param string							$root_path
	 * @param string							$php_ext
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\language\language $language, \phpbb\log\log_interface $log, \phpbb\request\request_interface $request, \phpbb\user $user, $topics_table, $forums_table, $root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;
		$this->log = $log;
		$this->request = $request;
		$this->user = $user;
		$this->topics_table = $topics_table;
		$this->forums_table = $forums_table;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.viewtopic_add_quickmod_option_before'		=> 'add_quickmod_option',
			'core.mcp_modify_permissions'					=> 'mcp_permissions',
			'core.modify_quickmod_options'					=> 'quickmod_action',
			'core.move_topics_before_query'					=> 'move_topics',
			'core.acp_manage_forums_move_content'			=> 'move_content_before',
			'core.acp_manage_forums_move_content_after'		=> 'move_content_after',
		];
	}

	/**
	 * Show "Prune automatically: switch on/off" in the quick-mod tools.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function add_quickmod_option($event)
	{
		$topic = $event['topic_data'];
		if (!schema::topic_scope($this->config) || !$this->auth->acl_get('a_forum')
			|| (int) $topic['forum_type'] !== FORUM_POST || !empty($topic['topic_moved_id']))
		{
			return;
		}

		$this->language->add_lang('common', 'uflagmey/postprune');
		$marked = !empty($topic['uflagmey_pp_marked']);

		$quickmod_array = $event['quickmod_array'];
		$quickmod_array[$marked ? self::ACTION_UNMARK : self::ACTION_MARK] = [$marked ? 'UFLAGMEY_PP_QM_UNMARK' : 'UFLAGMEY_PP_QM_MARK', true];
		$event['quickmod_array'] = $quickmod_array;
	}

	/**
	 * mcp.php rejects everybody without any m_ permission before the quick-mod
	 * switch is reached. Let administrators with a_forum through, but only for
	 * our two quick-mod actions; every other MCP request keeps the rejection.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function mcp_permissions($event)
	{
		if ($event['allow_user'] || !$event['quickmod'] || !schema::topic_scope($this->config))
		{
			return;
		}

		if ($this->own_action() !== '' && $this->auth->acl_get('a_forum'))
		{
			$event['allow_user'] = true;
		}
	}

	/**
	 * Handles our quick-mod actions (both the GET link and the confirm POST).
	 * Never returns for our actions: shows the confirm box, a message or an error.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function quickmod_action($event)
	{
		$action = $event['action'];
		if ($action !== self::ACTION_MARK && $action !== self::ACTION_UNMARK)
		{
			return;
		}

		$this->language->add_lang('common', 'uflagmey/postprune');
		$event['is_valid_action'] = true;

		if (!schema::topic_scope($this->config) || $this->own_action() !== $action || !$this->auth->acl_get('a_forum'))
		{
			send_status_line(403, 'Forbidden');
			trigger_error('NOT_AUTHORISED');
		}

		$topic = $this->get_topic($this->request->variable('t', 0));
		if (!$topic)
		{
			send_status_line(404, 'Not Found');
			trigger_error('NO_TOPIC');
		}
		if ((int) $topic['forum_type'] !== FORUM_POST || (int) $topic['topic_moved_id'] !== 0)
		{
			send_status_line(403, 'Forbidden');
			trigger_error('UFLAGMEY_PP_QM_NOT_POSSIBLE');
		}

		$mark = ($action === self::ACTION_MARK);
		$topic_id = (int) $topic['topic_id'];
		$u_topic = append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 't=' . $topic_id);
		$return = '<br><br>' . $this->language->lang('RETURN_TOPIC', '<a href="' . $u_topic . '">', '</a>');

		if ((bool) $topic['uflagmey_pp_marked'] === $mark)
		{
			trigger_error($this->language->lang($mark ? 'UFLAGMEY_PP_QM_ALREADY_MARKED' : 'UFLAGMEY_PP_QM_NOT_MARKED') . $return);
		}

		if (confirm_box(true))
		{
			$new = (int) $mark;
			$sql = 'UPDATE ' . $this->topics_table . '
				SET uflagmey_pp_marked = ' . $new . '
				WHERE topic_id = ' . (int) $topic_id . '
					AND uflagmey_pp_marked = ' . (1 - $new);
			$this->db->sql_query($sql);

			if ($this->db->sql_affectedrows())
			{
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, $mark ? 'LOG_UFLAGMEY_PP_TOPIC_MARKED' : 'LOG_UFLAGMEY_PP_TOPIC_UNMARKED', false, [
					$topic['topic_title'],
					$topic['forum_name'],
				]);
			}

			meta_refresh(3, $u_topic);
			trigger_error($this->language->lang($mark ? 'UFLAGMEY_PP_QM_MARKED' : 'UFLAGMEY_PP_QM_UNMARKED') . $return);
		}

		confirm_box(false, $this->language->lang($mark ? 'UFLAGMEY_PP_QM_MARK_CONFIRM' : 'UFLAGMEY_PP_QM_UNMARK_CONFIRM', $topic['topic_title']), build_hidden_fields([
			't'			=> $topic_id,
			'f'			=> (int) $topic['forum_id'],
			'quickmod'	=> 1,
			'action'	=> $action,
		]));

		// Cancelled (or a confirm POST without valid confirm key): back to the topic, nothing changed.
		redirect($u_topic);
	}

	/**
	 * MCP move and ACP user "move posts": topics leaving their forum lose the mark.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function move_topics($event)
	{
		if (!schema::topic_scope($this->config) || empty($event['topic_ids']))
		{
			return;
		}

		$sql = 'SELECT t.topic_id, t.topic_title, f.forum_name
			FROM ' . $this->topics_table . ' t
			LEFT JOIN ' . $this->forums_table . ' f ON (f.forum_id = t.forum_id)
			WHERE ' . $this->db->sql_in_set('t.topic_id', array_map('intval', $event['topic_ids'])) . '
				AND t.forum_id <> ' . (int) $event['forum_id'] . '
				AND t.uflagmey_pp_marked = 1';
		$this->unmark($this->fetch($sql), $this->forum_name((int) $event['forum_id']));
	}

	/**
	 * ACP forum delete/convert with "move content": remember the marked topics
	 * (they are moved by a plain UPDATE, not by move_topics()).
	 *
	 * @param \phpbb\event\data $event
	 */
	public function move_content_before($event)
	{
		$this->moving = [];
		if (!schema::topic_scope($this->config) || !empty($event['errors']) || (int) $event['from_id'] === (int) $event['to_id'])
		{
			return;
		}

		$sql = 'SELECT t.topic_id, t.topic_title, f.forum_name
			FROM ' . $this->topics_table . ' t
			LEFT JOIN ' . $this->forums_table . ' f ON (f.forum_id = t.forum_id)
			WHERE t.forum_id = ' . (int) $event['from_id'] . '
				AND t.uflagmey_pp_marked = 1';
		$this->moving = $this->fetch($sql);
	}

	/**
	 * @param \phpbb\event\data $event
	 */
	public function move_content_after($event)
	{
		$topics = $this->moving;
		$this->moving = [];
		if ($topics)
		{
			$this->unmark($topics, $this->forum_name((int) $event['to_id']));
		}
	}

	/**
	 * Our action as mcp.php will see it. mcp.php may take the action from
	 * action[...] or forum_action instead of the plain action parameter: then
	 * it is not ours, whatever the plain parameter says.
	 *
	 * @return string
	 */
	protected function own_action()
	{
		if ($this->request->variable('forum_action', '') !== '' || count($this->request->variable('action', ['' => 0])))
		{
			return '';
		}

		$action = $this->request->variable('action', '');

		return in_array($action, [self::ACTION_MARK, self::ACTION_UNMARK], true) ? $action : '';
	}

	/**
	 * @param int $topic_id
	 * @return array|false
	 */
	protected function get_topic($topic_id)
	{
		$sql = 'SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_moved_id, t.uflagmey_pp_marked, f.forum_name, f.forum_type
			FROM ' . $this->topics_table . ' t, ' . $this->forums_table . ' f
			WHERE t.topic_id = ' . (int) $topic_id . '
				AND f.forum_id = t.forum_id';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row;
	}

	/**
	 * @param string $sql
	 * @return array
	 */
	protected function fetch($sql)
	{
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows ?: [];
	}

	/**
	 * @param int $forum_id
	 * @return string
	 */
	protected function forum_name($forum_id)
	{
		$sql = 'SELECT forum_name
			FROM ' . $this->forums_table . '
			WHERE forum_id = ' . (int) $forum_id;
		$result = $this->db->sql_query($sql);
		$name = (string) $this->db->sql_fetchfield('forum_name');
		$this->db->sql_freeresult($result);

		return $name;
	}

	/**
	 * @param array		$topics		Rows with topic_id, topic_title, forum_name (source forum)
	 * @param string	$to_name	Target forum name
	 */
	protected function unmark(array $topics, $to_name)
	{
		if (!$topics)
		{
			return;
		}

		$sql = 'UPDATE ' . $this->topics_table . '
			SET uflagmey_pp_marked = 0
			WHERE ' . $this->db->sql_in_set('topic_id', array_map('intval', array_column($topics, 'topic_id')));
		$this->db->sql_query($sql);

		$user_id = isset($this->user->data['user_id']) ? (int) $this->user->data['user_id'] : ANONYMOUS;
		$ip = !empty($this->user->ip) ? $this->user->ip : '127.0.0.1';
		foreach ($topics as $topic)
		{
			$this->log->add('admin', $user_id, $ip, 'LOG_UFLAGMEY_PP_TOPIC_UNMARKED_MOVED', false, [
				$topic['topic_title'],
				(string) $topic['forum_name'],
				$to_name,
			]);
		}
	}
}
