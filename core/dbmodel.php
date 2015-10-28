<?php

/**
 * A model for a data type corresponding to a database table. Includes fancy getter and setter
 * magic, and basic search capabilities.
 * 
 * @uses Model
 * @author Mikkel Paulson <me@mikkel.ca> 
 * @license MIT
 */
abstract class DbModel extends Model {

	protected static $relations = [];
	protected static $table = null;
	protected static $primary = null;

	public $id = null;
	public $class = null;

	protected $old_data = [];
	protected $read_only = false;
	protected $relation_classes = [];

	protected $new = false;
	protected $loaded = false;

	const RELATION_ONE = 0;
	const RELATION_MANY = 1;


	/**
	 * Class constructor. If passed a numeric ID, fetches fields from a table based on the table
	 * name and primary key defined in class static parameters. If passed an array, assumes the
	 * passed data is the result of a database query and forgoes the select.
	 *
	 * WARNING: When constructed with an array, the instance will still be entered into the factory
	 * and will be returned to subsequent requests. If this is not intended behaviour, set the
	 * object parameters as follows:
	 *
	 *     $foo = new Foo();
	 *     $foo->bar = 123;
	 *     $foo->baz = 456;
	 *
	 *     // or
	 *
	 *     $foo = new Foo();
	 *     $foo->setData(['bar' => 123, 'baz' => 456]);
	 *
	 * The instance is always committed to the factory when calling $foo->commit().
	 * 
	 * @param null|int|array $data The primary key of the record to fetch, or an associative array
	 *                             containing the database result.
	 * 
	 * @return void
	 */
	public function __construct($data = null) {
		if (is_array($data)) {
			$this->old_data = $this->data = $data;
			$this->id = (int) $this->data[static::$primary];
			$this->loaded = true;
		} elseif (!is_null($data)) {
			$this->id = (int) $data;
		} else {
			$this->new = true;
		}

		$this->class = get_called_class();
	}



	// PUBLIC METHODS

	/**
	 * Search the table for records matching given conditions, returning an array of object
	 * instances of matched records.
	 * 
	 * @param string      $where       WHERE section of the query
	 * @param array       $params      Any parameters to insert into the WHERE section using sprintf()
	 * @param null|string $order       ORDER BY section of the query
	 * @param string      $limitClause LIMIT section of the query (eg. '10' or '0,10')
	 * 
	 * @return ModelList All matching objects, subject to LIMIT
	 */
	public static function search($where, $order = null, $limitClause = null) {
		$db = Db::factory();
		$cache = Cache::factory();
		$class = get_called_class();

		// parse inputs
		$key = strtolower($class) . ":search:[" . serialize($where) . "]:[" . serialize($order) . "]";

		if (empty($limitClause)) {
			$limit = null;
			$offset = 0;
		} else {
			$limitClause = explode(',', $limitClause);
			$limit = (int) array_pop($limitCause);
			$offset = (int) array_pop($limitCause);
		}

		// check for cache hit
		if ($cache->exists($key)) {
			return new ModelList($class, $cache->lSlice($key, $offset, $limit));
		}

		$result = $db->select(static::$table, static::$primary, $where, $order);
		if ($result === false) return null;

		$data = [];
		while ($row = $result->fetch_row())
			$data[] = $row[0];

		$cache->lPush($key, $data);
		$cache->expire($key, 900);

		return new ModelList($class, array_slice($data, $offset, $limit));
	}


	/**
	 * Commit changes to the object applied using the setter. Only modified fields are updated.
	 * 
	 * @return bool True on success, false otherwise
	 */
	public function commit() {
		//var_dump($this);
		$data = [];

		if ($this->new) {
			$data = $this->data;
		} else {
			foreach (array_keys($this->old_data) as $key)
				$data[$key] = isset($this->data[$key]) ? $this->data[$key] : null;
		}

		if (empty($data))
			return false;

		$db = Db::factory();
		$cache = Cache::factory();

		// insert a new record or update an existing one?
		if ($this->new) {
			$this->id = (int) $db->insert(static::$table, $data);
			$this->data[static::$primary] = $this->id;

			static::factoryCleanup();
			static::$factory[$this->class][$this->id] = $this; // update factory too
		} else {
			$db->update(static::$table, $data, [static::$primary => $this->id]);
		}

		$cache->set("object:{$this->class}:{$this->id}", $this->data, 1);
		$this->old_data = $this->data;

		return true;
	}

	/**
	 * Has this class been modified since its creation?
	 * 
	 * @return void
	 */
	public function isModified() {
		return $this->data !== $this->old_data;
	}

	/**
	 * Reset object data to match the record originally loaded from the database, discarding
	 * anything changed using the setter.
	 * 
	 * @return void
	 */
	public function reset() {
		$this->data = $this->old_data;
	}



	// PROTECTED/PRIVATE METHODS

