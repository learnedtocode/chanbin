<?php

if (
	$_SERVER['REQUEST_METHOD'] !== 'POST' ||
	!run_hooks('csrf_validate', $_POST['csrf'] ?? null)
) {
	fail(400, 'Page expired', true);
}

$username = strtok(clean_ascii($_POST['username'] ?? ''), '#');
$password = $_POST['password'] ?? '';
$paste = [
	'title' => clean_ascii($_POST['title'] ?? ''),
	'username' => $username,
	'trip' => run_hooks('password_to_trip', $password),
	'content' => $_POST['paste'] ?? '',
	'cloned_from' => $_POST['cloned_from'] ?? '',
];

$errors = [];

if (strlen($paste['title']) < 3) $errors[] = 'Title is too short';
if (strlen($paste['title']) > 60) $errors[] = 'Title is too long';
if (strlen($paste['username']) > 18) $errors[] = 'Username is too long';
if (strlen($paste['content']) < 3) $errors[] = 'Paste content is too short';
if (strlen($paste['content']) > 90000) $errors[] = 'Paste content is too long';
// https://stackoverflow.com/questions/6723562
if (!preg_match('@@u', $paste['content'])) $errors[] = 'Paste content is invalid';

if (!count($errors) && $paste['cloned_from']) {
	if (!preg_match('@^[a-zA-Z0-9]{9}$@', $paste['cloned_from'])) {
		$errors[] = 'Invalid clone ID';
	} else {
		$q_clone = $db->prepare("
			select id
			from pastes
			where id = ?
			and deleted = 0
		");
		$q_clone->bind_param('s', $paste['cloned_from']);
		$q_clone->execute();
		$q_clone = $q_clone->get_result();
		if (!$q_clone->num_rows) {
			$errors[] = 'Invalid clone ID';
		}
	}
}

$errors = run_hooks('new_paste_errors_1', $errors, $paste);

if (!count($errors)) {
	$q_limit = $db->prepare("
		select reason_text, blocked_until
		from limits
		where ip_hash = ?
		and (blocked_until >= unix_timestamp() or blocked_until = 0)
	");
	$q_limit->bind_param('s', $ip_hash_full);
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

$errors = run_hooks('new_paste_errors_2', $errors, $paste);

if (count($errors)) {
	fail(
		400, 'Problems with your paste:', true,
		"<ul>\n<li>" . implode('</li>\n<li>', $errors) . "</li>\n</ul>"
	);
}

$paste['id'] = run_hooks('new_paste_id');
$paste['is_mod_action'] = 0;
$paste['flags'] = 0;
if (!$paste['username']) $paste['username'] = null;
if (!$paste['trip']) $paste['trip'] = null;
if (!$paste['cloned_from']) $paste['cloned_from'] = null;

$paste = run_hooks('paste_data', $paste);

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
	$paste['id'],
	$paste['username'],
	$paste['trip'],
	$ip_hash_full,
	$paste['title'],
	$paste['content'],
	$paste['is_mod_action'],
	$paste['flags'],
	$paste['cloned_from']
);
if (!$q_newpaste->execute()) {
	fail(500, $q_newpaste->error, true);
}

redirect('/paste/' . $paste['id']);
