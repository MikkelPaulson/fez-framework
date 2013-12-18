<?php

/**
 * Basic model abstract class. Nothing exciting happening here.
 * 
 * @author Mikkel Paulson <me@mikkel.ca> 
 */
abstract class Model {

	protected static $factory = [];

	protected $data = [];


	public function __construct($data) {
		$this->data = $data;
	}



	// PUBLIC METHODS

	/**
	 * Returns the object instance for the given ID if available, creates one if not.
	 * 
	 * @param mixed $data Either the ID of the object, or an array of parameters. When called with
	 *                    an array, directly creates a new instance without saving. This is for
	 *                    compatibility with DbModel.
	 * 
	 * @return object The object instance
	 */
	public static function factory($data) {
		$class = get_called_class();

		if (is_array($data)) {
			return new $class($data);
		} elseif (is_object($data)) {
			$id = $data->id;
		} else {
			$id = $data;
		}

		if (isset(self::$factory[$class][$id])
		&& is_object(self::$factory[$class][$id])
		&& !self::$factory[$class][$id]->isModified()) {

			return self::$factory[$class][$id];
		} else {
			self::factoryCleanup();
			return self::$factory[$class][$id] = new $class($data);
		}
	}

	/**
	 * Clean up factory data in memory to a limit of 99 elements of a given type.
	 * 
	 * @return void
	 */
	public static function factoryCleanup() {
		$class = get_called_class();

		if (isset(self::$factory[$class]) && count(self::$factory[$class]) > 99) {
			foreach (self::$factory[$class] as $key => $value) {
				if (!$value->isModified())
					unset(self::$factory[$class][$key]);

				if (count(self::$factory[$class]) <= 99) break;
			}
		}
	}

	/**
	 * Remove unmodified objects from class factory.
	 * 
	 * @param bool $force Destroy (overwrite) objects as well as removing them from factory
	 * 
	 * @return void
	 */
	public static function factoryReset($force = false) {
		$class = get_called_class();

		foreach (self::$factory[$class] as $key => $object) {
			if (!$object->isModified()) {
				if ($force) {
					$object = null;
				}

				unset(self::$factory[$class][$key]);
			}
		}
	}


	/**
	 * Just here for fallback for DbModel. A vanilla model doesn't have versioning support.
	 * 
	 * @return void
	 */
	public function isModified() {
		return false;
	}

	/**
	 * Assigns a data set en masse, rather than assigning object parameters one at a time.
	 * 
	 * @param array $data An associative array containing data to assign.
	 * 
	 * @return void
	 */
	public function setData($data) {
		if (!$this->read_only)
			foreach ($data as $name => $value)
				$this->__set($name, $value);
	}



	// MAGIC METHODS

	public function __get($name) {
		return $this->data[$name];
	}
	public function __isset($name) {
		return isset($this->data[$name]);
	}
	public function __set($name, $value) {
		$this->data[$name] = $value;
	}
	public function __unset($name) {
		unset($this->data[$name]);
	}


}
