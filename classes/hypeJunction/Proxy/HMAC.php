<?php

namespace hypeJunction\Proxy;

/**
 * HMAC
 * @access private
 */
class HMAC {
	
	/**
	 * Calculate HMAC
	 * 
	 * @param array  $data HMAC data
	 * @param string $key  HMAC key
	 */
	public static function getHash(array $data, $key) {
		ksort($data);
		return hash_hmac('sha256', serialize($data), $key);
	}
}
