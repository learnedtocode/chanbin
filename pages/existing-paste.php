<?php

$q_paste = $db->prepare("
	select *
	from pastes
	where id = ?
	and deleted = 0
");
error_log(json_encode($route_params));
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

$paste['uid'] = run_hooks('ip_hash_to_display', $paste['ip_hash']);
page_header('Paste: ' . $paste['title'], ['paste' => $paste]);

echo '<div id="lines"></div>';
echo '<textarea id="paste" name="paste" maxlength="90000" readonly>';
echo htmlspecialchars($paste['content']);
echo '</textarea>';

page_footer();
