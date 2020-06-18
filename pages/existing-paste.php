<?php

$paste = Paste::load($route_params['paste_id']);
if (!$paste) {
	fail(400, 'Invalid paste ID');
}

if ($route_params['format'] === 'raw' || $route_params['format'] === 'download') {
	header('Content-Type: text/plain');
	if ($route_params['format'] === 'download') {
		$filename = preg_replace('@[^a-zA-Z0-9_]+@', '-', $paste->title)
			. '_' . $paste->id . '.txt';
		header('Content-Disposition: attachment; filename="' . $filename . '"');
	}
	run_hooks('req_end', 200);
	die($paste->content);
}

page_header('Paste: ' . $paste->title, ['paste' => $paste]);

echo '<textarea id="paste" name="paste" maxlength="90000" readonly>';
echo htmlspecialchars($paste->content);
echo '</textarea>';

page_footer();
