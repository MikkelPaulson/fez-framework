<?php

class CLIProgressBar {

	private $msg = null;
	private $msg_orig = null;
	private $cur = 0;
	private $last = null;
	private $max = null;

	private $start = null;
	private $end = null;

	private $c = [];

	public function __construct($msg, $max = 0) {
		$this->start = microtime(true);

		$this->msg_orig = $this->msg = $msg;
		$this->max = $max;

		$this->c = [
			'reset' => chr(27).'[0m',
			'blue' => chr(27).'[34m',
			'red' => chr(27).'[1;31m',
			'green' => chr(27).'[32m',
			'grey' => chr(27).'[37m',
		];

		$this->draw();
	}

	public function __destruct() {
		$this->end = microtime(true);
		$this->cur = $this->max;
		$this->draw();

		echo "\n";
	}

	public function __set($var, $val) {
		$this->$var = $val;
		$this->draw();
	}

	public function __get($var) {
		return $this->$var;
	}

	public function update($cur) {
		$this->cur = $cur;
		$this->draw();
	}

	private function draw() {
		if ($this->last > microtime(true) - 0.5 && $this->cur != $this->max)
			return;

		$width = (int) `tput cols`;


		if (!empty($this->end)) {
			$eta = $this->end - $this->start;
			$this->msg = $this->msg_orig;
		} elseif ($this->cur) {
			$eta = round(($this->max - $this->cur) / $this->cur * (microtime(true) - $this->start));
		} else {
			$eta = 0;
		}

		$eta = ($eta < 3600) ? sprintf('%d:%02d', floor($eta / 60), $eta % 60) : sprintf('%d:%02d:%02d', floor($eta / 3600), floor(($eta % 3600) / 60), $eta % 60);

		$before = '  [';
		$after = $this->max ? ("] " . str_pad(str_pad($this->cur, strlen($this->max), ' ', STR_PAD_LEFT) . "/$this->max", 15, ' ', STR_PAD_BOTH) . " " . str_pad(round($this->cur / $this->max * 100), 3, ' ', STR_PAD_LEFT) . "% " . str_pad($eta, 6, ' ', STR_PAD_LEFT) . "  ") : ']  ';

		$progressbar_len = $width - strlen($before) - strlen($after);
		$progressbar = ($this->max?'==':'--') . $this->msg . str_repeat($this->max?'=':'-', max($progressbar_len - strlen($this->msg) - 2, 0));

		echo "\r";


		if (!$this->max) {
			echo $before . $this->c['blue'] . $progressbar . $this->c['reset'] . $after;
		} elseif ($this->cur == $this->max) {
			echo $before . $this->c['green'] . $progressbar . $this->c['reset'] . $after;
		} elseif (!$this->cur) {
			echo $before . $this->c['grey'] . $progressbar . $this->c['reset'] . $after;
		} else {
			$break = round($progressbar_len * $this->cur / $this->max);
			echo $before . $this->c['red'] . substr($progressbar, 0, $break) . $this->c['grey'] . substr($progressbar, $break) . $this->c['reset'] . $after;
		}

		$this->last = microtime(true);
	}

}
