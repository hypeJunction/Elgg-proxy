<?php

namespace Elgg\FileService;

/**
 * File service
 * @access private
 */
class File {

	const DISPOSITION_INLINE = 'inline';
	const DISPOSITION_ATTACHMENT = 'attachment';
	const DEFAULT_TTL = 7200;
	
	/**
	 * @var \ElggFile 
	 */
	private $file;

	/**
	 * @var int
	 */
	private $expires;

	/**
	 * @var string
	 */
	private $disposition = self::DISPOSITION_ATTACHMENT;

	/**
	 * @var bool
	 */
	private $use_cookie = true;

	/**
	 * Set file object
	 *
	 * @param \ElggFile $file File object
	 * @return void
	 */
	public function setFile(\ElggFile $file) {
		$this->file = $file;
	}

	/**
	 * Sets URL expiration
	 *
	 * @param int $expires String suitable for strtotime()
	 * @return void
	 */
	public function setExpires($expires = '+2 hours') {
		$this->expires = strtotime($expires);
	}

	/**
	 * Sets content disposition
	 *
	 * @param string $disposition Content disposition (inline or attachment)
	 * @return void
	 */
	public function setDisposition($disposition = self::DISPOSITION_ATTACHMENT) {
		if ($disposition == self::DISPOSITION_INLINE) {
			$this->disposition = self::DISPOSITION_INLINE;
		} else {
			$this->disposition = self::DISPOSITION_ATTACHMENT;
		}
	}

	/**
	 * Bind URL to current user session
	 *
	 * @param bool $use_cookie Use cookie
	 * @return void
	 */
	public function bindSession($use_cookie = true) {
		$this->use_cookie = $use_cookie;
	}

	/**
	 * Returns publically accessible URL
	 * @return string
	 */
	public function getURL() {

		if (!$this->file instanceof \ElggFile || !$this->file->exists()) {
			elgg_log("Unable to resolve resource URL for a file that does not exist on filestore: " . (string) $this->file);
			return elgg_normalize_url('/404');
		}

		$root_prefix = elgg_get_data_path();
		$path = $this->file->getFilenameOnFilestore();

		if (substr($path, 0, strlen($root_prefix)) == $root_prefix) {
			$relative_path = substr($path, strlen($root_prefix));
		} else {
			elgg_log("Unable to resolve relative path of the file on the filestore");
			return elgg_normalize_url('/404');
		}

		$data = array(
			'expires' => isset($this->expires) ? $this->expires : time() + self::DEFAULT_TTL,
			'last_updated' => filemtime($this->file->getFilenameOnFilestore()),
			'disposition' => $this->disposition == self::DISPOSITION_INLINE ? 'i' : 'a',
			'path' => $relative_path,
		);


		$key = get_site_secret();
		if ($this->use_cookie) {
			$data['cookie'] = $this->getSessionCookie();
			$data['use_cookie'] = 1;
		} else {
			$data['use_cookie'] = 0;
		}

		ksort($data);
		$mac = _elgg_services()->crypto->getHmac($data, 'sha256', $key)->getToken();

		return elgg_normalize_url("mod/proxy/e{$data['expires']}/l{$data['last_updated']}/d{$data['disposition']}/c{$data['use_cookie']}/$mac/$relative_path");
	}

	/**
	 * Serve URI
	 *
	 * @param string $url URL
	 * @return void
	 */
	public function serveFromURL($url = '') {

		if (headers_sent()) {
			return;
		}

		if (!preg_match('~/e(\d+)/l(\d+)/d([ia])/c([01])/([a-zA-Z0-9\-_]+)/(.*)$~', $url, $m)) {
			header("HTTP/1.1 400 Bad Request");
			exit;
		}

		list(, $expires, $last_updated, $disposition, $use_cookie, $mac, $path) = $m;

		if ($expires && $expires < time()) {
			header("HTTP/1.1 403 Forbidden");
			exit;
		}

		$etag = md5($last_updated);
		if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == "\"$etag\"") {
			header("HTTP/1.1 304 Not Modified");
			exit;
		}

		$key = get_site_secret();
		$hmac_data = array(
			'expires' => (int) $expires,
			'last_updated' => (int) $last_updated,
			'disposition' => $disposition,
			'path' => $path,
			'use_cookie' => (int) $use_cookie,
		);
		if ((bool) $use_cookie) {
			$hmac_data['cookie'] = $this->getSessionCookie();
		}
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
	private static function getSessionCookie() {
		$global_cookies_config = _elgg_services()->config->get('cookies');
		$cookie_config = $global_cookies_config['session'];
		$cookie_name = $cookie_config['name'];
		return _elgg_services()->request->cookies->get($cookie_name, '');
	}
}
