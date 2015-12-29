<?php

$plugin_root = __DIR__;
$root = dirname(dirname($plugin_root));
$alt_root = dirname(dirname(dirname($root)));

$autoloaders = array(
	"$plugin_root/vendor/autoload.php",
	"$root/vendor/autoload.php",
	"$alt_root/vendor/autoload.php",
);

foreach ($autoloaders as $autoloader) {
	if (is_readable($autoloader)) {
		require_once $autoloader;
	}
}

try {
	\Elgg\Application::start();
	(new Elgg\Application\ServeFileHandler(_elgg_services()->config, $_SERVER))->handleRequest($_GET['__uri']);
} catch (Exception $e) {
	header("HTTP/1.1 400 Bad Request");
	exit;
}