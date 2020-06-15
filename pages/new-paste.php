<?php

if ($route_params['clone_paste_id']) {
	$q_clone = $db->prepare("
		select content
		from pastes
		where id = ?
		and deleted = 0
	");
	$q_clone->bind_param('s', $route_params['clone_paste_id']);
	$q_clone->execute();
	$q_clone = $q_clone->get_result();
	if (!$q_clone->num_rows) {
		fail(400, 'Invalid clone ID');
	}
	$paste = $q_clone->fetch_assoc();
	$content = $paste['content'];
} else {
	$content = '';
}

page_header('New Paste', [
	'paste_form' => true,
	'clone_paste_id' => $route_params['clone_paste_id'],
]);

echo '<div id="lines"></div>';
echo '<textarea id="paste" name="paste" maxlength="90000">';
echo htmlspecialchars($content);
echo '</textarea>';

page_footer();
