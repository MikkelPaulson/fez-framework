<?php

/**
 * Database model.
 * 
 * @author Mikkel Paulson <me@mikkel.ca> 
 */
class Db {

	public $mysqli = null;
	private $args = [];

	private static $instances = [];


	/**
	 * Class constructor. Initializes mysqli object. Refer to mysqli docs for parameters.
	 *
	 * @param array $params Parameters to pass to mysqli constructor
	 * 
	 * @return void
	 */
	public function __construct($params) {
		$defaults = [
			'host'	=> ini_get('mysqli.default_host'),
			'user'	=> ini_get('mysqli.default_user'),
			'pass'	=> ini_get('mysqli.default_pw'),
			'db'	=> '',
			'port'	=> ini_get('mysqli.default_port'),
			'sock'	=> ini_get('mysqli.default_socket'),
		];

		$params = array_merge($defaults, $params);

		$this->mysqli = new mysqli(
			$params['host'],
			$params['user'],
			$params['pass'],
			$params['db'],
			$params['port'],
			$params['sock']
		);
	}



	// PUBLIC METHODS

	/**
	 * Class factory. Since the database class is immutable, it dosen't make sense to have multiple
	 * instances for any given database connection.
	 * 
	 * @param string $name Database name; taken from site configuration
	 * 
	 * @return Db Object instance referring to the named database
	 */
	public static function factory($name = 'bustimer') {
		if (isset(self::$instances[$name])) {
			return self::$instances[$name];
		} else {
			global $config;
			return self::$instances[$name] = new Db($config['database'][$name]);
		}
	}


	/**
	 * Delete a record.
	 * 
	 * @param string       $table The table to delete from
	 * @param string|array $where Conditions to match when deleting. Tests equality with an
	 *                            associative array; otherwise evaluates SQL code directly.
	 * 
	 * @return void
	 */
	public function delete($table, $where, $limit = null) {
		if (is_array($where)) {
			$params = array_values(array_filter($where, [$this, 'filterToken']));

			$where_temp = array();
			foreach ($where as $key => $value) {
				$where_temp[] = "`$key` " . $this->formatToken($value, true);
			}
			$where = implode(' AND ', $where_temp);
		} else {
			$params = null;
		}

		$query = "DELETE FROM `$table` WHERE $where";

		if (!empty($limit))
			$query .= " LIMIT $limit";

		$this->query($query, $params);
		return $this->mysqli->affected_rows;
	}

	/**
	 * Insert a new record from an associative array. More complex queries should use
	 * $this->query() instead.
	 * 
	 * @param string $table   The table to insert into
	 * @param array  $data    An associative array containing data to insert
	 * @param bool   $delayed True use INSERT DELAYED instead of INSERT
	 * 
	 * @return int The numeric ID of the new record
	 */
	public function insert($table, $data, $delayed = false) {
		//var_dump($table, $data);
		$query = "INSERT " . ($delayed?"DELAYED ":"") . "INTO `$table` (`" . implode("`,`", array_keys($data)) . "`) VALUES (" . implode(',', array_map([$this, 'formatToken'], $data)) . ");";
		$this->query($query, array_values(array_filter($data, [$this, 'filterToken'])));
		return $this->mysqli->insert_id;
	}

	/**
	 * Inserts a new record; alias for $this->insert($table, $data, true).
	 * 
	 * @param string $table   The table to insert into
	 * @param array  $data    An associative array containing data to insert
	 * 
	 * @return int The numeric ID of the new record
	 */
	public function insertDelayed($table, $data) {
		return $this->insert($table, $data, true);
	}

	/**
	 * Execute a REPLACE operation (INSERT or UPDATE depending if the primary or unique key is
	 * already used).
	 * 
	 * @param string $table   The table to insert into
	 * @param array  $data    An associative array containing data to insert
	 * 
	 * @return int The numeric ID of the new or modified record
	 */
	public function replace($table, $data) {
		$query = "REPLACE INTO `$table` (`" . implode("`,`", array_keys($data)) . "`) VALUES (" . implode(',', array_map([$this, 'formatToken'], $data)) . ");";

		$this->query($query, array_values(array_filter($data, [$this, 'filterToken'])));
		return $this->mysqli->insert_id;
	}

	/**
	 * Select one or more records. More complex queries should use Db::query() directly.
	 * 
	 * @param string       $table   The table to delete from
	 * @param string|array $what    What to select (eg. '*' or ['foo', 'bar'])
	 * @param string|array $where   Conditions to match when deleting. Tests equality with an
	 *                              associative array; otherwise evaluates SQL code directly.
	 * @param string|null  $order   ORDER BY clause, eg. 'date_updated DESC'
	 * @param string|null  $limit   LIMIT clause, or NULL for no limit (NOT RECOMMENDED)
	 * @param string|null  $primary Name of primary key to return an assoc array using that key as
	 *                              an index
	 * 
	 * @return mysqli_result|array Query result, or array if $primary is set
	 */
	public function select($table, $what = '*', $where = '', $order = null, $limit = null, $primary = null) {
		if (is_array($what)) {
			$what = '`' . implode('`, `', $what) . '`';
		}

		if (is_array($where)) {
			$params = array_values(array_filter($where, [$this, 'filterToken']));

			$where_temp = array();
			foreach ($where as $key => $value) {
				$where_temp[] = "`$key` " . $this->formatToken($value, true);
			}
			$where = implode(' AND ', $where_temp);
		} else {
			$params = null;
		}

		$query = "SELECT $what FROM `$table`";
		if (!empty($where)) $query .= " WHERE $where";
		if (!empty($order)) $query .= " ORDER BY $order";
		if (!empty($limit)) $query .= " LIMIT $limit";

		$result = $this->query($query, $params);

		if (is_null($primary)) {
			return $result;
		} else {
			$return = [];
			while ($row = $result->fetch_assoc())
				$return[$row[$primary]] = $row;

			return $return;
		}
	}

