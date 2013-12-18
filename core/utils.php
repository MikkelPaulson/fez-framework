<?php

class Utils {

	public static function distanceToDegrees($distance, $latitude) {
	}

	public static function degreesToDistance($degrees, $latitude) {
	}

	public static function timeElapsed($message = '') {
		static $start_time = null;

		$time = microtime(true);

		if ($start_time === null) {
			echo "$message: [baseline]\n";
			$start_time = $time;
		} else {
			echo "$message: " . round($time - $start_time, 4) . "s\n";
		}
	}
}
