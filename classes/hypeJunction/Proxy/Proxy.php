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
	 * @return string
	 */
	public function getURL($expires = 0, $disposition = '') {

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

		$query = array(
			'expires' => $expires ? time() + $expires : 0,
			'access_id' => (int) $this->file->access_id,
			'last_updated' => filemtime($this->file->getFilenameOnFilestore()),
			'disposition' => (string) $disposition,
		);

		$data = $query;
		$data['path'] = $relative_path;

		$query['hmac'] = HMAC::getHash($data, get_site_secret());

		$query_hash = base64_encode(serialize($query));

		return elgg_normalize_url("/mod/proxy/$query_hash/$relative_path");
	}

}