	/**
	 * Update an existing record.
	 * 
	 * @param string       $table The table to update
	 * @param array        $data  An associative array containing fields to update
	 * @param string|array $where Conditions to match when updating. Tests equality with an
	 *                            associative array; otherwise evaluates SQL code directly.
	 * 
	 * @return int The numeric ID of the updated record
	 */
	public function update($table, $data, $where) {
		foreach ($data as $key => $value) {
			$updates[] = "`$key` = " . $this->formatToken($value);
		}

		if (is_array($where)) {
			$params = array_merge(
				array_values(array_filter($data, [$this, 'filterToken'])),
				array_values(array_filter($where, [$this, 'filterToken']))
			);

			$where_temp = array();
			foreach ($where as $key => $value) {
				$where_temp[] = "`$key` " . $this->formatToken($value, true);
			}
			$where = implode(' AND ', $where_temp);
		} else {
			$params = array_values(array_filter($data, [$this, 'filterToken']));
		}

		$query = "UPDATE `$table` SET " . implode(', ', $updates) . " WHERE $where;";
		$this->query($query, $params);
		return $this->mysqli->insert_id;
	}


	/**
	 * Executes SQL queries contained in a named file.
	 * 
	 * @param string $file The filename to execute
	 * 
	 * @return bool True if operation succeeded; false otherwise
	 */
	public function exec($file) {
		if (!is_file($file))
			return false;

		system("mysql -u{$this->args['user']} -p{$this->args['pass']} -h{$this->args['host']} {$this->args['db']} < $file", $response);

		return !$response;
	}

	/**
	 * Execute a query, optionally replacing/escaping parameters with sprintf
	 * 
	 * @param string $query  Query to execute; sprintf placeholders will be replaced from $params
	 * @param array  $params Array of sprintf placeholders to replace in $query, if any
	 * 
	 * @return mysqli_result|bool The result from the mysqli::query() call
	 */
	public function query($query, $params = []) {

		if (!empty($params)) {
			$params = array_map([$this, 'escape'], $params);
			$query = vsprintf($query, $params);
		}

		$return = $this->mysqli->query($query);

		if ($this->mysqli->errno || strpos($query, 'agencies')) {
			echo "\n\n";
			//var_dump($this->mysqli->error);
			//var_dump($query);
			echo "\n\n";
		}

		return $return;

	}


	/**
	 * Companion to self::formatToken(): determines if a substition will be performed, ie. if
	 * formatToken() returned %d or %s. Example use:
	 *
	 *     $tokens = array_filter($tokens, [$this, 'filterToken']);
	 * 
	 * @param mixed $value The value to filter for
	 * 
	 * @return bool 
	 */
	public function filterToken($value) {
		return !is_array($value) && !is_resource($value);
	}

	/**
	 * Generate a sprintf token based on a data type.
	 * 
	 * @param mixed $value      The value to generate a token for
	 * @param bool  $comparator Include the comparator (so null becomes 'IS NULL')
	 * 
	 * @return string|null The token, or null if unsupported type (ie. an array or resource)
	 */
	public function formatToken($value, $comparator = false) {
		switch (gettype($value)) {
		case 'boolean':
		case 'integer':
		case 'double':
			return $comparator ? '= %d' : '%d';
		case 'string':
		case 'object':
			return $comparator ? '= "%s"' : '"%s"';
		case 'array':
		case 'resource':
			return null;
		case 'NULL':
			return $comparator ? 'IS NULL' : 'NULL';
		}
	}

	/**
	 * Wrapper for mysqli::escape_string with handling for non-string types. Wrap strings in quotes.
	 *
	 * Suggested syntax:
	 *
	 *     array_map([$this, 'escape'], $params);
	 * 
	 * @param mixed $value The value to escape
	 * 
	 * @return string|null The escaped value; null if not an escapable type
	 */
	public function escape($value) {
		switch (gettype($value)) {
		case 'boolean':
			return $value ? '1' : '0';
		case 'integer':
		case 'double':
			return (string) $value;
		case 'object':
			return (string) $value->id;
		case 'string':
			return $this->mysqli->escape_string($value);
		case 'array':
		case 'resource':
		case 'NULL':
			return null;
		}
	}



	// MAGIC METHODS

	/**
	 * Magic caller; provides an interface for $this->mysqli.
	 * 
	 * @param string $name      The method name to call
	 * @param array  $arguments Arguments to pass to the method
	 * 
	 * @return mixed The method return value
	 */
	public function __call($name, $arguments) {
		return call_user_func_array([$this->mysqli, $name], $arguments);
	}

	/**
	 * Magic setter; provides an interface for $this->mysqli.
	 * 
	 * @param string $name  The property name to set
	 * @param mixed  $value The value to set
	 * 
	 * @return void
	 */
	public function __set($name, $value) {
		$this->mysqli->$name = $value;
	}

	/**
	 * Magic getter; provides an interface for $this->mysqli.
	 * 
	 * @param string $name  The property name to set
	 * 
	 * @return mixed The value of the property
	 */
	public function __get($name) {
		return $this->mysqli->$name;
	}

}
