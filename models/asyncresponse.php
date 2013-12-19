<?php

class AsyncResponse {

	private $ch = null;
	private $data = [];

	public function __construct($ch) {
		$this->ch = $ch;
	}

	/**
	 * Magic method to allow direct access of returned value. cURL request is loaded on access
	 * rather than on object instantiation, so blocking doesn't occur until this point.
	 *
	 * Supported magic properties:
	 *
	 * * string $text -- raw text response
	 * * array $headers -- associative array of returned headers
	 * * stdClass $json -- JSON-decoded response
	 * * SimpleXMLElement $xml -- parsed XML response
	 * 
	 * @param string $param The requested object parameter
	 * 
	 * @return mixed See method description for types
	 */
	public function __get($param) {
		if (!in_array($param, ['text', 'headers', 'json', 'xml']))
			return;

		// fetch data from cURL if it has not yet been loaded
		if (!array_key_exists('text', $this->data)) {
			$data = curl_multi_getcontent($this->ch);

			$header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
			$this->data['text'] = substr($data, $header_size);
			$headers_raw = array_map('trim', explode("\n", substr($data, 0, $header_size)));

			foreach ($headers_raw as $header) {
				if (strpos(':', $header) !== false) {
					list($key, $value) = preg_split('/: */', $header, 2);
					$this->data['headers'][$key] = $value;
				} else {
					$this->data['headers'][] = $value;
				}
			}

			curl_multi_remove_handle($this->ch);
			curl_close($this->ch);
		}

		// return requested data, parsing it first if required
		if ($param == 'text' || $param == 'headers') {
			return $this->data[$param];
		} elseif ($param == 'json') {
			return array_key_exists('json', $this->data) ? $this->data['json'] : ($this->data['json'] = json_decode($this->data['text']));
		} elseif ($param == 'xml') {
			return array_key_exists('xml', $this->data) ? $this->data['xml'] : ($this->data['xml'] = simplexml_load_string($this->data['text']));
		}
	}

}
