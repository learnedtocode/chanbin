<?php

if ($route_params['clone_paste_id']) {
	$paste = Paste::load($route_params['clone_paste_id']);
	if (!$paste) {
		fail(400, 'Invalid clone ID');
	}
	$content = $paste->content;
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
