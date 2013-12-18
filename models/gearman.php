<?php

class Gearman {

	public $gearman = null;

	public static $instances = [];

	/**
	 * Class constructor. Initializes Gearman object. Refer to Gearman docs for parameters.
	 * 
	 * @param string $type The type of Gearman class to instantiate (eg. 'client' for
	 *                     GearmanClient, 'worker' for GearmanWorker)
	 * 
	 * @return void
	 */
	public function __construct($type) {
		$type = 'Gearman' . ucfirst($type);
		$this->gearman = new $type();

		if (in_array($type, ['GearmanClient', 'GearmanWorker'])) {
			try {
				$this->gearman->addServer('127.0.0.1', 4370);
			} catch (Exception $e) {
				error_log('Error connecting to Gearman: ' . $e->getMessage() . ' (Gearman error ' . $this->gearman->error() . ')');
			}
		}
	}

	public static function factory($type = 'client') {
		if (isset(self::$instances[$type])) {
			return self::$instances[$type];
		} else {
			global $config;
			return self::$instances[$type] = new Gearman($type);
		}
	}

	/**
	 * Magic setter; provides an interface for $this->gearman.
	 * 
	 * @param string $name  The property name to set
	 * @param mixed  $value The value to set
	 * 
	 * @return void
	 */
	public function __set($name, $value) {
		$this->gearman->$name = $value;
	}

	/**
	 * Magic getter; provides an interface for $this->gearman.
	 * 
	 * @param string $name The property name to get
	 * 
	 * @return mixed The value of the property
	 */
	public function __get($name) {
		return $this->gearman->$name;
	}

	/**
	 * Magic caller; provides an interface for $this->gearman.
	 * 
	 * @param string $name      The method name to call
	 * @param array  $arguments Arguments to pass to the method
	 * 
	 * @return mixed The method return value
	 */
	public function __call($name, $arguments) {
		$return = @call_user_func_array([$this->gearman, $name], $arguments);

		if ($this->gearman->getErrno()) {
			error_log('Gearman error: ' . $this->gearman->error());
			return null;
		}

		return $return;
	}

}
