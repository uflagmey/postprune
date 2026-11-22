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
use uflagmey\postprune\prune\pruner;
use uflagmey\postprune\prune\query_builder;
use uflagmey\postprune\prune\schema;
use uflagmey\postprune\prune\settings;

/**
 * Adds the per-forum post prune settings to ACP > Forums > Edit forum.
 */
class listener implements EventSubscriberInterface
{
	/** @var int Upper bound of the USINT column uflagmey_pp_days (rules: prune\settings) */
	const DAYS_MAX = settings::DAYS_MAX;

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

	/** @var \phpbb\auth\auth|null */
	protected $auth;

	/** @var \phpbb\user|null */
	protected $user;

	/** @var pruner|null */
	protected $pruner;

	/** @var string */
	protected $adm_path;

	/** @var string Preview result for the form (request without JavaScript) */
	protected $preview = '';

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
	 * @param \phpbb\auth\auth|null						$auth
	 * @param \phpbb\user|null							$user
	 * @param pruner|null								$pruner
	 * @param string									$adm_path	phpBB's admin directory relative to $root_path
	 */
	public function __construct(\phpbb\language\language $language, \phpbb\request\request_interface $request, ?\phpbb\config\config $config = null, ?\phpbb\db\driver\driver_interface $db = null, ?query_builder $query_builder = null, $root_path = '', $php_ext = 'php', ?\phpbb\auth\auth $auth = null, ?\phpbb\user $user = null, ?pruner $pruner = null, $adm_path = 'adm/')
	{
		$this->language = $language;
		$this->request = $request;
		$this->config = $config;
		$this->db = $db;
		$this->query_builder = $query_builder;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->auth = $auth;
		$this->user = $user;
		$this->pruner = $pruner;
		$this->adm_path = $adm_path;
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

	/**
	 * Deletion rule and keep count are usable (schema marker 3, current container).
	 *
	 * @return bool
	 */
	protected function keep_count()
	{
		return $this->topic_scope() && schema::keep_count($this->config);
	}

	/**
	 * The ACP preview is usable: complete update (schema marker 3) and a
	 * container that has all its services.
	 *
	 * @return bool
	 */
	protected function preview_available()
	{
		return $this->keep_count() && $this->auth !== null && $this->user !== null && $this->pruner !== null;
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
	 * Default values of the beta2 per-forum settings (settings::DEFAULTS).
	 *
	 * @return array
	 */
	public static function defaults()
	{
		return array_intersect_key(settings::DEFAULTS, array_flip(['uflagmey_pp_mode', 'uflagmey_pp_days', 'uflagmey_pp_keep_first', 'uflagmey_pp_skip_sticky', 'uflagmey_pp_count_sync']));
	}

	/**
	 * Per-forum settings in use: the beta2 set, plus the scope (beta3) and the
	 * deletion rule with its keep count (beta5) once the respective update is complete.
	 *
	 * @return array
	 */
	protected function settings()
	{
		return self::defaults()
			+ ($this->topic_scope() ? array_intersect_key(settings::DEFAULTS, array_flip(['uflagmey_pp_scope'])) : [])
			+ ($this->keep_count() ? array_intersect_key(settings::DEFAULTS, array_flip(['uflagmey_pp_rule', 'uflagmey_pp_keep_count'])) : []);
	}

	/**
	 * Read the submitted settings into the forum data that core will save.
	 * Only fields that are actually in the POST count. A setting missing from
	 * the POST (form rendered without our fields, another tool posting the
	 * form) keeps the forum's stored value on edit, both when saving and when
	 * core redisplays the form after a validation error; a new forum gets the
	 * default.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function request_data($event)
	{
		$forum_data = $event['forum_data'];
		$edit = ($event['action'] === 'edit' && !empty($forum_data['forum_id']));

		$event['forum_data'] = array_merge($forum_data, $this->posted_settings($this->settings(), $edit ? (int) $forum_data['forum_id'] : 0));
	}

	/**
	 * Settings from the POST, read the same way for saving the forum form and
	 * for the preview with the values in the form: strictly as a plain number
	 * (settings::posted_int(); an array, "7.9", "12abc" or an empty field gives
	 * settings::INVALID, which the rules then reject). A setting missing
	 * from the POST keeps the stored value of an existing forum ($forum_id),
	 * a new forum ($forum_id 0) gets the default.
	 *
	 * @param array	$settings	key => default
	 * @param int	$forum_id	Existing forum, 0 for a new one
	 * @return array	key => int; an existing forum's key is left out if nothing is stored
	 *					(unknown forum or outdated container: core does not touch the column)
	 */
	protected function posted_settings(array $settings, $forum_id)
	{
		$data = [];
		$stored = null;

		foreach ($settings as $key => $default)
		{
			if ($this->request->is_set_post($key))
			{
				$data[$key] = settings::posted_int($this->request->variable($key, ''));
			}
			else if (!$forum_id)
			{
				$data[$key] = $default;
			}
			else
			{
				if ($stored === null)
				{
					$stored = $this->stored_settings($forum_id);
				}

				if (isset($stored[$key]))
				{
					$data[$key] = (int) $stored[$key];
				}
			}
		}

		return $data;
	}

	/**
	 * Stored per-forum settings of one forum.
	 *
	 * @param int $forum_id
	 * @return array Empty if the forum does not exist or the container is outdated (no db / query builder)
	 */
	protected function stored_settings($forum_id)
	{
		if ($this->db === null || $this->query_builder === null)
		{
			return [];
		}

		$sql = $this->db->sql_build_query('SELECT', $this->query_builder->forum_settings_query($forum_id, $this->topic_scope(), $this->keep_count()));
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: [];
	}

	/**
	 * A new forum starts with the defaults. A preview request (button in the
	 * block with link hash, never the forum form: core only saves with "update"
	 * in the POST) is answered here: with JavaScript a POST with the values in
	 * the form, answered as JSON; without JavaScript the GET link, stored
	 * settings, result shown in the form.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function initialise_data($event)
	{
		if ($event['update'])
		{
			return;
		}

		if ($event['action'] === 'edit' && ($this->request->is_set('uflagmey_pp_preview', \phpbb\request\request_interface::GET)
			|| $this->request->is_set_post('uflagmey_pp_preview')))
		{
			$this->preview_request((int) $event['forum_id']);
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

		$event['errors'] = array_merge($event['errors'], $this->setting_errors(array_intersect_key($forum_data, $this->settings())));
	}

	/**
	 * Translated errors of the settings (rules: prune\settings::errors()).
	 *
	 * @param array $settings
	 * @return array
	 */
	protected function setting_errors(array $settings)
	{
		$this->language->add_lang('common', 'uflagmey/postprune');

		return array_map(function ($error) {
			return call_user_func_array([$this->language, 'lang'], $error);
		}, settings::errors($settings));
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
			'S_UFLAGMEY_PP_UPDATE_PENDING'	=> !$this->keep_count(),
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

		if ($this->keep_count() && $event['action'] === 'edit' && $event['forum_id'])
		{
			$saved = $this->saved_line((int) $event['forum_id']);
			if ($saved)
			{
				$template_data['UFLAGMEY_PP_SAVED_FOR'] = $this->language->lang('UFLAGMEY_PP_SAVED_FOR', $saved['forum_name']);
				$template_data['UFLAGMEY_PP_SAVED'] = $saved['summary'];
			}
		}

		if ($this->preview_available() && $event['action'] === 'edit' && $event['forum_id'])
		{
			$template_data = array_merge($template_data, [
				'U_UFLAGMEY_PP_PREVIEW'			=> $this->preview_url((int) $event['forum_id']),
				'UFLAGMEY_PP_PREVIEW_RESULT'	=> $this->preview,
			]);
		}

		if ($this->keep_count())
		{
			$template_data = array_merge($template_data, [
				'S_UFLAGMEY_PP_KEEP_COUNT'		=> true,
				'UFLAGMEY_PP_RULE'				=> (int) $data['uflagmey_pp_rule'],
				'UFLAGMEY_PP_KEEP_COUNT'		=> (int) $data['uflagmey_pp_keep_count'],
				'UFLAGMEY_PP_KEEP_COUNT_MIN'	=> query_builder::KEEP_COUNT_MIN,
				'UFLAGMEY_PP_KEEP_COUNT_MAX'	=> query_builder::KEEP_COUNT_MAX,
			]);
		}

		$event['template_data'] = array_merge($event['template_data'], $template_data);
	}

	/**
	 * @param int $forum_id
	 * @return string
	 */
	protected function preview_hash_name($forum_id)
	{
		return 'uflagmey_pp_preview.' . (int) $forum_id;
	}

	/**
	 * Link of the preview button: its own GET action of acp_forums with a link hash.
	 *
	 * @param int $forum_id
	 * @return string
	 */
	protected function preview_url($forum_id)
	{
		return append_sid($this->root_path . $this->adm_path . 'index.' . $this->php_ext, [
			'i'						=> 'acp_forums',
			'mode'					=> 'manage',
			'action'				=> 'edit',
			'f'						=> (int) $forum_id,
			'uflagmey_pp_preview'	=> 1,
			'hash'					=> generate_link_hash($this->preview_hash_name($forum_id)),
		]);
	}

	/**
	 * Answers a preview request. Only for a_forum (acp_forums checks that already)
	 * with a valid link hash for this forum; anything else is rejected.
	 *
	 * @param int $forum_id
	 */
	protected function preview_request($forum_id)
	{
		$this->language->add_lang('common', 'uflagmey/postprune');

		// Values from the form only by POST; a GET carrying them is rejected.
		$form = $this->request->is_set_post('uflagmey_pp_form');
		$valid = $this->preview_available()
			&& $this->auth->acl_get('a_forum')
			&& !$this->request->is_set('uflagmey_pp_form', \phpbb\request\request_interface::GET)
			&& check_link_hash($this->request->variable('hash', ''), $this->preview_hash_name($forum_id));

		if ($this->request->is_ajax())
		{
			$json = new \phpbb\json_response();
			$json->send($valid ? [
				'success'				=> true,
				'UFLAGMEY_PP_PREVIEW'	=> $form ? $this->form_preview_text($forum_id) : $this->preview_text($forum_id),
			] : [
				'success'		=> false,
				'MESSAGE_TITLE'	=> $this->language->lang('ERROR'),
				'MESSAGE_TEXT'	=> $this->language->lang('FORM_INVALID'),
			]);
		}

		if (!$valid)
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link(append_sid($this->root_path . $this->adm_path . 'index.' . $this->php_ext, 'i=acp_forums&amp;mode=manage&amp;action=edit&amp;f=' . (int) $forum_id)), E_USER_WARNING);
		}

		$this->preview = $form ? $this->form_preview_text($forum_id) : $this->preview_text($forum_id);
	}

