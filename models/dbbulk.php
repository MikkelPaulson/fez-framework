<?php

class DbBulk {

	private $db = null;
	private $operation = null;
	private $table = null;
	private $fields = null;

	private $data = [];

	public function __construct($db, $operation, $table, $fields) {
		$this->db = $db;
		$this->operation = $operation;
		$this->table = $table;
		$this->fields = $fields;
	}

	public function add($values) {
		$this->data[] = $values;
		return count($this->data) - 1;
	}

	public function remove($index) {
		array_splice($this->data, $index, 1);
	}

	public function commit() {
		if (empty($this->data))
			return;

		$rows = [];
		foreach ($this->data as $row) {
			$rows[] = vsprintf(implode(',', array_map([$this->db, 'formatToken'], $row)), array_map([$this->db, 'escape'], $row));
		}

		$query = "{$this->operation} INTO {$this->table} (`" . implode('`,`', $this->fields) . "`) VALUES (" . implode('),(', $rows) . ")";

		//var_dump($query);die();

		$this->data = [];
	}

}
