<?php

/**
 * Wrapper for SplFileObject with proper key-value CSV support. Assumes that the first line
 * contains row headers unless $keys is passed on instantiation.
 * 
 * @uses SeekableIterator
 * @author Mikkel Paulson <me@mikkel.ca> 
 */
class CSV implements SeekableIterator {

	public $lines = 0;

	private $file = null;
	private $mode = null;
	private $keys = [];
	private $header = null;

	/**
	 * Class constructor. Initializes SplFileObject and parses first line as keys.
	 * 
	 * @param string $path Path to the CSV file to load
	 * @param string $mode Edit mode (see fopen() in PHP docs); writing is not currently supported
	 * @param array  $keys Column keys; if empty, treats first row as keys
	 * 
	 * @return void
	 */
	public function __construct($path, $mode = 'r', $keys = []) {
		$this->file = new SplFileObject($path, $mode);

		if (empty($keys)) {
			$this->file->next();
			$this->keys = array_map('trim', str_getcsv(preg_replace('/^[^[:print:]]+/', '', $this->file->current())));
			$this->lines = shell_exec("wc -l $path") - 1;
			$this->header = 1;
		} else {
			$this->keys = $keys;
			$this->header = 0;
		}

		$this->mode = $mode;
	}



	// SEEKABLEITERATOR METHODS

	public function current() {
		if ($current = $this->file->current()) {
			return array_combine($this->keys, array_map('trim', str_getcsv($current)));
		} else {
			return $current;
		}
	}
	public function key() { return $this->file->key(); }
	public function next() { $this->file->next(); }
	public function rewind() { $this->file->seek($this->header); }
	public function seek($position) { $this->file->seek($position + $this->header); }
	public function valid() { return (bool) $this->file->current(); }

}
