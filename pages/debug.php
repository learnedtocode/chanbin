<?php

echo '<pre>';
$csrf_token = run_hooks('csrf_token');
echo json_encode([
	'ip_hash_display' => $ip_hash_display,
	'ip_hash_full' => $ip_hash_full,
	'csrf' => $csrf_token,
	'csrf_decode' => run_hooks('csrf_decode', $csrf_token),
], JSON_PRETTY_PRINT);
echo "\n\n";
foreach ($_SERVER as $name => $value) echo "$name: $value\n";
echo '</pre>';
