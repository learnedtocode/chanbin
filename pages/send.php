<?php

if (
	$_SERVER['REQUEST_METHOD'] !== 'POST' ||
	!run_hooks('csrf_validate', $_POST['csrf'] ?? null)
) {
	fail(400, 'Page expired', true);
}

$paste = [
	'title' => clean_ascii($_POST['title'] ?? ''),
	'username' => clean_ascii($_POST['username'] ?? ''),
	'trip' => run_hooks('password_to_trip', $_POST['password'] ?? null),
	'content' => $_POST['paste'] ?? '',
	'cloned_from' => $_POST['cloned_from'] ?? '',
];

$errors = [];

if (strlen($paste['title']) < 3) $errors[] = 'Title is too short';
if (strlen($paste['title']) > 18) $errors[] = 'Title is too long';
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
