<?php

abstract class RouterAbstract {


	public static function getRoute() {

		$route = explode('/', trim(preg_replace('#^([^?]*).*$#', '$1', $_SERVER['REQUEST_URI']), '/'));

		$controller = array_shift($route) ?: '';
		$action = array_shift($route) ?: '';

		$path = '/' . trim("$controller/$action", '/');

		// /default/somepage/foo/bar/baz === /default/somepage?foo=bar&baz
		// this should only be used for additional path info (eg. extra routing), not normal
		// get parameters
		while (!empty($route)) {
			$key = array_shift($route);
			$value = array_shift($route);

			$_GET[$key] = $value;
			$_REQUEST[$key] = $value;
		}

		return static::map($path);

	}

	public static function map($path) {
		$routes = static::getAllRoutes();

		if (isset($routes[$path]))
			return $routes[$path];
		else
			return [ 'error', '_404' ];
	}

	public static function urlFor($controller, $action, $params = []) {
		$index = array_search([$controller,$action], static::getAllRoutes());
		return $index === false ? null : $index;
	}


	protected static function getAllRoutes() {
		global $config;

		return $config['routes']['global'];
	}

}
