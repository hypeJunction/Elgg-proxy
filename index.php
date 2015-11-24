<?php

global $CONFIG;
if (!isset($CONFIG)) {
	$CONFIG = new \stdClass;
}
$CONFIG->boot_complete = false;

// This will be overridden by the DB value but may be needed before the upgrade script can be run.
$CONFIG->default_limit = 10;

$engine_dir = dirname(dirname(__DIR__));
$paths = array(
	$engine_dir . '/engine/settings.php',
	$engine_dir . '/engine/load.php',
	$engine_dir . '/mod/proxy/vendor/autoload.php',
);

foreach ($paths as $path) {
	if (is_readable($path)) {
		require_once $path;
	}
}

elgg_trigger_event('boot', 'system');

$CONFIG->boot_complete = true;

try {
	(new Elgg\FileService\File)->serveFromURL(current_page_url());
} catch (Exception $e) {
	header("HTTP/1.1 400 Bad Request");
	exit;
}