	/**
	 * Preview with the values in the form (POST): the stored settings overlaid
	 * with the posted ones (settings::PREVIEW_KEYS), checked by the same rules
	 * as saving. Invalid values: the errors saving would show, no count.
	 * Values that differ from the stored ones are marked "not saved yet".
	 *
	 * @param int $forum_id
	 * @return string
	 */
	protected function form_preview_text($forum_id)
	{
		$keys = array_intersect_key($this->settings(), array_flip(settings::PREVIEW_KEYS));
		$values = $this->posted_settings($keys, $forum_id);

		$errors = $this->setting_errors($values);
		if ($errors)
		{
			return '<span class="error" id="uflagmey_pp_preview_error">' . implode('<br>', $errors) . '</span>';
		}

		$stored = $this->stored_settings($forum_id);
		$unsaved = false;
		foreach ($values as $key => $value)
		{
			$unsaved = $unsaved || !isset($stored[$key]) || (int) $stored[$key] !== (int) $value;
		}

		return $this->preview_text($forum_id, $values, $unsaved);
	}

	/**
	 * Result of the preview, shown right below the "Saved for …" line (which names the
	 * settings): "→ n posts would be deleted (oldest: <date>, newest to be deleted: <date>)",
	 * dates in the board/user date format with time. Rule "count": a last line names
	 * the rule counted with, both conditions (rc1: X and the minimum age).
	 *
	 * @param int		$forum_id
	 * @param array	$form		Values from the form, already checked (see form_preview_text()); empty: stored settings
	 * @param bool		$unsaved	The form values differ from the stored ones
	 * @return string
	 */
	protected function preview_text($forum_id, array $form = [], $unsaved = false)
	{
		$result = $this->pruner->preview($forum_id, time(), $form);
		if (!$result)
		{
			return $this->language->lang($result === null ? 'NO_FORUM' : 'UFLAGMEY_PP_PREVIEW_FAILED');
		}

		if (!$result['post_forum'])
		{
			return $this->language->lang('UFLAGMEY_PP_PREVIEW_NOT_POST_FORUM');
		}

		if ($result['doubt'])
		{
			return $this->language->lang('UFLAGMEY_PP_PREVIEW_DOUBT');
		}

		$sep = $this->language->lang('UFLAGMEY_PP_THOUSANDS_SEP');

		$text = $this->language->lang($result['softdeleted'] ? 'UFLAGMEY_PP_PREVIEW_RESULT_SOFT' : 'UFLAGMEY_PP_PREVIEW_RESULT', $result['num'],
			number_format($result['num'], 0, '', $sep),
			$result['num'] ? $this->user->format_date($result['oldest'], false, true) : '',
			$result['num'] ? $this->user->format_date($result['newest'], false, true) : '',
			number_format($result['softdeleted'], 0, '', $sep));
		if ($unsaved)
		{
			$text = $this->language->lang('UFLAGMEY_PP_PREVIEW_UNSAVED', $text);
		}
		if ($this->query_builder->rule($result['forum']) === query_builder::RULE_COUNT)
		{
			$text .= '<br>' . $this->language->lang('UFLAGMEY_PP_PREVIEW_RULE', $this->count_rule_text($result['forum']));
		}

		return $text;
	}

