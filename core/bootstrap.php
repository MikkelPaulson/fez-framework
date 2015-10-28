<?php

/**
 * Main bootstrap module of Fez Framework.
 *
 * @author me@mikkel.ca
 * @license MIT
 */

// load config files
require_once("{$config['framework_root']}/config/{$config['env']}.php");
require_once("{$config['project_root']}/config/routes.php");

// bootstrap autoloader
require_once("{$config['project_root']}/config/autoload.php");
require_once("{$config['framework_root']}/config/autoload.php");

date_default_timezone_set('UTC');
session_start();


// parse input
list($controller_name, $action) = Router::getRoute();
$controller_class = ucfirst($controller_name) . 'Controller';

$controller = new $controller_class();

$controller->$action();


// render output
switch ($controller->content_type) {
case 'text/html':
	header('Content-type: text/html');
	header('Content-encoding: UTF-8');

	View::render($controller, $action);
	break;
case 'application/json':
	header('Content-type: application/json');
	header('Content-encoding: UTF-8');

	echo json_encode($controller->data);
	break;
}
