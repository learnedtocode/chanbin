<?php

if (
	$_SERVER['REQUEST_METHOD'] !== 'POST' ||
	!run_hooks('csrf_validate', $_POST['csrf'] ?? null)
) {
	fail(400, 'Page expired', true);
}

$username = strtok(clean_ascii($_POST['username'] ?? ''), '#');
$password = $_POST['password'] ?? '';
$paste_data = [
	'title' => clean_ascii($_POST['title'] ?? ''),
	'username' => $username,
	'trip' => run_hooks('password_to_trip', $password),
	'ip_hash' => $ip_hash_full,
	'content' => $_POST['paste'] ?? '',
	'cloned_from' => $_POST['cloned_from'] ?? '',
];

$errors = [];
$errors_try_again = true;

if (strlen($paste_data['title']) < 3) $errors[] = 'Title is too short';
if (strlen($paste_data['title']) > 60) $errors[] = 'Title is too long';
if (strlen($paste_data['username']) > 18) $errors[] = 'Username is too long';
if (strlen($paste_data['content']) < 3) $errors[] = 'Paste content is too short';
if (strlen($paste_data['content']) > 90000) $errors[] = 'Paste content is too long';
// https://stackoverflow.com/questions/6723562
if (!preg_match('@@u', $paste_data['content'])) $errors[] = 'Paste content is invalid';