	/**
	 * Rule "count" in words, both conditions: "keep the newest 100 per topic, delete older ones after 30 days".
	 *
	 * @param array $forum	Settings row (stored, or overlaid with the form)
	 * @return string
	 */
	protected function count_rule_text(array $forum)
	{
		return $this->language->lang('UFLAGMEY_PP_SAVED_KEEP_COUNT', $this->query_builder->keep_count($forum))
			. ', ' . $this->language->lang('UFLAGMEY_PP_SAVED_KEEP_AGE', $this->query_builder->days($forum));
	}

	/**
	 * The always visible "Saved: …" line of the block: the forum's settings as
	 * stored in the database, so a form that differs from them stands out
	 * (e.g. redisplayed after a validation error). Reads the forum row and the
	 * number of marked topics, no post counting.
	 *
	 * @param int $forum_id
	 * @return array|null	['forum_name' => string, 'summary' => string]; null if the forum does not exist
	 */
	protected function saved_line($forum_id)
	{
		$sql = $this->db->sql_build_query('SELECT', $this->query_builder->forum_query($forum_id, true, true));
		$result = $this->db->sql_query($sql);
		$forum = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$forum)
		{
			return null;
		}

		$topics = null;
		if ($this->query_builder->scope($forum) === query_builder::SCOPE_MARKED)
		{
			$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $this->query_builder->marked_count_query($forum)));
			$topics = (int) $this->db->sql_fetchfield('num_topics');
			$this->db->sql_freeresult($result);
		}

		return ['forum_name' => $forum['forum_name'], 'summary' => $this->saved_summary($forum, $topics)];
	}

	/**
	 * Stored settings in words: "Dry run · Marked topics only (3) · 90 days · Keep first post".
	 *
	 * @param array		$forum	Row from query_builder::forum_query()
	 * @param int|null	$topics	Number of marked topics, null for scope "whole forum"
	 * @return string
	 */
	protected function saved_summary(array $forum, $topics)
	{
		$modes = [query_builder::MODE_OFF => 'UFLAGMEY_PP_MODE_OFF', query_builder::MODE_DRYRUN => 'UFLAGMEY_PP_MODE_DRYRUN', query_builder::MODE_ACTIVE => 'UFLAGMEY_PP_MODE_ACTIVE'];
		$mode = (int) $forum['uflagmey_pp_mode'];
		$parts = [$this->language->lang(isset($modes[$mode]) ? $modes[$mode] : 'UFLAGMEY_PP_MODE_OFF')];

		$marked = ($this->query_builder->scope($forum) === query_builder::SCOPE_MARKED);
		$parts[] = $marked ? $this->language->lang('UFLAGMEY_PP_SAVED_MARKED', (int) $topics) : $this->language->lang('UFLAGMEY_PP_SCOPE_FORUM');

		if ($this->query_builder->rule($forum) === query_builder::RULE_COUNT)
		{
			$parts[] = $this->count_rule_text($forum);
		}
		else
		{
			$parts[] = $this->language->lang('UFLAGMEY_PP_SAVED_DAYS', $this->query_builder->days($forum));
		}

		$parts[] = $this->language->lang(empty($forum['uflagmey_pp_keep_first']) ? 'UFLAGMEY_PP_SAVED_KEEP_FIRST_NO' : 'UFLAGMEY_PP_SAVED_KEEP_FIRST');
		if (!$marked)
		{
			$parts[] = $this->language->lang(empty($forum['uflagmey_pp_skip_sticky']) ? 'UFLAGMEY_PP_SAVED_SKIP_STICKY_NO' : 'UFLAGMEY_PP_SAVED_SKIP_STICKY');
		}
		if (!empty($forum['uflagmey_pp_count_sync']))
		{
			$parts[] = $this->language->lang('UFLAGMEY_PP_SAVED_COUNT_SYNC');
		}
		if (settings::doubt($forum, schema::version($this->config)))
		{
			$parts[] = $this->language->lang('UFLAGMEY_PP_SAVED_DOUBT');
		}

		return implode(' · ', $parts);
	}

	/**
	 * Marked topics of the forum for the ACP list. Without f_read on the forum (rc1) only the
	 * topic IDs are shown, no titles and no links.
	 *
	 * @param int $forum_id
	 * @return array [['ID' => int, 'TITLE' => string, 'U_VIEW' => string, 'S_HIDDEN' => bool], ...]
	 */
	protected function marked_topics($forum_id)
	{
		$readable = $this->auth !== null && $this->auth->acl_get('f_read', $forum_id);
		$sql = $this->db->sql_build_query('SELECT', $this->query_builder->marked_topics_query($forum_id));
		$result = $this->db->sql_query($sql);
		$topics = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topics[] = [
				'ID'		=> (int) $row['topic_id'],
				'TITLE'		=> $readable ? $row['topic_title'] : '',
				'U_VIEW'	=> $readable ? append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 't=' . (int) $row['topic_id']) : '',
				'S_HIDDEN'	=> !$readable,
			];
		}
		$this->db->sql_freeresult($result);

		return $topics;
	}
}
