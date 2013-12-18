<?php

/**
 * Custom version of DateTime class with better constructor logic: accepts string timezone values,
 * integers as UNIX timestamps with timezone support, and proper support for MySQL datestamps
 * (YYYY-mm-dd HH:ii:ss).
 * 
 * @uses DateTime
 * @author Mikkel Paulson <me@mikkel.ca> 
 */
class DateTimeCustom extends DateTime {

	/**
	 * Class constructor. Wrapper for DateTime::_construct() with more versatile support for
	 * various conditions that otherwise require extra code.
	 * 
	 * @param string|int          $time     Time for new DateTime object
	 * @param string|DateTimeZone $timezone Timezone; accepts a string name (eg. "America/Montreal"
	 *                                      or DateTimeZone object
	 * 
	 * @return void
	 */
	public function __construct($time = 'now', $timezone = null) {
		if (is_string($timezone))
			$timezone = new DateTimeZone($timezone);

		if (is_int($time)) {
			parent::__construct("@$time");
			$this->setTimezone($timezone);
		} elseif (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]+):([0-9]{2}):([0-9]{2})$/', $time, $m)) {
			parent::__construct(null, $timezone);

			$this->setDate($m[1], $m[2], $m[3]);
			$this->setTime($m[4], $m[5], $m[6]);
		} else {
			parent::__construct($time, $timezone);
		}
	}

}
