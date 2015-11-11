<?php

$root = __DIR__;
$paths = array(
	dirname(dirname($root)) . '/engine/settings.php',
	dirname(dirname($root)) . '/vendor/autoload.php',
	$root . '/vendor/autoload.php',
);

foreach ($paths as $path) {
	if (is_readable($path)) {
		require_once $path;
	}
}

global $CONFIG;

$server = new \hypeJunction\Proxy\Server($CONFIG, $_GET['__uri']);
$server->serve();
