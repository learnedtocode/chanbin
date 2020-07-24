<?php

require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/inc/Paste.php';
require dirname(__DIR__) . '/inc/functions.php';

ini_set('error_log', dirname(__DIR__) . '/errors.log');
ini_set('display_errors', false);
mb_internal_encoding('UTF-8');

foreach (glob(dirname(__DIR__) . '/plugins/*.php') as $plugin) {
	require $plugin;
}

$ip_hash_full = run_hooks('ip_hash_full');
$ip_hash_display = run_hooks('ip_hash_to_display', $ip_hash_full);

$db = @new mysqli(
	$config['db']['host'],
	$config['db']['user'],
	$config['db']['password'],
	$config['db']['database']
);
if ($db && !$db->connect_errno) {
	$db->set_charset('utf8mb4');
} else {
	error_log('MySQL connection failed: ' . $db->connect_error);
	fail(500, 'A server error occurred');
}

$route = strtok($_SERVER['REQUEST_URI'], '?');

$route_c = '/' . trim($route, '/');
$route_c = preg_replace('@/+@', '/', $route_c);
if ($route_c !== $route) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    if ($qs) $qs = '?' . $qs;
	redirect($route_c . $qs);
}

$route_params = [];

if ($route === '/debug-' . $config['secrets']['debug']) {
	header('Cache-Control: no-store');
	require dirname(__DIR__) . '/pages/debug.php';

} else if ($route === '/') {
	header('Cache-Control: no-store');
    $route_params['clone_paste_id'] = null;
	require dirname(__DIR__) . '/pages/new-paste.php';

} else if ($route === '/about') {
	header('Cache-Control: max-age=3600');
	require dirname(__DIR__) . '/pages/about.php';

} else if ($route === '/recent') {
	header('Cache-Control: max-age=6');
    $route_params['list_type'] = 'recent';
    require dirname(__DIR__) . '/pages/list.php';

} else if (preg_match('@^/uid/([a-zA-Z0-9]{12})$@', $route, $matches)) {
	header('Cache-Control: max-age=6');
    $route_params['list_type'] = 'uid';
    $route_params['list_ip_hash'] = $matches[1];
    require dirname(__DIR__) . '/pages/list.php';

} else if (preg_match('@^/trip/([a-zA-Z0-9]{12})$@', $route, $matches)) {
	header('Cache-Control: max-age=6');
    $route_params['list_type'] = 'trip';
    $route_params['list_trip'] = $matches[1];
    require dirname(__DIR__) . '/pages/list.php';

} else if (preg_match('@^/(paste|raw|download)/([a-zA-Z0-9]{9})$@', $route, $matches)) {
	header('Cache-Control: max-age=60');
    $route_params['format'] = $matches[1];
    $route_params['paste_id'] = $matches[2];
    require dirname(__DIR__) . '/pages/existing-paste.php';

} else if (preg_match('@^/clone/([a-zA-Z0-9]{9})$@', $route, $matches)) {
	header('Cache-Control: max-age=60');
    $route_params['clone_paste_id'] = $matches[1];
	require dirname(__DIR__) . '/pages/new-paste.php';

} else if ($route === '/send') {
	header('Cache-Control: no-store');
	require dirname(__DIR__) . '/pages/send.php';

} else if (preg_match('@^/api/info/([a-zA-Z0-9]{9})$@', $route, $matches)) {
	header('Cache-Control: no-store');
    $route_params['paste_id'] = $matches[1];
	require dirname(__DIR__) . '/pages/api-info.php';

} else {
	fail(404, 'Page not found');
}

run_hooks('req_end', 200);
