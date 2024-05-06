<?php

require 'config.php';

set_time_limit(30);
error_reporting(0);

class RateLimiter {
	private $sessionLifetime;
	private $maxRequests;

	public function __construct($sessionLifetime = 60, $maxRequests = 1000) {
		global $sessionLifetime, $maxRequests;
		$this->sessionLifetime = $sessionLifetime;
		$this->maxRequests = $maxRequests;

		if (!session_id()) {
			$this->startSessionBasedOnIP();
		}

		if (!isset($_SESSION['tzero'])) {
			$_SESSION['tzero'] = time();
		}

		$sinceIntervalStart = time() - $_SESSION['tzero'];

		if ($sinceIntervalStart > $this->sessionLifetime) {
			$_SESSION['tzero'] = time();
			$_SESSION['hits'] = 1;
		} else {
			$_SESSION['hits']++;
		}

		if ($_SESSION['hits'] > $this->maxRequests) {
			header('HTTP/1.1 429 Too Many Requests');
			die('Too many requests');
		}
	}

	private function startSessionBasedOnIP() {
		session_start();
		$ipHash = md5($_SERVER['REMOTE_ADDR']);
		session_id($ipHash);
	}
}

class TileProxy {
	private $storage;
	private $operator;
	private $tileserver;
	private $trustedHosts;
	private $ttl;

	public function __construct($storage, $operator, $tileserver, $trustedHosts, $ttl) {
		$this->storage = $storage;
		$this->operator = $operator;
		$this->tileserver = $tileserver;
		$this->trustedHosts = $trustedHosts;
		$this->ttl = $ttl;

		$this->limitRequests();
		$this->processRequest();
	}

	private function limitRequests() {
		new RateLimiter();
	}

	private function downloadTile($z, $x, $y) {
		set_time_limit(0);
		$source = $this->tileserver;
		$source = str_replace('{x}', $x, $source);
		$source = str_replace('{y}', $y, $source);
		$source = str_replace('{z}', $z, $source);

		$filePath = $this->storage . $z . '/' . $x . '/' . $y . '.png';
		$fh = fopen($filePath, 'w');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $source);
		curl_setopt($ch, CURLOPT_FILE, $fh);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Tile Proxy, Operator: ' . $this->operator);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);

		if (curl_exec($ch)) {
			curl_close($ch);
			fclose($fh);
		} else {
			curl_close($ch);
			fclose($fh);
			unlink($filePath);
			throw new Exception('Error providing tile');
		}
	}

	private function processRequest() {
		$z = filter_var($_GET['z'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 20]]);
		$x = filter_var($_GET['x'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		$y = filter_var($_GET['y'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		if ($z === false || $x === false || $y === false) {
			header('HTTP/1.0 400 Bad Request');
			die('Invalid parameters');
		}

		// CORS
		$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
		if (in_array(parse_url($origin, PHP_URL_HOST), $this->trustedHosts)) {
			header('Access-Control-Allow-Origin: ' . $origin);
		}

		// Referer-Check
		$referer = $_SERVER['HTTP_REFERER'] ?? '';
		if (!empty($referer)) {
			$refererHost = parse_url($referer, PHP_URL_HOST);
			if (!in_array($refererHost, $this->trustedHosts)) {
				header('HTTP/1.1 403 Forbidden');
				die('Access denied');
			}
		}

		// Check if directories exist and download tile
		$tileDirectory = $this->storage . $z . '/' . $x;
		if (!is_dir($tileDirectory)) {
			mkdir($tileDirectory, 0750, true);
		}

		$tilePath = $tileDirectory . '/' . $y . '.png';
		if (file_exists($tilePath)) {
			$age = filemtime($tilePath);
			$modified = gmdate('D, d M Y H:i:s', $age) .' GMT';
			if ($age + $this->ttl <= time()) {
				$this->downloadTile($z, $x, $y);
			}
		} else {
			// If the tile doesn't exist, download it
			$this->downloadTile($z, $x, $y);
			$modified = gmdate('D, d M Y H:i:s', time()) .' GMT';
		}

		// Output the tile image
		if (file_exists($tilePath)) {
			$expires = gmdate('D, d M Y H:i:s', time() + $this->ttl) .' GMT';
			header('Expires: ' . $expires);
			header('Last-Modified: ' . $modified);
			header('Content-Type: image/png');
			header('Cache-Control: public, max-age=' . $this->ttl);
			readfile($this->storage . $z . '/' . $x . '/' . $y . '.png');
		} else {
			header('HTTP/1.0 500 Internal Server Error');
			die('Internal Server Error');
		}
	}
}

// Usage
new TileProxy($storage, $operator, $tileserver, $trustedHosts, $ttl);
