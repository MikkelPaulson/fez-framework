<?php

/**
 * Wrapper class for Redis.
 *
 * TODO implement smarter cache expiry?
 * 
 * @author Mikkel Paulson <me@mikkel.ca> 
 * @license MIT
 */
class Cache {

	public static $instances = [];

	public $redis = null;
	public $params = [];

	public function __construct($params) {
		$this->redis = new Redis();
		$this->redis->connect(
			$params[0][0],	// hostname
			$params[0][1],	// server port
			5				// timeout
		);

		$this->params = $params;
	}

	/**
	 * Class factory.
	 * 
	 * @param string $name Redis instance/cluster to access
	 * 
	 * @return Cache A new cache object for the named instance/cluster
	 */
	public static function factory($name = 'default') {
		global $config;

		if (!isset(self::$instances[$name]))
			return self::$instances[$name] = new Cache($config['redis'][$name]);
		else
			return self::$instances[$name];
	}

	/**
	 * Magic caller; provides an interface for $this->redis. Also implements batch functionality for
	 * [rl]Pushx? so that passing an array for $value pushes multiple values.
	 * 
	 * @param string $name      The function name to call
	 * @param array  $arguments Arguments to pass to the function
	 * 
	 * @return mixed The function return value
	 */
	public function __call($name, $arguments) {
		if (in_array($name, ['rPush', 'lPush', 'rPushx', 'lPushx'])) {
			list($key, $value) = $arguments;

			return call_user_func_array([$this->redis, $name], array_merge([$key], empty($value) ? [null] : (array) $value));
		} else {
			return call_user_func_array([$this->redis, $name], $arguments);
		}
	}

	/**
	 * Implementation of lRange with PHP-like offset, len syntax instead of offset, offset.
	 * 
	 * @param string $key    Cache key to fetch
	 * @param int    $start  Start offset (0-based)
	 * @param int    $length Max elements to return
	 * 
	 * @return array Elements fetched from Redis list
	 */
	public function lSlice($key, $start, $length = null) {
		if ($start < 0 || $length < 0)
			$size = $this->redis->lSize($key);

		if ($start < 0)
			$start = $size + $start;

		if ($length === null)
			return $this->redis->lRange($key, $start, -1);
		elseif ($length > 0)
			return $this->redis->lRange($key, $start, $start + $length - 1);
		elseif ($length < 0)
			return $this->redis->lRange($key, $start, $size + $start);
		else
			return [];
	}

}
