<?php

$q_paste = $db->prepare("
	select id, username, trip, ip_hash, timestamp,
		title, content, deleted,
		is_mod_action, flags, cloned_from, times_viewed
	from pastes
	where id = ?
	and deleted = 0
");
$q_paste->bind_param('s', $route_params['paste_id']);
$q_paste->execute();
$q_paste = $q_paste->get_result();
if (!$q_paste->num_rows) {
	fail(400, 'Invalid paste ID');
}
$paste = $q_paste->fetch_assoc();

if ($route_params['format'] === 'raw' || $route_params['format'] === 'download') {
	header('Content-Type: text/plain');
	if ($route_params['format'] === 'download') {
		$filename = preg_replace('@[^a-zA-Z0-9_]+@', '-', $paste['title'])
			. '_' . $paste['id'] . '.txt';
		header('Content-Disposition: attachment; filename="' . $filename . '"');
	}
	die($paste['content']);
}

$q_count = $db->prepare("
	select count(*) as uid_count
	from pastes
	where ip_hash = ?
	and deleted = 0
");
$q_count->bind_param('s', $paste['ip_hash']);
$q_count->execute();
$q_count = $q_count->get_result();
$count = $q_count->fetch_assoc();
$paste['uid_count'] = $count['uid_count'];

if ($paste['trip']) {
	$q_count = $db->prepare("
		select count(*) as uid_count
		from pastes
		where trip = ?
		and deleted = 0
	");
	$q_count->bind_param('s', $paste['trip']);
	$q_count->execute();
	$q_count = $q_count->get_result();
	$count = $q_count->fetch_assoc();
	$paste['trip_count'] = $count['uid_count'];
} else {
	$paste['trip_count'] = 0;
}

$paste['uid'] = run_hooks('ip_hash_to_display', $paste['ip_hash']);
page_header('Paste: ' . $paste['title'], ['paste' => $paste]);

echo '<div id="lines"></div>';
echo '<textarea id="paste" name="paste" maxlength="90000" readonly>';
echo htmlspecialchars($paste['content']);
echo '</textarea>';

page_footer();
