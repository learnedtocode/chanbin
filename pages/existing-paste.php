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

page_header('Paste: ' . $paste['title']);

echo '<div id="lines"></div>';
echo '<textarea id="paste" name="paste" maxlength="90000" readonly>';
echo htmlspecialchars($paste['content']);
echo '</textarea>';

page_footer();
