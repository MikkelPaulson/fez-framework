<?php

class View {

	public static function render($controller, $action) {
		global $config;

		extract($controller->data);

		include("{$config['project_root']}/views/layouts/{$controller->layout}.header.tpl");
		include("{$config['project_root']}/views/$controller/$action.tpl");
		include("{$config['project_root']}/views/layouts/{$controller->layout}.footer.tpl");
	}

}
