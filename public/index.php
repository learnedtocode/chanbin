<?php

if (isset($_GET['showheaders'])) {
	echo '<pre>';
	foreach ($_SERVER as $name => $value) echo "$name: $value\n";
	echo '</pre>';
}

require __DIR__ . '/coming-soon.php';
