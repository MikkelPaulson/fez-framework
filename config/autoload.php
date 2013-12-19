<?php

spl_autoload_register(function($class){

	global $config;

	switch ($class) {

	// abstract classes
	case 'Controller':			$path = 'core/controller.php';				break;
	case 'DbModel':				$path = 'core/dbmodel.php';					break;
	case 'Model':				$path = 'core/model.php';					break;

	// core classes
	case 'APIClient':			$path = 'core/apiclient.php';				break;
	case 'AsyncRequest':		$path = 'core/asyncrequest.php';			break;
	case 'DateTimeCustom':		$path = 'core/datetime.php';				break;
	case 'ModelList':			$path = 'core/modellist.php';				break;
	case 'Router':				$path = 'core/router.php';					break;
	case 'RouterAbstract':		$path = 'core/routerabstract.php';			break;
	case 'Utils':				$path = 'core/utils.php';					break;
	case 'View':				$path = 'core/view.php';					break;

	// models
	case 'AsyncResponse':		$path = 'models/asyncresponse.php';			break;
	case 'CLI':					$path = 'models/cli.php';					break;
	case 'CLIProgressBar':		$path = 'models/cli.php';					break;
	case 'CSV':					$path = 'models/csv.php';					break;
	case 'Cache':				$path = 'models/cache.php';					break;
	case 'Db':					$path = 'models/db.php';					break;
	case 'DbBulk':				$path = 'models/dbbulk.php';				break;
	case 'Gearman':				$path = 'models/gearman.php';				break;

	}

	if (!empty($path))
		require_once("{$config['framework_root']}/$path");

});
