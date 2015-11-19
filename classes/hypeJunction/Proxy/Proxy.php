<?php

namespace hypeJunction\Proxy;

/**
 * Proxy
 * @access private
 */
class Proxy {

	const DISPOSITION_INLINE = 'inline';
	const DISPOSITION_ATTACHMENT = 'attachment';

	/**
	 * @var \ElggFile 
	 */
	private $file;

	/**
	 * Constructor
	 *
	 * @param \ElggFile $file File entity or filestore object
	 */
	public function __construct(\ElggFile $file) {
		$this->file = $file;
	}

	/**
	 * Returns publically accessible URL
	 *
	 * @param int    $expires     Expiration in seconds
	 * @param string $disposition Content disposition
	 * @param bool   $use_cookie  Use current session cookie in HMAC signature
	 * @return string
	 */
	public function getURL($expires = 0, $disposition = 'attachment', $use_cookie = true) {

		if (!$this->file->exists()) {
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
			'expires' => $expires ? time() + $expires : 0,
			'last_updated' => filemtime($this->file->getFilenameOnFilestore()),
			'disposition' => $disposition == 'inline' ? 'i' : 'a',
			'path' => $relative_path,
		);

		
		$key = $this->getSiteSecret();
		if ($use_cookie) {
			$data['cookie'] = $this->getSessionCookie();
			$data['use_cookie'] = 1;
		} else {
			$data['use_cookie'] = 0;
		}

		ksort($data);

		var_dump($data);
		
		$mac = _elgg_services()->crypto->getHmac($data, 'sha256', $key)->getToken();

		return elgg_normalize_url("mod/proxy/e{$data['expires']}/l{$data['last_updated']}/d{$data['disposition']}/c{$data['use_cookie']}/$mac/$relative_path");
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
