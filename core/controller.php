<?php

/**
 * Abstract class for site controllers. Methods are mainly used to interface with views.
 * 
 * @author Mikkel Paulson <me@mikkel.ca> 
 * @license MIT
 */
abstract class Controller {

	public static $controller = null;
	public static $action = null;
	public static $obj = null;

	public $data = [];
	public $layout = 'default';

	public $content_type = 'text/html';

	private $enqueued = ['js' => [], 'css' => []];


	// PUBLIC METHODS

	/**
	 * Initialize a new controller as mapped and call the method appropriate to $action.
	 * 
	 * @param string $controller The name of the controller to instantiate
	 * @param string $action     The controller method to call
	 * 
	 * @return void
	 */
	public static function init($controller, $action) {
		$classname = ucfirst(self::$controller) . 'Controller';

		self::$obj = new $classname();
		self::$obj->$action();
	}

	/**
	 * Execute an HTTP redirect. Includes a die so you don't have to.(TM)
	 * 
	 * @param string $destination The destination URL
	 * @param int    $code        Numeric HTTP response code
	 * 
	 * @return void
	 */
	public static function redirect($destination, $code = 302) {
		$codes = [
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			307 => 'Temporary Redirect',
			308 => 'Permanent Redirect',
		];

		header("HTTP/1.1 $code {$codes[$code]}");
		header("Location: $destination");

		die();
	}


	/**
	 * Add a new stylesheet or script file to be outputted in the page head.
	 * 
	 * @param string $type     The type of file to enqueue ('js' or 'css')
	 * @param string $link     Link to the enqueued file
	 * @param int    $priority Priority for ordering; smaller numbers appear higher. Negative
	 *                         values are allowed.
	 * 
	 * @return void
	 */
	public function enqueue($type, $link, $priority = 0) {
		$this->enqueued[$type][$link] = $priority;
	}

	/**
	 * Output all scripts and stylesheets that have been added using $this->enqueue(). Should
	 * always be called in page header.
	 * 
	 * @return void
	 */
	public function outputEnqueued() {
		asort($this->enqueued['css']);
		asort($this->enqueued['js']);

		foreach ($this->enqueued['css'] as $link => $priority)
			echo "<link rel=\"stylesheet\" href=\"$link\">";

		foreach ($this->enqueued['js'] as $link => $priority)
			echo "<script type=\"text/javascript\" src=\"$link\"></script>";
	}



	// MAGIC METHODS

	/**
	 * Magic method used when casting to string. Returns the name of the controller (minus the
	 * ~Controller naming convention used in models). 
	 * 
	 * @return string The name of the controller
	 */
	public function __toString() {
		return strtolower(str_replace('Controller', '', get_called_class()));
	}

}
