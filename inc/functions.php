<?php

function page_header($title) {
	$app_css_filename = trim(file_get_contents(
		dirname(__DIR__) . '/zzz/app-css-filename.txt')
	);
	$app_js_filename = trim(file_get_contents(
		dirname(__DIR__) . '/zzz/app-js-filename.txt')
	);
	$page_title =
		htmlspecialchars($title)
		. ' - '
		. htmlspecialchars(run_hooks('site_name', 'chanbin'));
?>
<!doctype html>
<html>
	<head>
		<title><?php echo $page_title; ?></title>
		<link rel="stylesheet" type="text/css" href="/assets/<?php echo $app_css_filename; ?>">
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<script src="/assets/<?php echo $app_js_filename; ?>"></script>
	</head>
	<body>
		<div id="header">
			<?php run_hooks('logo'); ?>
			<div id="logotext"><?php echo run_hooks('logotext', 'chanbin'); ?></div>
		</div>
		<div id="content">
<?php
}

function page_footer() {
?>
		</div>
	</body>
</html><!-- <?php echo date('Y-m-d g:i:s'); ?> -->
<?php
}

function fail($code, $message) {
	switch ($code) {
		case 400:
			header('HTTP/1.1 400 Bad Request');
			break;
		case 500:
			header('HTTP/1.1 500 Internal Server Error');
			break;
	}
	page_header($message);
	echo '<h2>' . htmlentities($message) . '</h2>';
	page_footer();
	run_hooks('req_end', $code, $message);
	die();
}

function redirect($location, $code = 302) {
	switch ($code) {
		case 302:
			header('HTTP/1.1 302 Found');
			break;
	}
	page_header('Location: ' . $location);
	echo '<h2>You are being redirected</h2>';
	page_footer();
	run_hooks('req_end', $code, $location);
	die();
}

$hooks = [];

function add_hook($name, $fn) {
	global $hooks;
	if (!isset($hooks[$name])) $hooks[$name] = [];
	$hooks[$name][] = $fn;
}

function run_hooks($name, ...$args) {
	global $hooks;
	$value = $args[0] ?? null;
	foreach (($hooks[$name] ?? []) as $fn) {
		$value = call_user_func_array($fn, $args);
	}
	return $value;
}
