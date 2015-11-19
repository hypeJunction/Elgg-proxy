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
 * @param bool        $use_cookie  Use current session cookie in HMAC signatures to limit URL validity to current session only
 * @return string
 */
function elgg_proxy_get_url(\ElggFile $file, $expire = 0, $disposition = 'attachment', $use_cookie = true) {
	$proxy = new hypeJunction\Proxy\Proxy($file);
	return $proxy->getURL($expire, $disposition, $use_cookie);
}
