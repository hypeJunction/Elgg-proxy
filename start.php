<?php

/**
 * File server
 *
 * @author Ismayil Khayredinov <info@hypejunction.com>
 * @copyright Copyright (c) 2015, Ismayil Khayredinov
 */
require_once __DIR__ . '/autoloader.php';

if (!function_exists('elgg_get_download_url')) {

	/**
	 * Returns file's download URL
	 *
	 * @param \ElggFile $file       File object or entity
	 * @param bool      $use_cookie Limit URL validity to current session only
	 * @param string    $expires    URL expiration, as a string suitable for strtotime()
	 * @return string
	 */
	function elgg_get_download_url(\ElggFile $file, $use_cookie = true, $expires = '+2 hours') {
		$file_svc = new Elgg\FileService\File();
		$file_svc->setFile($file);
		$file_svc->setExpires($expires);
		$file_svc->setDisposition('attachment');
		$file_svc->bindSession($use_cookie);
		return $file_svc->getURL();
	}

}

if (!function_exists('elgg_get_inline_url')) {

	/**
	 * Returns file's URL for inline display
	 * Suitable for displaying cacheable resources, such as user avatars
	 *
	 * @param \ElggFile $file       File object or entity
	 * @param bool      $use_cookie Limit URL validity to current session only
	 * @param string    $expires    URL expiration, as a string suitable for strtotime()
	 * @return string
	 */
	function elgg_get_inline_url(\ElggFile $file, $use_cookie = false, $expires = '+1 year') {
		$file_svc = new Elgg\FileService\File();
		$file_svc->setFile($file);
		$file_svc->setExpires($expires);
		$file_svc->setDisposition('inline');
		$file_svc->bindSession($use_cookie);
		return $file_svc->getURL();
	}

}