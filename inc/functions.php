<?php

$page_paste_form = false;

function page_header($title, $paste_form = false, $clone_paste_id = null) {
	global $route, $page_paste_form;
	$page_paste_form = $paste_form;

	$app_css_filename = trim(file_get_contents(
		dirname(__DIR__) . '/zzz/app-css-filename.txt')
	);
	$app_js_filename = trim(file_get_contents(
		dirname(__DIR__) . '/zzz/app-js-filename.txt')
	);
	$page_title =
		htmlspecialchars(run_hooks('site_name', 'chanbin'))
		. ' - '
		. htmlspecialchars($title);

	$nav_links = [
		'new paste' => '/',
		'recent' => '/recent',
		'about' => '/about',
	];
	$nav_menu = '';
	foreach ($nav_links as $text => $href) {
		if ($route === $href) {
			continue;
		}
		if ($nav_menu) $nav_menu .= ' | ';
		$nav_menu .= '<a href="' . $href . '">' . $text . '</a>';
	}

	$paste_status = 'new paste:';
	if ($clone_paste_id) {
		$paste_status =
			'clone of <a href="/paste/' . htmlspecialchars($clone_paste_id)
			. '">' . htmlspecialchars($clone_paste_id) . '</a>:';
	}

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
<?php if ($paste_form) { ?>
	<body>
		<form method="post" action="/send">
			<div id="header">
				<?php run_hooks('logo'); ?>
				<div id="logotext"><?php echo run_hooks('logotext', 'chanbin'); ?></div>
				<div id="controls">
					<span id="description"><?php echo $paste_status; ?></span>
					<input type="text" id="title" name="title" minlength="3" maxlength="18" placeholder="Title">
					<input type="text" id="username" name="username" maxlength="18" placeholder="Username">
					<input type="password" id="password" name="password" maxlength="99" placeholder="Password">
					<input type="submit" id="send" value="SEND">
					<input type="hidden" name="clone_from" value="<?php echo $clone_paste_id ? htmlspecialchars($clone_paste_id) : '' ?>">
					<input type="hidden" name="csrf" value="<?php echo htmlspecialchars(run_hooks('csrf_token')); ?>">
				</div>
				<div id="top-menu"><?php echo $nav_menu; ?></div>
			</div>
			<div id="content">
<?php } else { ?>
	<body>
		<div id="header">
			<?php run_hooks('logo'); ?>
			<div id="logotext"><?php echo run_hooks('logotext', 'chanbin'); ?></div>
			<div id="top-menu"><?php echo $nav_menu; ?></div>
		</div>
		<div id="content">
<?php }
}

function page_footer() {
	global $page_paste_form;
?>
			</div>
<?php if ($page_paste_form) { ?>
		</form>
<?php } ?>
	</body>
</html><!-- <?php echo date('Y-m-d g:i:s'); ?> -->
<?php
}

function fail($code, $message, $text = false) {
	switch ($code) {
		case 400:
			header('HTTP/1.1 400 Bad Request');
			break;
		case 500:
			header('HTTP/1.1 500 Internal Server Error');
			break;
	}
	page_header($message);
	echo '<div id="page-text">';
	echo '<h2 class="error">' . htmlentities($message) . '</h2>';
	if ($text) {
		echo '<p>Click your browser\'s Back button and try again.</p>';
	}
	echo '</div>';
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
	echo '<div id="page-text">';
	echo '<h2 class="error">You are being redirected</h2>';
	echo '</div>';
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
