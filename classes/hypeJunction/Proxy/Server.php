<?php

namespace hypeJunction\Proxy;

/**
 * Server class
 * @access private
 */
class Server {

	const READ = 'read';
	const WRITE = 'write';
	const READ_WRITE = 'readwrite';

	private $config;
	private $dbPrefix;
	private $dbLink;

	private $path;
	private $expires;
	private $last_updated;
	private $access_id;
	private $disposition;
	private $hmac;

	/**
	 * Constructor
	 *
	 * @param array  $config Elgg Config
	 * @param string $uri    Request URI
	 */
	public function __construct($config, $uri) {

		$this->config = $config;
		$this->dbPrefix = $config->dbprefix;
		
		$segments = explode('/', $uri);
		$query_hash = array_shift($segments);
		$this->path = implode('/', $segments);

		$query = unserialize(base64_decode($query_hash));

		$this->hmac = $query['hmac'];
		$this->expires = (int) $query['expires'];
		$this->last_updated = (int) $query['last_updated'];
		$this->access_id = (int) $query['access_id'];
		$this->disposition = $query['disposition'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function serve() {

		if (headers_sent()) {
			return;
		}

		if ($this->expires && $this->expires < time()) {
			header("HTTP/1.1 403 Forbidden");
			exit;
		}

		if (!$this->hmac || !$this->path) {
			header("HTTP/1.1 400 Bad Request");
			exit;
		}

		$etag = md5($this->last_updated);
		if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == "\"$etag\"") {
			header("HTTP/1.1 304 Not Modified");
			exit;
		}

		$this->openDbLink();
		$values = $this->getDatalistValue(array('dataroot', '__site_secret__'));
		$this->closeDbLink();

		if (empty($values)) {
			header("HTTP/1.1 404 Not Found");
			exit;
		}

		$data_root = $values['dataroot'];
		$key = $values['__site_secret__'];

		$hmac_data = array(
			'expires' => $this->expires,
			'last_updated' => $this->last_updated,
			'access_id' => $this->access_id,
			'disposition' => $this->disposition,
			'path' => $this->path,
		);
		
		$hmac = HMAC::getHash($hmac_data, $key);
		if ($this->hmac !== $hmac) {
			header("HTTP/1.1 403 Forbidden");
			exit;
		}

		$filenameonfilestore = "{$data_root}{$this->path}";

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
		if ($this->disposition == 'inline') {
			header("Content-disposition: inline");
		} else {
			$basename = basename($filenameonfilestore);
			header("Content-disposition: attachment; filename='$basename'");
		}

		$expires = $this->expires ? gmdate('D, d M Y H:i:s \G\M\T', $this->expires) : gmdate('D, d M Y H:i:s \G\M\T', strtotime("+3 years"));
		header('Expires: ' . $expires, true);
		header("Pragma: public");
		header("Cache-Control: public");



		header("ETag: \"$etag\"");

		readfile($filenameonfilestore);
		exit;
	}

	/**
	 * Returns DB config
	 * @return array
	 */
	protected function getDbConfig() {
		if ($this->isDatabaseSplit()) {
			return $this->getConnectionConfig(self::READ);
		}
		return $this->getConnectionConfig(self::READ_WRITE);
	}

	/**
	 * Connects to DB
	 * @return void
	 */
	protected function openDbLink() {
		$dbConfig = $this->getDbConfig();
		$this->dbLink = @mysql_connect($dbConfig['host'], $dbConfig['user'], $dbConfig['password'], true);
	}

	/**
	 * Closes DB connection
	 * @return void
	 */
	protected function closeDbLink() {
		if ($this->dbLink) {
			mysql_close($this->dbLink);
		}
	}

	/**
	 * Retreive values from datalists table
	 *
	 * @param array $names Parameter names to retreive
	 * @return array
	 */
	protected function getDatalistValue(array $names = array()) {

		if (!$this->dbLink) {
			return array();
		}

		$dbConfig = $this->getDbConfig();
		if (!mysql_select_db($dbConfig['database'], $this->dbLink)) {
			return array();
		}

		if (empty($names)) {
			return array();
		}
		$names_in = array();
		foreach ($names as $name) {
			$name = mysql_real_escape_string($name);
			$names_in[] = "'$name'";
		}
		$names_in = implode(',', $names_in);

		$values = array();

		$q = "SELECT name, value
				FROM {$this->dbPrefix}datalists
				WHERE name IN ({$names_in})";

		$result = mysql_query($q, $this->dbLink);
		if ($result) {
			$row = mysql_fetch_object($result);
			while ($row) {
				$values[$row->name] = $row->value;
				$row = mysql_fetch_object($result);
			}
		}

		return $values;
	}

	/**
	 * Returns request query value
	 *
	 * @param string $name    Query name
	 * @param mixed  $default Default value
	 * @return mixed
	 */
	protected function get($name, $default = null) {
		if (isset($_GET[$name])) {
			return $_GET[$name];
		}
		return $default;
	}

	/**
	 * Are the read and write connections separate?
	 *
	 * @return bool
	 */
	public function isDatabaseSplit() {
		if (isset($this->config->db) && isset($this->config->db['split'])) {
			return $this->config->db['split'];
		}
		// this was the recommend structure from Elgg 1.0 to 1.8
		if (isset($this->config->db) && isset($this->config->db->split)) {
			return $this->config->db->split;
		}
		return false;
	}

	/**
	 * Get the connection configuration
	 *
	 * The parameters are in an array like this:
	 * array(
	 * 	'host' => 'xxx',
	 *  'user' => 'xxx',
	 *  'password' => 'xxx',
	 *  'database' => 'xxx',
	 * )
	 *
	 * @param int $type The connection type: READ, WRITE, READ_WRITE
	 * @return array
	 */
	public function getConnectionConfig($type = self::READ_WRITE) {
		$config = array();
		switch ($type) {
			case self::READ:
			case self::WRITE:
				$config = $this->getParticularConnectionConfig($type);
				break;
			default:
				$config = $this->getGeneralConnectionConfig();
				break;
		}
		return $config;
	}

	/**
	 * Get the read/write database connection information
	 *
	 * @return array
	 */
	protected function getGeneralConnectionConfig() {
		return array(
			'host' => $this->config->dbhost,
			'user' => $this->config->dbuser,
			'password' => $this->config->dbpass,
			'database' => $this->config->dbname,
		);
	}

	/**
	 * Get connection information for reading or writing
	 *
	 * @param string $type Connection type: 'write' or 'read'
	 * @return array
	 */
	protected function getParticularConnectionConfig($type) {
		if (is_object($this->config->db[$type])) {
			// old style single connection (Elgg < 1.9)
			$config = array(
				'host' => $this->config->db[$type]->dbhost,
				'user' => $this->config->db[$type]->dbuser,
				'password' => $this->config->db[$type]->dbpass,
				'database' => $this->config->db[$type]->dbname,
			);
		} else if (array_key_exists('dbhost', $this->config->db[$type])) {
			// new style single connection
			$config = array(
				'host' => $this->config->db[$type]['dbhost'],
				'user' => $this->config->db[$type]['dbuser'],
				'password' => $this->config->db[$type]['dbpass'],
				'database' => $this->config->db[$type]['dbname'],
			);
		} else if (is_object(current($this->config->db[$type]))) {
			// old style multiple connections
			$index = array_rand($this->config->db[$type]);
			$config = array(
				'host' => $this->config->db[$type][$index]->dbhost,
				'user' => $this->config->db[$type][$index]->dbuser,
				'password' => $this->config->db[$type][$index]->dbpass,
				'database' => $this->config->db[$type][$index]->dbname,
			);
		} else {
			// new style multiple connections
			$index = array_rand($this->config->db[$type]);
			$config = array(
				'host' => $this->config->db[$type][$index]['dbhost'],
				'user' => $this->config->db[$type][$index]['dbuser'],
				'password' => $this->config->db[$type][$index]['dbpass'],
				'database' => $this->config->db[$type][$index]['dbname'],
			);
		}
		return $config;
	}

}
