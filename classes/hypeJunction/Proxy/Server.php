<?php

namespace hypeJunction\Proxy;

/**
 * Server class
 * @access private
 */
class Server {

	/**
	 * Serve URI
	 *
	 * @param string $uri URI
	 * @return void
	 */
	public function serve($uri = '') {

		if (headers_sent()) {
			return;
		}
		
		if (!preg_match('~/e(\d+)/a(\d+)/l(\d+)/d([ia])/k([cs])/([a-zA-Z0-9\-_]+)/(.*)$~', $uri, $m)) {
			header("HTTP/1.1 400 Bad Request");
			exit;
		}

		list(, $expires, $access_id, $last_updated, $disposition, $key_type, $mac, $path) = $m;

		if ($expires && $expires < time()) {
			header("HTTP/1.1 403 Forbidden");
			exit;
		}

		$etag = md5($last_updated);
		if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == "\"$etag\"") {
			header("HTTP/1.1 304 Not Modified");
			exit;
		}

		$key = $key_type == 'c' ? $this->getSessionCookie() : $this->getSiteSecret();
		$hmac_data = array(
			'expires' => (int) $expires,
			'last_updated' => (int) $last_updated,
			'access_id' => (int) $access_id,
			'disposition' => $disposition,
			'path' => $path,
			'key_type' => $key_type,
		);
		ksort($hmac_data);

		$hmac = _elgg_services()->crypto->getHmac($hmac_data, 'sha256', $key);
		if (!$hmac->matchesToken($mac)) {
			header("HTTP/1.1 403 Forbidden");
			exit;
		}

		$dataroot = _elgg_services()->config->get('dataroot');
		if (empty($dataroot)) {
			header("HTTP/1.1 404 Not Found");
			exit;
		}

		$filenameonfilestore = "{$dataroot}{$path}";

		if (!is_readable($filenameonfilestore)) {
			header("HTTP/1.1 404 Not Found");
			exit;
		}

		$mime = 'application/otcet-stream';
		if (function_exists('finfo_file') && defined('FILEINFO_MIME_TYPE')) {
			$resource = finfo_open(FILEINFO_MIME_TYPE);
			if ($resource) {
				$mime = finfo_file($resource, $filenameonfilestore);
			}
		}

		$filesize = filesize($filenameonfilestore);
		header("Content-Length: $filesize");

		header("Content-type: $mime");
		if ($disposition == 'i') {
			header("Content-disposition: inline");
		} else {
			$basename = basename($filenameonfilestore);
			header("Content-disposition: attachment; filename='$basename'");
		}

		if ($expires) {
			$expires_str = gmdate('D, d M Y H:i:s \G\M\T', $expires);
		} else {
			$expires_str = gmdate('D, d M Y H:i:s \G\M\T', strtotime("+3 years"));
		}

		header('Expires: ' . $expires_str, true);
		header("Pragma: public");
		header("Cache-Control: public");
		header("ETag: \"$etag\"");

		readfile($filenameonfilestore);
		exit;
	}

	/**
	 * Get current session cookie
	 * @return string
	 */
	private function getSessionCookie() {
		$global_cookies_config = _elgg_services()->config->get('cookies');
		$cookie_config = $global_cookies_config['session'];
		$cookie_name = $cookie_config['name'];
		return _elgg_services()->request->cookies->get($cookie_name, '');
	}

	/**
	 * Get site secret
	 * @return string
	 */
	private function getSiteSecret() {
		return get_site_secret();
	}

}
