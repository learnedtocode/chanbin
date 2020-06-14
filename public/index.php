<?php

require dirname(__DIR__) . '/config.php';

$route = strtok($_SERVER['REQUEST_URI'], '?');

$route_c = '/' . trim($route, '/');
if ($route_c !== $route) {
	$qs = ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
	header('HTTP/1.1 302 Found');
	header('Location: ' . $route_c . $qs);
	die('Redirecting...');
}

if ($route === '/debug-' . $config['debug_password']) {
	echo '<pre>';
	foreach ($_SERVER as $name => $value) echo "$name: $value\n";
	echo '</pre>';

} else if ($route === '/') {
	header('Cache-Control: no-store');
	require dirname(__DIR__) . '/pages/coming-soon.php';

} else {
	header('HTTP/1.1 404 Not Found');
	die('<h2>Page Not Found</h2>');
}
