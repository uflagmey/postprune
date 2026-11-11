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
use uflagmey\postprune\prune\query_builder;
use uflagmey\postprune\prune\schema;

/**
 * Adds the per-forum post prune settings to ACP > Forums > Edit forum.
 */
class listener implements EventSubscriberInterface
{
	/** @var int Upper bound of the USINT column uflagmey_pp_days */
	const DAYS_MAX = 65535;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\config\config|null */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface|null */
	protected $db;

	/** @var query_builder|null */
	protected $query_builder;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * New arguments are optional and appended: after an upload over an active
	 * older version, phpBB may still run the old container with the old arguments.
	 *
	 * @param \phpbb\language\language					$language
	 * @param \phpbb\request\request_interface			$request
	 * @param \phpbb\config\config|null					$config
	 * @param \phpbb\db\driver\driver_interface|null		$db
	 * @param query_builder|null						$query_builder
	 * @param string									$root_path
	 * @param string									$php_ext
	 */
	public function __construct(\phpbb\language\language $language, \phpbb\request\request_interface $request, ?\phpbb\config\config $config = null, ?\phpbb\db\driver\driver_interface $db = null, ?query_builder $query_builder = null, $root_path = '', $php_ext = 'php')
	{
		$this->language = $language;
		$this->request = $request;
		$this->config = $config;
		$this->db = $db;
		$this->query_builder = $query_builder;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Forum scope and topic marks are usable (schema marker set, current container).
	 *
	 * @return bool
	 */
	protected function topic_scope()
	{
		return schema::topic_scope($this->config) && $this->db !== null && $this->query_builder !== null;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.acp_manage_forums_request_data'		=> 'request_data',
			'core.acp_manage_forums_initialise_data'	=> 'initialise_data',
			'core.acp_manage_forums_validate_data'		=> 'validate_data',
			'core.acp_manage_forums_display_form'		=> 'display_form',
		];
	}

	/**
	 * Default values of the per-forum settings (must match the migration).
	 *
	 * @return array
	 */
	public static function defaults()
	{
		return [
			'uflagmey_pp_mode'			=> 0,
			'uflagmey_pp_days'			=> 30,
			'uflagmey_pp_keep_first'	=> 1,
			'uflagmey_pp_skip_sticky'	=> 1,
			'uflagmey_pp_count_sync'	=> 0,
		];
	}

	/**
	 * Per-forum settings in use: the beta2 set, plus the scope once the update is complete.
	 *
	 * @return array
	 */
	protected function settings()
	{
		return self::defaults() + ($this->topic_scope() ? ['uflagmey_pp_scope' => query_builder::SCOPE_FORUM] : []);
	}

	/**
	 * Read the submitted settings into the forum data that core will save.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function request_data($event)
	{
		$forum_data = $event['forum_data'];

		foreach ($this->settings() as $key => $default)
		{
			$forum_data[$key] = $this->request->variable($key, $default);
		}

		$event['forum_data'] = $forum_data;
	}

	/**
	 * A new forum starts with the defaults.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function initialise_data($event)
	{
		if ($event['update'])
		{
			return;
		}

		$event['forum_data'] = array_merge($this->settings(), $event['forum_data']);
	}

	/**
	 * @param \phpbb\event\data $event
	 */
	public function validate_data($event)
	{
		$forum_data = $event['forum_data'];

		if (!array_key_exists('uflagmey_pp_mode', $forum_data))
		{
			return;
		}

		$this->language->add_lang('common', 'uflagmey/postprune');
		$errors = $event['errors'];

		if (!in_array((int) $forum_data['uflagmey_pp_mode'], [0, 1, 2], true))
		{
			$errors[] = $this->language->lang('UFLAGMEY_PP_ERR_MODE');
		}

		if (array_key_exists('uflagmey_pp_scope', $forum_data) && !in_array((int) $forum_data['uflagmey_pp_scope'], [query_builder::SCOPE_FORUM, query_builder::SCOPE_MARKED], true))
		{
			$errors[] = $this->language->lang('UFLAGMEY_PP_ERR_SCOPE');
		}

		$days = (int) $forum_data['uflagmey_pp_days'];
		if ($days < 1 || $days > self::DAYS_MAX)
		{
			$errors[] = $this->language->lang('UFLAGMEY_PP_ERR_DAYS', self::DAYS_MAX);
		}

		$event['errors'] = $errors;
	}

	/**
	 * @param \phpbb\event\data $event
	 */
	public function display_form($event)
	{
		$this->language->add_lang('common', 'uflagmey/postprune');

		$data = array_merge($this->settings(), $event['forum_data']);

		$template_data = [
			'UFLAGMEY_PP_MODE'			=> (int) $data['uflagmey_pp_mode'],
			'UFLAGMEY_PP_DAYS'			=> (int) $data['uflagmey_pp_days'],
			'UFLAGMEY_PP_DAYS_MAX'		=> self::DAYS_MAX,
			'S_UFLAGMEY_PP_KEEP_FIRST'	=> (bool) $data['uflagmey_pp_keep_first'],
			'S_UFLAGMEY_PP_SKIP_STICKY'	=> (bool) $data['uflagmey_pp_skip_sticky'],
			'S_UFLAGMEY_PP_COUNT_SYNC'	=> (bool) $data['uflagmey_pp_count_sync'],
			'S_UFLAGMEY_PP_UPDATE_PENDING'	=> !$this->topic_scope(),
			'S_UFLAGMEY_PP_TOPIC_SCOPE'		=> $this->topic_scope(),
		];

		if ($this->topic_scope())
		{
			$marked = ($event['action'] === 'edit' && $event['forum_id']) ? $this->marked_topics((int) $event['forum_id']) : [];
			$template_data = array_merge($template_data, [
				'UFLAGMEY_PP_SCOPE'			=> (int) $data['uflagmey_pp_scope'],
				'UFLAGMEY_PP_MARKED'		=> $marked,
				'UFLAGMEY_PP_MARKED_INACTIVE'	=> (count($marked) && (int) $data['uflagmey_pp_scope'] !== query_builder::SCOPE_MARKED)
					? $this->language->lang('UFLAGMEY_PP_MARKED_INACTIVE', count($marked)) : '',
			]);
		}

		$event['template_data'] = array_merge($event['template_data'], $template_data);
	}

	/**
	 * @param int $forum_id
	 * @return array [['ID' => int, 'TITLE' => string, 'U_VIEW' => string], ...]
	 */
	protected function marked_topics($forum_id)
	{
		$sql = $this->db->sql_build_query('SELECT', $this->query_builder->marked_topics_query($forum_id));
		$result = $this->db->sql_query($sql);
		$topics = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topics[] = [
				'ID'		=> (int) $row['topic_id'],
				'TITLE'		=> $row['topic_title'],
				'U_VIEW'	=> append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 't=' . (int) $row['topic_id']),
			];
		}
		$this->db->sql_freeresult($result);

		return $topics;
	}
}
