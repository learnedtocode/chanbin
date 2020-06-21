<?php

class Paste {
	private static $counts_by_uid = [];
	private static $counts_by_trip = [];

	public static function load($paste_id, $paste_data = null, $need_counts = true) {
		global $db;

		if (!$paste_data) {
			$q_paste = $db->prepare("
				select id, username, trip, ip_hash, timestamp,
					title, content, deleted,
					is_mod_action, flags, cloned_from, times_viewed
				from pastes
				where id = ?
				and deleted = 0
			");
			$q_paste->bind_param('s', $paste_id);
			$q_paste->execute();
			$q_paste = $q_paste->get_result();
			if (!$q_paste->num_rows) {
				return null;
			}
			$paste_data = $q_paste->fetch_assoc();
		}

		if (!isset($paste_data['uid_count']) && $need_counts) {
			if (!isset(self::$counts_by_uid[$paste_data['ip_hash']])) {
				$q_count = $db->prepare("
					select count(*) as uid_count
					from pastes
					where ip_hash = ?
					and deleted = 0
				");
				$q_count->bind_param('s', $paste_data['ip_hash']);
				$q_count->execute();
				$q_count = $q_count->get_result();
				$count = $q_count->fetch_assoc();
				self::$counts_by_uid[$paste_data['ip_hash']] =
					$count['uid_count'];
			}
			$paste_data['uid_count'] = self::$counts_by_uid[$paste_data['ip_hash']];
		}

		if (!empty($paste_data['trip']) && !isset($paste_data['trip_count']) && $need_counts) {
			if (!isset(self::$counts_by_trip[$paste_data['trip']])) {
				$q_count = $db->prepare("
					select count(*) as trip_count
					from pastes
					where trip = ?
					and deleted = 0
				");
				$q_count->bind_param('s', $paste_data['trip']);
				$q_count->execute();
				$q_count = $q_count->get_result();
				$count = $q_count->fetch_assoc();
				self::$counts_by_trip[$paste_data['trip']] =
					$count['trip_count'];
			}
			$paste_data['trip_count'] = self::$counts_by_trip[$paste_data['trip']];
		}

		if (!isset($paste_data['uid'])) {
			$paste_data['uid'] = run_hooks('ip_hash_to_display', $paste_data['ip_hash']);
		}

		return new Paste($paste_data);
	}

	public static function list_recent() {
		return self::list();
	}

	public static function list_by_ip_hash($ip_hash) {
		return self::list('ip_hash', $ip_hash);
	}

	public static function list_by_trip($trip) {
		return self::list('trip', $trip);
	}

	private static function list($key = null, $value = null) {
		global $db;
		$sql_lines = [
			'select id, username, trip, ip_hash, timestamp,',
			'	title, content, deleted,',
			'	is_mod_action, flags, cloned_from, times_viewed',
			'from pastes',
			'where deleted = 0',
		];
		if ($key) {
			$sql_lines[] = "and $key = ?";
		}
		$sql_lines[] = 'order by timestamp desc';
		$sql_lines[] = 'limit 300';
		$sql = self::formatLines($sql_lines, false);
		$q_list = $db->prepare($sql);
		if ($key) {
			$q_list->bind_param('s', $value);
		}
		$q_list->execute();
		$q_list = $q_list->get_result();
		$pastes = [];
		while ($paste_data = $q_list->fetch_assoc()) {
			$pastes[] = self::load($paste_data['id'], $paste_data);
		}
		return $pastes;
	}

	private function __construct($data) {
		$this->data = $data;
	}

	public function __get($name) {
		return $this->data[$name] ?? null;
	}

	private static function formatLines($lines, $trailing = true) {
		return implode("\n", $lines) . ($trailing ? "\n" : '');
	}

	public function getTitleHTML() {
		$class = 'paste-title';
		$title_attr = $this->title;
		if ($this->is_mod_action) {
			$class .= ' is-mod-action';
			$title_attr = 'moderator action';
		}
		return self::formatLines([
			'<span class="' . $class . '"'
			. ' title="' . htmlspecialchars($title_attr) . '">'
				. htmlspecialchars($this->title)
			. '</span>',
		]);
	}

	public function getUserTripHTML($anon_full_word = false) {
		$lines = [];
	
		if ($this->username) {
			$lines[] =
				'<span class="user">'
					. htmlspecialchars($this->username)
				. '</span>';
		} else if (!$this->trip) {
			if ($anon_full_word) {
				$lines[] = '<span class="anon">Anonymous</span>';
			} else {
				$lines[] = '<span class="anon">Anon</span>';
			}
		}
		if ($this->trip) {
			$lines = array_merge($lines, [
				'<a class="trip-link" href="/trip/'
				. htmlspecialchars($this->trip)
				. '">'
					. '<span class="trip">'
						. htmlspecialchars('!!!' . substr($this->trip, 0, 3))
						. '<span class="narrow">&hellip;</span>'
						. '<span class="wide">'
							. htmlspecialchars(substr($this->trip, 3))
						. '</span>'
					. '</span>'
					. '<span class="count">'
						. '(' . htmlspecialchars($this->trip_count) . ')'
					. '</span>'
				. '</a>',
			]);
		}

		return self::formatLines($lines);
	}

	public function getUIDHTML() {
		$r = hexdec(substr($this->uid, 0, 2));
		$g = hexdec(substr($this->uid, 2, 2));
		$b = hexdec(substr($this->uid, 4, 2));
		if ($r < 150 && $r > $g - 30 && $r > $b - 60) {
			$r = 255 - $r;
			$g = 255 - $g;
			$b = 255 - $b;
		}
		$color =
			str_pad(dechex($r), 2, '0', STR_PAD_LEFT) .
			str_pad(dechex($g), 2, '0', STR_PAD_LEFT) .
			str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
		$class = ($r*.2126 + $g*.7152 + $b*.0722) > 128 ? 'light' : 'dark';

		return self::formatLines([
			'<a class="uid-link ' . $class . '"'
			. ' style="background: #' . htmlspecialchars($color) . '"'
			. ' href="/uid/' . htmlspecialchars($this->ip_hash) . '">',
				'<span class="uid">'
					. htmlspecialchars($this->uid)
				. '</span>'
				. '<span class="count">'
					. '(' . htmlspecialchars($this->uid_count) . ')'
				. '</span>'
			. '</a>',
		]);
	}

	public function getDateHTML() {
		$date = new DateTime('@' . $this->timestamp);
		$date->setTimeZone(new DateTimeZone('America/New_York'));

		$title = $date->format('n/d/y g:i:s a T');
		$time = $date->format('g:i a T');
		$date = $date->format('n/d/y');

		return self::formatLines([
			'<span class="date"'
			. ' data-ts="' . htmlspecialchars($this->timestamp) . '"'
			. ' title="' . htmlspecialchars($title) . '">'
				. htmlspecialchars($date)
				. '<span class="wide"> ' . htmlspecialchars($time) . '</span>'
			. '</span>',
		]);
	}

	public function getSizeHTML() {
		$size = number_format(strlen($this->content));
		return self::formatLines([
			'<span class="size">'
				. htmlspecialchars($size)
			. '</span>',
		]);
	}

	public function getInfoText($sep = "\n") {
		$items = [];
		if ($this->is_mod_action) {
			$items[] = 'title (mod): ' . $this->title;
		} else {
			$items[] = 'title: ' . $this->title;
		}
		if ($this->username) {
			$items[] = 'user: ' . $this->username;
		} else if (!$this->trip) {
			$items[] = '(anonymous)';
		}
		if ($this->trip) {
			$items[] = 'trip: !!!' . $this->trip . ' (' . $this->trip_count . ')';
		}
		$items[] = 'uid: ' . $this->uid . ' (' . $this->uid_count . ')';
		$date = new DateTime('@' . $this->timestamp);
		$date->setTimeZone(new DateTimeZone('America/New_York'));
		$items[] = 'date: ' . $date->format('n/d/y g:i:s a T');
		return implode($items, $sep);
	}

	private static function parseModeratorPasteID($line) {
		if (preg_match('@^(https?://[^/]+/paste/)?([a-zA-Z0-9]{9})$@', $line, $matches)) {
			return $matches[2];
		} else {
			return null;
		}
	}

	private function parseModeratorActionContent() {
		$len = mb_strlen($this->content);
		if ($len < 27 || $len > 1200) {
			return false;
		}
		$lines = array_map('trim', explode("\n", trim($this->content), 31));
		if (count($lines) < 3 || count($lines) > 30) {
			return false;
		}

		if (preg_match('@^((ban|wipe|delete)\s*(\+|,)\s*)+$@', $lines[0] . ',')) {
			$actions_raw = preg_replace('@\s*[,+]\s*@', ',', $lines[0]);
			$actions_raw = explode(',', $actions_raw);
			$actions = ['ban' => false, 'wipe' => false, 'delete' => false];
			foreach ($actions_raw as $action) {
				$actions[$action] = true;
			}
		} else {
			return false;
		}

		if (preg_match('@^reason\s*:(.{3,})$@', $lines[count($lines) - 1], $matches)) {
			$reason = trim($matches[1]);
			if (strlen($reason) < 3) {
				return false;
			}
		} else {
			return false;
		}

		$paste_ids = [];
		for ($i = 1; $i < count($lines) - 1; $i++) {
			$paste_id = self::parseModeratorPasteID($lines[$i]);
			if ($paste_id) {
				$paste_ids[] = $paste_id;
			} else {
				return false;
			}
		}
		if (!count($paste_ids)) { // not necessary?
			return false;
		}
		$paste_ids = array_unique($paste_ids);

		return compact('actions', 'paste_ids', 'reason');
	}

	public function parseModeratorAction() {
		$valid_title = ($this->title === '!mod');
		$valid_content = $this->parseModeratorActionContent();
		$valid_user = run_hooks('paste_is_user_moderator', false, $this);

		if ($valid_title && $valid_content && $valid_user) {
			// Don't do any DB queries until now
			// also this should query all data at once?
			$data = $valid_content;
			$data['pastes'] = [];
			foreach ($data['paste_ids'] as $paste_id) {
				$paste = self::load($paste_id);
				if ($paste) {
					$data['pastes'][$paste_id] = $paste;
				}
			}
			if (!count($data['pastes'])) {
				throw new ErrorException('Invalid moderator paste content: No valid paste IDs found');
			}
			return $data;

		} else if ($valid_title || $valid_content) {
			if ($valid_title && !$valid_user) {
				throw new ErrorException('Only moderators can use that paste title.');
			} else if ($valid_content && !$valid_user) {
				throw new ErrorException('Only moderators can use that paste content.');
			} else if ($valid_title) {
				// add more specific message?
				throw new ErrorException('Invalid moderator paste content.');
			} else if ($valid_content) {
				throw new ErrorException('Moderator paste title must be exactly "!mod" without quotes.');
			}

		} else {
			return false;
		}
	}

	public static function annotateModeratorActionContent($content, $mod_data) {
		$lines = array_map('trim', explode("\n", trim($content), 31));
		for ($i = 1; $i < count($lines) - 1; $i++) {
			$paste_id = self::parseModeratorPasteID($lines[$i]);
			if (!$paste_id || !isset($mod_data['pastes'][$paste_id])) {
				// Deleted or invalid paste ID?
				continue;
			}
			$lines[$i] .=
				' ['
				. $mod_data['pastes'][$paste_id]->getInfoText(', ')
				. ']';
		}
		return implode("\n", $lines);
	}
}