	/**
	 * Ensure that data is properly loaded from cache or database. On-demand data loading reduces
	 * memory requirements.
	 * 
	 * @return void
	 */
	protected function load() {
		if (!$this->loaded && !$this->new) {
			$cache = Cache::factory();
			$db = Db::factory();

			$key = strtolower(get_called_class()) . ":data:{$this->id}";

			// check for data in cache
			$result = $cache->get($key);
			if ($result !== false) {
				$this->old_data = $this->data = unserialize($result);
				$this->loaded = true;
				return;
			}


			// check for data in database
			$result = $db->select(static::$table, '*', [static::$primary => $this->id]);

			// TODO: handle this better
			if ($result === false || !$result->num_rows) {
				die("ID not found: {$this->id} of " . static::$table);
			}

			$this->old_data = $this->data = $result->fetch_assoc();
			$this->loaded = true;


			// save updated data to cache
			$cache->set($key, serialize($this->data));
			$cache->expire($key, 7200);
		}
	}



	// MAGIC METHODS

	/**
	 * Magic method to enable the following syntaxes for models having a one-to-many relationship
	 * with another model:
	 *
	 *     (array) $cookie->getChips([$where[, $order[, $limit]]]); // array of matched objects
	 *     (int) $cookie->getChipsCount([$where]); // integer count of matching records
	 *
	 * See Db::select() for the full meaning and usage of the parameters.
	 * 
	 * @param string $name Name of the called method
	 * @param array  $args Passed method arguments
	 * 
	 * @return mixed See descriptions in example code; null if method not found
	 */
	public function __call($name, $args) {
		if (preg_match('/^get([A-Z][a-z]*)(Count)?$/', $name, $match)
		&& isset(static::$relations[$name = strtolower($match[1])])
		&& static::$relations[$name]['relation'] == self::RELATION_MANY) {

			$db = Db::factory();

			if (count($args) > 3) return null;

			// extract variables:: $relation, $key, $class
			extract(static::$relations[$name]);

			// extract parameters: $where, $order, $limit
			extract(array_combine(['where', 'order', 'limit'], array_pad($args, 3, null)));

			switch (gettype($where)) {
			case 'string':
			default:
				$where = "`$key` = {$this->id} AND $where";
				break;
			case 'array':
				$where = array_merge([$key => $this->id], $where);
				break;
			case 'NULL':
				$where = [$key => $this->id];
				break;
			}

			// getItemsCount(['foo' => 'bar']);
			if (!empty($match[2])) {
				if (count($args) > 1) return null;

				$result = $db->select($class::$table, 'COUNT(*)', $where); break;

				if ($result === false) return null;

				return (int) $result->fetch_row()[0];

			// getItems(['foo' => 'bar'], 'baz ASC', '0,10');
			} else {
				switch (count($args)) {
				case 0:
				case 1: $result = $db->select($class::$table, $class::$primary, $where); break;
				case 2: $result = $db->select($class::$table, $class::$primary, $where, $order); break;
				case 3: $result = $db->select($class::$table, $class::$primary, $where, $order, $limit); break;
				}

				if ($result === false) return null;

				$response = [];
				foreach ($result as $item) {
					$id = (int) $item[$class::$primary];
					$response[] = $class::factory($id);
				}

				return new ModelList($class, $response);
			}
		}

		return null;
	}

	/**
	 * Getter. If requested name matches a predefined relational structure, return and if
	 * necessary instantiate an object of the appropriate type. Otherwise, fetch the named
	 * value from the $data object property.
	 * 
	 * @param string $name The name of the property to fetch
	 * 
	 * @return mixed The property value or object instance
	 */
	public function __get($name) {
		$this->load();

		if (isset(static::$relations[$name]) && static::$relations[$name]['relation'] == self::RELATION_ONE) {
			if (isset($this->relation_classes[$name])) {
				return $this->relation_classes[$name];
			} else {
				$key = static::$relations[$name]['key'];
				$class = static::$relations[$name]['class'];

				return $this->relation_classes[$name] = new $class($this->data[$key]);
			}
		} else {
			return $this->data[$name];
		}
	}

	public function __isset($name) {
		$this->load();
		return isset($this->data[$name]);
	}

	/**
	 * Setter. Maintains a diff with original record set as values change to allow simple updating
	 * of database records.
	 * 
	 * @param string $name  The name of the field to set
	 * @param mixed  $value The corresponding value
	 * 
	 * @return void
	 */
	public function __set($name, $value) {
		if (!$this->read_only) {
			$this->load();
			$this->data[$name] = $value;
			
			// because array_diff_assoc doesn't like array values
			if (is_array($value))
				$this->old_data[$name] = null;
		}
	}

	/**
	 * Magic method to unset a value while keeping rudimentary history.
	 * 
	 * @param string $name The name of the field to unset
	 * 
	 * @return void
	 */
	public function __unset($name) {
		if (!$this->read_only) {
			$this->load();
			unset($this->data[$name]);

			if (in_array($name, $this->old_data))
				$this->data[$name] = null;
		}
	}

	/**
	 * Triggered when the instance is cast to a string -- mainly used in database queries.
	 * 
	 * @return string The ID of the object instance.
	 */
	public function __toString() {
		return (string) $this->id;
	}

}
