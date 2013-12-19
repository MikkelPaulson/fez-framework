<?php

/**
 * Singleton class to handle asynchronous URL requests. Returns AsyncResponse object instances,
 * which don't block until accessed directly.
 * 
 * @author Mikkel Paulson <me@mikkel.ca> 
 */

class AsyncRequest {

	private static $mh = null;

	/**
	 * Magic method to handle static method overloading.
	 * 
	 * @param string $method Name of static method called
	 * @param array  $args   Arguments passed
	 * 
	 * @return mixed The return value of the called method, if any
	 */
	public static function __callStatic($method, $args) {
		if (in_array($method, ['get', 'post', 'put', 'delete'])) {
			array_unshift($args, $method);
			return call_user_func_array('self::request', $args);
		}
	}

	/**
	 * Core method to conduct an HTTP request
	 * 
	 * @param string $method  HTTP method to use
	 * @param string $url     URL to request
	 * @param array  $headers Headers to send with request, if any
	 * @param mixed  $content Request content, if any
	 * @param int    $timeout Request timeout in seconds
	 * 
	 * @return AsyncResponse An object representing the request
	 */
	public static function request($method, $url, $headers = [], $content = null, $timeout = 2) {

		// initialize multiget handle
		if (is_null(self::$mh))
			self::$mh = curl_multi_init();

		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 2);

		if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		if (!is_null($content)) curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

		curl_multi_add_handle(self::$mh, $ch);

		return new AsyncResponse($ch);

	}

	public static function run() {
		do {
			$mrc = curl_multi_exec(self::$mh, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select(self::$mh) != -1) {
				do {
					$mrc = curl_multi_exec(self::$mh, $active);
				} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}
		}
	}

	public static function remove($ch) {
		curl_multi_remove_handle(self::$mh, $ch);
	}

}
