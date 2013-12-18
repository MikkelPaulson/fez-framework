<?php

/**
 * Iterator used when returning a list of instances. Stores IDs only and instantiates children on
 * demand to reduce performance/memory demands. Usage examples:
 *
 *     // get a new instance
 *     $list = MyClass::search(['foo' => 'bar']);
 *     
 *     // iterate through results
 *     foreach ($list as $item)  // <-- $item instances created on iteration
 *         print_r($item->name); // <-- row loaded from cache on access -- @see DbModel
 *     
 *     // spawn a new list using an array method -- only results where $data['type'] is 'baz'
 *     // analogous to array_filter($list, function($o){...})
 *     $new_list = $list->array_filter(function($o){return $o->type == 'baz';});
 *     
 *     // access elements by index
 *     print_r($new_list(0)->name);
 * 
 * @uses Iterator
 * @author Mikkel Paulson <me@mikkel.ca> 
 */
class ModelList implements Iterator {

	private $class = null;
	private $data = [];
	private $pointer = 0;


	/**
	 * Class constructor. Initializes data array with null values, which is only updated on access.
	 * 
	 * @param string $class Name of class of child elements
	 * @param array  $data  Element IDs to load
	 * 
	 * @return void
	 */
	public function __construct($class, $data) {
		$this->class = $class;
		$this->data = array_fill_keys($data, null);
	}



	// ITERATOR METHODS

	public function current() {
		$current = current($this->data);

		if ($current === null) {
			$key = key($this->data);
			return $this->data[$key] = new $this->class($key);
		} else {
			return $current;
		}
	}

	public function key() {
		return $this->pointer;
	}

	public function next() {
		next($this->data);
		$this->pointer++;
		return $this->current();
	}

	public function rewind() {
		reset($this->data);
		$this->pointer = 0;
	}

	public function valid() {
		return $this->pointer < count($this->data);
	}



	// MAGIC METHODS

	/**
	 * Magic method to allow the class to implement most array functions (eg. count, array_filter,
	 * etc). Anything that accepts an array as the first parameter is supported, plus custom
	 * support for array_map.
	 * 
	 * @param string $method The name of the called method
	 * @param array  $params Passed parameters
	 * 
	 * @return mixed The return value of the function called; if the function returned an array,
	 *               returns a new instance of ModelList with the array elements
	 */
	public function __call($method, $params) {
		if ($method == 'array_map') {
			$result = call_user_func_array($method, array_merge([$params[0], array_keys($this->data)], array_slice($params, 1)));
		} else {
			$result = call_user_func_array($method, array_merge([array_keys($this->data)], $params ?: []));
		}

		if (is_array($result))
			return new ModelList($this->class, $result);
		else
			return $result;
	}

	/**
	 * Allows an array-like syntax for accessing elements by position: $obj(0) instead of $arr[0].
	 * 
	 * @param int $offset The 0-based offset to access
	 * 
	 * @return object The value at that offset, instantiated on demand if necessary
	 */
	public function __invoke($offset) {
		$result = array_slice($this->data, $offset, 1, true);
		if (empty($result)) {
			return null;
		}

		list($key, $value) = each($result);

		if ($value === null) {
			return $this->data[$key] = new $this->class($key);
		} else {
			return $value;
		}
	}

	/**
	 * Handles casting object as a string. Converts to a comma-delimited list suitable for DB query
	 * IN (x,y,z) syntax.
	 * 
	 * @return string Comma-separated list of array elements
	 */
	public function __toString() {
		return implode(',', array_keys($this->data));
	}

}