if (!count($errors) && $paste_data['cloned_from']) {
	if (!preg_match('@^[a-zA-Z0-9]{9}$@', $paste_data['cloned_from'])) {
		$errors[] = 'Invalid clone ID';
	} else {
		$q_clone = $db->prepare("
			select id
			from pastes
			where id = ?
			and deleted = 0
		");
		$q_clone->bind_param('s', $paste_data['cloned_from']);
		$q_clone->execute();
		$q_clone = $q_clone->get_result();
		if (!$q_clone->num_rows) {
			$errors[] = 'Invalid clone ID';
		}
	}
}

$errors = run_hooks('new_paste_errors_1', $errors, $paste_data);

if (!count($errors)) {
	$q_limit = $db->prepare("
		select reason_text, blocked_until
		from limits
		where ip_hash = ?
		and (blocked_until >= unix_timestamp() or blocked_until = 0)
	");
	$q_limit->bind_param('s', $paste_data['ip_hash']);
	$q_limit->execute();
	$q_limit = $q_limit->get_result();
	if ($q_limit->num_rows) {
		$limit = $q_limit->fetch_assoc();
		if ($limit['blocked_until'] === 0) {
			$errors = [
				'Inside you there are two wolves',
				'One of them is banned',
				'The other one is banned',
				'<strong class="error">You are banned</strong>',
				'Reason: ' . htmlentities($limit['reason_text']),
			];
			$errors_try_again = false;
		} else {
			$errors[] = 'You are creating too many pastes, please wait a bit and try again';
		}
	}
}

function check_password($username, $password) {
	global $db;

	if (!$password) {
		return true;
	}
	if (strlen($password) < 6) {
		return 'Your password is too short. Please pick a strong, unique password.';
	}
	if (levenshtein($username, $password) < 6) {
		return 'Your password is too similar to your username. Please pick a strong, unique password.';
	}

	$password_sha1 = sha1($password);
	$password_sha1_first_5 = substr($password_sha1, 0, 5);
	$hibp = null;

	$q_hibp = $db->prepare("
		select hashes, last_updated
		from hibp_cache
		where first_5 = ?
	");
	$q_hibp->bind_param('s', $password_sha1_first_5);
	$q_hibp->execute();
	$q_hibp = $q_hibp->get_result();
	if ($q_hibp->num_rows) {
		$hibp_test = $q_hibp->fetch_assoc();
		if ($hibp_test['last_updated'] >= time() - 7*24*3600) {
			$hibp = $hibp_test;
		}
	}

	if (!$hibp) { // fetch
		$url = 'https://api.pwnedpasswords.com/range/' . $password_sha1_first_5;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3000);
		$response = curl_exec($ch);
		curl_close($ch);
		if (!$response) {
			error_log('HIBP request FAILED: ' . $url);
			fail(500, 'An internal error occurred', true);
		}
		$hibp = ['hashes' => trim($response)];
		// save to db
		$q_hibp = $db->prepare("
			insert into hibp_cache (first_5, hashes, last_updated)
			values (?, ?, unix_timestamp())
			on duplicate key update first_5 = ?,
				hashes = ?,
				last_updated = unix_timestamp()
		");
		$q_hibp->bind_param(
			'ssss',
			$password_sha1_first_5,
			$hibp['hashes'],
			$password_sha1_first_5,
			$hibp['hashes']
		);
		if (!$q_hibp->execute()) {
			error_log('HIBP save FAILED: ' . $q_hibp->error);
			fail(500, 'An internal error occurred', true);
		}
	}

	$password_sha1_test = strtoupper(substr($password_sha1, 5));
	foreach (explode("\n", $hibp['hashes']) as $line) {
		if (strtok($line, ':') === $password_sha1_test) {
			$breach_count = (int)strtok(':');
			if ($breach_count > 1) {
				error_log("HIBP password BREACHED: $password_sha1_first_5:$breach_count");
				return "That password has been included in $breach_count <a href=\"https://haveibeenpwned.com/\" rel=\"noopener noreferrer\">public data breaches</a>, so it can't be used here. Please pick a strong, unique password.";
			} else {
				error_log("HIBP password WARNING: $password_sha1_first_5:$breach_count");
			}
		}
	}

	return true;
}

if ($password && !count($errors)) {
	$password_check = check_password($username, $password);
	if ($password_check !== true) {
		$errors[] = $password_check;
	}
}

$errors = run_hooks('new_paste_errors_2', $errors, $paste_data);

if (count($errors)) {
	fail(
		400, 'Problems with your paste:', $errors_try_again,
		"<ul>\n<li>" . implode("</li>\n<li>", $errors) . "</li>\n</ul>"
	);
}

$paste_data['id'] = run_hooks('new_paste_id');
$paste_data['is_mod_action'] = 0;
$paste_data['flags'] = 0;
if (!$paste_data['username']) $paste_data['username'] = null;
if (!$paste_data['trip']) $paste_data['trip'] = null;
if (!$paste_data['cloned_from']) $paste_data['cloned_from'] = null;

try {
	$mod_data = Paste::load(null, $paste_data, false)->parseModeratorAction();
} catch (Exception $e) {
	fail(
		400, 'Problems with your paste:', true,
		'<p>' . htmlspecialchars($e->getMessage()) . '</p>'
	);
}
if ($mod_data) {
	$paste_data['is_mod_action'] = 1;
	try {
		$paste_data['content'] = Paste::annotateModeratorActionContent(
			$paste_data['content'],
			$mod_data
		);
	} catch (Exception $e) {
		fail(
			500, 'An internal error occurred:', true,
			'<p>' . htmlspecialchars($e->getMessage()) . '</p>'
		);
	}
	if ($mod_data['actions']['wipe']) {
		$ip_hashes = array_unique(array_map(function($paste) {
			// Sanitization shouldn't be necessary, but doesn't hurt
			return preg_replace('@[^a-zA-Z0-9]@', '', $paste->ip_hash);
		}, array_values($mod_data['pastes'])));
		$ip_hashes_params = implode(',', array_fill(0, count($ip_hashes), '?'));
		$q_wipe = $db->prepare("
			update pastes
			set deleted = 1
			where ip_hash in ($ip_hashes_params)
		");
		$q_wipe->bind_param(str_repeat('s', count($ip_hashes)), ...$ip_hashes);
		$q_wipe->execute();
	} else if ($mod_data['actions']['delete']) {
		$paste_ids = array_unique(array_map(function($paste_id) {
			// Sanitization shouldn't be necessary, but doesn't hurt
			return preg_replace('@[^a-zA-Z0-9]@', '', $paste_id);
		}, $mod_data['paste_ids']));
		$paste_ids_params = implode(',', array_fill(0, count($paste_ids), '?'));
		$q_delete = $db->prepare("
			update pastes
			set deleted = 1
			where id in ($paste_ids_params)
		");
		$q_delete->bind_param(str_repeat('s', count($paste_ids)), ...$paste_ids);
		if (!$q_delete->execute()) {
			fail(500, $q_delete->error, true);
		}
	}
	if ($mod_data['actions']['ban']) {
		$ip_hashes = array_unique(array_map(function($paste) {
			// Sanitization shouldn't be necessary, but doesn't hurt
			return preg_replace('@[^a-zA-Z0-9]@', '', $paste->ip_hash);
		}, array_values($mod_data['pastes'])));
		foreach ($ip_hashes as $ip_hash) {
			$q_ban = $db->prepare("
				insert into limits (
					ip_hash, blocked_since, blocked_until,
					reason_text, mod_paste_id
				) values (
					?, unix_timestamp(), 0, ?, ?
				) on duplicate key update
					ip_hash = ?,
					blocked_since = unix_timestamp(),
					blocked_until = 0,
					reason_text = ?,
					mod_paste_id = ?
			");
			$q_ban->bind_param(
				'sss' . 'sss',
				$ip_hash,
				$mod_data['reason'],
				$paste_data['id'],
				$ip_hash,
				$mod_data['reason'],
				$paste_data['id']
			);
			if (!$q_ban->execute()) {
				fail(500, $q_ban->error, true);
			}
		}
	}
}

$paste_data = run_hooks('paste_data', $paste_data);

$q_newpaste = $db->prepare("
	insert into pastes (
		id, username, trip, ip_hash,
		timestamp, title, content,
		is_mod_action, flags, cloned_from
	) values (
		?, ?, ?, ?,
		unix_timestamp(), ?, ?,
		?, ?, ?
	)
");
$q_newpaste->bind_param(
	'ssss' . 'ss' . 'iis',
	$paste_data['id'],
	$paste_data['username'],
	$paste_data['trip'],
	$paste_data['ip_hash'],
	$paste_data['title'],
	$paste_data['content'],
	$paste_data['is_mod_action'],
	$paste_data['flags'],
	$paste_data['cloned_from']
);
if (!$q_newpaste->execute()) {
	fail(500, $q_newpaste->error, true);
}

redirect('/paste/' . $paste_data['id']);
