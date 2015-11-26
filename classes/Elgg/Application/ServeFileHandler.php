<?php
namespace Elgg\Application;

use Elgg\Config;


/**
 * File server handler
 *
 * @access private
 *
 * @package Elgg.Core
 */
class ServeFileHandler {

	/** @var Config */
	private $config;

	/** @var array */
	private $server_vars;

	/**
	 * Constructor
	 *
	 * @param Config      $config      Elgg configuration
	 * @param array       $server_vars Server vars
	 */
	public function __construct(Config $config, $server_vars) {
		$this->config = $config;
		$this->server_vars = $server_vars;
	}

	/**
	 * Handle a request for a file
	 *
	 * @param array $path URL path
	 * @return void
	 */
	public function handleRequest($path) {

		if (!preg_match('~e(\d+)/l(\d+)/d([ia])/c([01])/([a-zA-Z0-9\-_]+)/(.*)$~', $path, $m)) {
			header("HTTP/1.1 400 Bad Request");
			exit;
		}

		list(, $expires, $last_updated, $disposition, $use_cookie, $mac, $path_from_dataroot) = $m;

		if ($expires && $expires < time()) {
			$this->send403('URL has expired');
		}

		$etag = '"' . $last_updated . '"';
		$this->handle304($etag);

		$hmac_data = array(
			'expires' => (int) $expires,
			'last_updated' => (int) $last_updated,
			'disposition' => $disposition,
			'path' => $path_from_dataroot,
			'use_cookie' => (int) $use_cookie,
		);
		if ((bool) $use_cookie) {
			$hmac_data['cookie'] = _elgg_services()->session->getId();
		}
		ksort($hmac_data);

		$hmac = elgg_build_hmac($hmac_data);
		if (!$hmac->matchesToken($mac)) {
			$this->send403();
		}

		$dataroot = _elgg_services()->config->get('dataroot');
		if (empty($dataroot)) {
			$this->send404();
		}

		$filenameonfilestore = "{$dataroot}{$path_from_dataroot}";

		if (!is_readable($filenameonfilestore)) {
			$this->send404();
		}

		$actual_last_updated = filemtime($filenameonfilestore);
		if ($actual_last_updated != $last_updated) {
			$this->send403('URL has expired');
		}

		$mime = $this->getContentType($filenameonfilestore);
		header("Content-type: $mime", true);

		$filesize = filesize($filenameonfilestore);
		header("Content-Length: $filesize", true);

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

		$cache_control = $use_cookie ? 'no-cache' : 'public';
		header("Pragma: $cache_control", true);
		header("Cache-Control: $cache_control", true);

		header("ETag: $etag");

		readfile($filenameonfilestore);
		exit;
	}

	/**
	 * Returns content type header value
	 * 
	 * @param string $path Full path to file
	 */
	protected function getContentType($path) {
		$mime = 'application/otcet-stream';
		if (function_exists('finfo_file') && defined('FILEINFO_MIME_TYPE')) {
			$resource = finfo_open(FILEINFO_MIME_TYPE);
			if ($resource) {
				$mime = finfo_file($resource, $path);
			}
		}
		return $mime;
	}

	/**
	 * Send a 304 and exit() if the ETag matches the request
	 *
	 * @param string $etag ETag value
	 * @return void
	 */
	protected function handle304($etag) {
		if (isset($this->server_vars['HTTP_IF_NONE_MATCH'])
			&& trim($this->server_vars['HTTP_IF_NONE_MATCH']) === $etag) {
			header("HTTP/1.1 304 Not Modified");
			exit;
		}
	}
	
	/**
	 * Send a 403 with an error message to requestor and exit()
	 *
	 * @param string $msg Optional message text
	 * @return void
	 */
	protected function send403($msg = 'Error: permission denied') {
		header('HTTP/1.1 403 Forbidden');
		echo $msg;
		exit;
	}

	/**
	 * Send a 404 with an error message to requestor and exit()
	 *
	 * @param string $msg Optional message text
	 * @return void
	 */
	protected function send404($msg = 'Error: file not found') {
		header('HTTP/1.1 404 Not found');
		echo $msg;
		exit;
	}
}
