<?php

/**
 * File server
 *
 * @author Ismayil Khayredinov <info@hypejunction.com>
 * @copyright Copyright (c) 2015, Ismayil Khayredinov
 */
require_once __DIR__ . '/autoloader.php';

/**
 * Returns HMAC-signed URL for serving the file
 *
 * @param \ElggFile   $file        File entity or filestore object to serve
 * @param int         $expire      Validitiy of the URL in seconds (default: no expiration)
 * @param string      $disposition Content disposition ('inline' or 'attachment'). Default determined by mimetype
 * @param bool        $persistent  Persist URL validity across multiple user sessions
 * @return string
 */
function elgg_proxy_get_url(\ElggFile $file, $expire = 0, $disposition = null, $persistent = false) {
	$proxy = new hypeJunction\Proxy\Proxy($file);
	return $proxy->getURL($expire, $disposition, $persistent);
}