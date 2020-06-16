<?php

class Paste {
	private static $counts_by_uid = [];
	private static $counts_by_trip = [];

	public static function load($paste_id, $paste_data = null) {
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

		if (!isset($paste_data['uid_count'])) {
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

		if (!empty($paste_data['trip']) && !isset($paste_data['trip_count'])) {
			if (!isset(self::$counts_by_trip[$paste_data['ip_hash']])) {
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
				self::$counts_by_trip[$paste_data['ip_hash']] =
					$count['trip_count'];
			}
			$paste_data['trip_count'] = self::$counts_by_trip[$paste_data['ip_hash']];
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
		$sql_lines[] = 'limit 30';
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

	public function getReadableDate() {
		$date = new DateTime('@' . $this->timestamp);
		$date->setTimeZone(new DateTimeZone('America/New_York'));
		return $date->format('n/d/y g:i a T');
	}

	public function getTitleHTML() {
		return self::formatLines([
			'<span class="title">'
				. htmlspecialchars($this->title)
				. '</span>',
		]);
	}

	public function getUserTripHTML($anon_full_word = false) {
		$lines = [];
	
		if ($this->username) {
			$lines[] = '<span class="user">'
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
					. '">',
				'<span class="trip">'
					. htmlspecialchars('!!!' . $this->trip)
					. '</span>'
					. '<span class="count">'
					. '(' . htmlspecialchars($this->trip_count) . ')'
					. '</span>',
				'</a>',
			]);
		}

		return self::formatLines($lines);
	}

	public function getUIDHTML() {
		$r = hexdec(substr($this->uid, 0, 2));
		$g = hexdec(substr($this->uid, 2, 2));
		$b = hexdec(substr($this->uid, 4, 2));
		$class = ($r*.2126 + $g*.7152 + $b*.0722) > 128 ? 'light' : 'dark';

		return self::formatLines([
			'<a class="uid-link ' . $class . '"'
				. ' style="background: #' . htmlspecialchars($this->uid) . '"'
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
		$date = $this->getReadableDate();
		return self::formatLines([
			'<span class="date"'
				. ' data-ts="' . htmlspecialchars($this->timestamp) . '"'
				. ' title="' . htmlspecialchars($date) . '">'
				. $date
				. '</span>',
		]);
	}

	public function getSizeHTML() {
		$size = number_format(mb_strlen($this->content));
		return self::formatLines([
			'<span class="size">'
				. htmlspecialchars($size)
				. '</span>',
		]);
	}
}
