<?php

require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/inc/functions.php';

ini_set('error_log', dirname(__DIR__) . '/errors.log');
ini_set('display_errors', false);

foreach (glob(dirname(__DIR__) . '/plugins/*.php') as $plugin) {
	require $plugin;
}

$db = @new mysqli(
	$config['db']['host'],
	$config['db']['user'],
	$config['db']['password'],
	$config['db']['database']
);
if ($db && !$db->connect_errno) {
	$db->set_charset('utf8');
} else {
	error_log('MySQL connection failed: ' . $db->connect_error);
	fail(500, 'A server error occurred');
}

$route = strtok($_SERVER['REQUEST_URI'], '?');

$route_c = '/' . trim($route, '/');
$route_c = preg_replace('@/+@', '/', $route_c);
if ($route_c !== $route) {
	$qs = ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
	redirect($route_c . $qs);
}

$ip_hash_full = run_hooks('ip_hash_full');
$ip_hash_display = run_hooks('ip_hash_to_display', $ip_hash_full);

$route_params = [];

if ($route === '/debug-' . $config['secrets']['debug']) {
	header('Cache-Control: no-store');
	require dirname(__DIR__) . '/pages/debug.php';

} else if ($route === '/') {
	header('Cache-Control: no-store');
	require dirname(__DIR__) . '/pages/new-paste.php';

} else if ($route === '/about') {
	header('Cache-Control: max-age=3600');
	require dirname(__DIR__) . '/pages/about.php';

} else if ($route === '/send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	header('Cache-Control: no-store');
	require dirname(__DIR__) . '/pages/send.php';

} else {
	fail(404, 'Page not found');
}
