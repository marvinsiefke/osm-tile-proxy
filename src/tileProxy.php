<?php

class tileProxy {
	private $operator;
	private $trustedHosts;
	private $browserTtl;
	private $tileservers;
	private $tolerance;
	private $storage;
	private $rateLimiter;
	public $cron;


	// Constructor
	public function __construct($operator, $trustedHosts = [], $cron = [], $browserTtl = 86400 * 7, $tileservers = [], $tolerance = 0.5, $storage = 'tmp/', $ratelimits = []) {
		$this->operator = $operator;
		$this->trustedHosts = $trustedHosts;
		$this->cron = $cron;
		$this->browserTtl = $browserTtl;
		$this->tolerance = $tolerance;
		$this->storage = $storage;
		$this->queuePath = $this->storage . 'queue.txt';
		$this->tileservers = empty($tileservers) ? ['openstreetmap' => ['urls' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', 'ttl' => 86400 * 31]] : $tileservers;
		$this->cron = empty($cron) ? ['enabled' => true, 'forceCli' => true, 'batchSize' => 25] : $cron;
		$this->ratelimits = empty($ratelimits) ? ['enabled' => true, 'durationInterval' => 60, 'durationHardBan' => 21600, 'maxHits' => 1500, 'maxSoftBans' => 50] : $ratelimits;

		if (class_exists('rateLimiter') && array_key_exists('enabled', $this->ratelimits) && $this->ratelimits['enabled'] === true) {
			$this->rateLimiter = new rateLimiter($this->ratelimits['durationInterval'], $this->ratelimits['durationHardBan'], $this->ratelimits['maxHits'], $this->ratelimits['maxSoftBans']);
		}
	}


	// Converts tile parameters to latitude and longitude
	private function tileToLatLon($z, $x, $y) {
		$n = pow(2, $z);
		$lonDeg = $x / $n * 360 - 180;
		$latRad = atan(sinh(pi() * (1 - 2 * $y / $n)));
		$latDeg = rad2deg($latRad);
		return [$latDeg, $lonDeg];
	}


	// Checks if the given latitudes and longitudes are in bounds (respecting the tolerance)
	private function isInBounds($lat, $lon, $bounds) {
		return $lat >= ($bounds[0][0] - $this->tolerance) && $lat <= ($bounds[1][0] + $this->tolerance) && $lon >= ($bounds[0][1] - $this->tolerance) && $lon <= ($bounds[1][1] + $this->tolerance);
	}


	// Downloads tile
	private function downloadTile($z, $x, $y, $tileserver) {
		$tileservers = $this->tileservers[$tileserver]['urls'];
		$url = is_array($tileservers) ? $tileservers[array_rand($tileservers)] : $tileservers;
		$source = str_replace(['{x}', '{y}', '{z}'], [$x, $y, $z], $url);
		$filePath = $this->storage . $tileserver . '/' . $z . '/' . $x . '/' . $y . '.png';

		if (!is_dir(dirname($filePath))) {
			mkdir(dirname($filePath), 0750, true);
		}

		$fh = fopen($filePath, 'w');
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $source);
		curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // improves really bad performance!
		curl_setopt($ch, CURLOPT_FILE, $fh);
		curl_setopt($ch, CURLOPT_TIMEOUT, 25); 
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Tile Proxy, Operator: ' . $this->operator);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 120);
		curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 60);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, false); 
		curl_setopt($ch, CURLOPT_FORBID_REUSE, false); 
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate'); 
		curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 86400); 

		// Implement retries for temporary errors
		$maxRetries = 2;
		$retryCount = 0;
		$success = false;

		while ($retryCount < $maxRetries && !$success) {
			$success = curl_exec($ch);
			if (!$success) {
				$error = curl_errno($ch);
				if (in_array($error, [CURLE_OPERATION_TIMEOUTED, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT])) {
					$retryCount++;
					sleep(1); // Wait a bit before retrying
				} else {
					break; // Non-recoverable error
				}
			}
		}

		curl_close($ch);
		fclose($fh);

		if (!$success) {
			unlink($filePath); // Delete if download failed
			error_log('Error getting tile. cURL Error (' . $error . '): ' . curl_strerror($error), 0);
			throw new Exception('Error getting tile');
		}
	}


	// Returns the current referer if given
	private function getReferer() {
		if (!empty($_SERVER['HTTP_REFERER'])) {
			return parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
		}

		return false;
	}


	// Returns the current host if given
	private function getHost() {
		if (!empty($_SERVER['HTTP_HOST'])) {
			$host = filter_var($_SERVER['HTTP_HOST'], FILTER_SANITIZE_URL);
			return $host;
		}

		return false;
	}


	// Returns the intended tileserver
	private function intendedTileserver() {
		$host = $this->getHost();
		$fallback = array_key_first($this->tileservers);
		$intendedTileserver = $fallback;

		if (array_key_exists('tileserver', $this->trustedHosts[$host]) && array_key_exists($this->trustedHosts[$host]['tileserver'], $this->tileservers)) {
			$intendedTileserver = $this->trustedHosts[$host]['tileserver'];
		}

		return $intendedTileserver;
	}


	// Adds tile to queue
	private function queueTile($z, $x, $y, $tileserver) {
		if (!is_dir(dirname($this->queuePath))) {
			mkdir(dirname($this->queuePath), 0750, true);
		}

		$entry = "$z,$x,$y,$tileserver\n";
		file_put_contents($this->queuePath, $entry, FILE_APPEND | LOCK_EX);
	}


	// Processes queue for the cronjob
	public function processQueue() {
		if (!file_exists($this->queuePath)) {
			return;
		}

		$queue = file($this->queuePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!$queue) {
			return;
		}

		$batchSize = array_key_exists('batchSize', $this->cron) ? $this->cron['batchSize'] : 30;
		$batch = array_slice($queue, 0, $batchSize);
		$remaining = array_slice($queue, $batchSize);

		foreach ($batch as $line) {
			list($z, $x, $y, $tileserver) = explode(',', $line);
			try {
				echo "Updating $z/$x/$y from $tileserver ...\n";
				$this->downloadTile($z, $x, $y, $tileserver);
			} catch (Exception $error) {
				echo "Error on updating $z/$x/$y from $tileserver: ",$error->getMessage(),"\n";
				error_log('Failed to update tile '.$z.'/'.$x.'/'.$y.' from '.$tileserver.': '.$error->getMessage(), 0);
			}
		}

		file_put_contents($this->queuePath, implode("\n", $remaining) . (empty($remaining) ? '' : "\n"));
	}


	// Validates and processes request
	public function processRequest() {
		header('X-Content-Type-Options: nosniff');
		header('X-XSS-Protection: 1; mode=block');

		// Validates parameters to patterns
		$z = filter_input(INPUT_GET, 'z', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 20]]);
		$x = filter_input(INPUT_GET, 'x', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		$y = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

		if ($z === false || $x === false || $y === false) {
			if($this->rateLimiter) {
				$this->rateLimiter->hardBan();
			}
			header('HTTP/1.1 400 Bad Request');
			die('Invalid parameters.');
		}

		// Checks if the host name is given
		$host = $this->getHost();
		if(!$host) {
			$this->rateLimiter ? $this->rateLimiter->hardBan() : null;
			header('HTTP/1.1 400 Bad Request');
			die('Invalid parameters.');
		} 

		if (!empty($this->trustedHosts)) {
			if (!array_key_exists($host, $this->trustedHosts)) {
				$this->rateLimiter ? $this->rateLimiter->hardBan() : null;
				header('HTTP/1.1 400 Bad Request');
				die('Invalid parameters.');
			} else {
				// Checks if the referer is empty or otherwise allowed
				if($referer = $this->getReferer() && array_key_exists('referers', $this->trustedHosts[$host])) {
					$allowedReferers = $this->trustedHosts[$host]['referers'];
					if((is_array($allowedReferers) && !in_array($referer, $allowedReferers)) || (is_string($allowedReferers) && $allowedReferers != $referer)) {
						$this->rateLimiter ? $this->rateLimiter->softBan() : null;
						header('HTTP/1.1 400 Bad Request');
						die('Invalid parameters.');
					}
				}

				// Checks if the zoom level is allowed 
				if ((array_key_exists('maxZoom', $this->trustedHosts[$host]) && $z > $this->trustedHosts[$host]['maxZoom']) || (array_key_exists('minZoom', $this->trustedHosts[$host]) && $z < $this->trustedHosts[$host]['minZoom'])) {
					$this->rateLimiter ? $this->rateLimiter->softBan() : null;
					header('HTTP/1.1 400 Bad Request');
					die('Invalid parameters.');
				}

				// Checks if the tile is in specified bounds
				if (array_key_exists('maxBounds', $this->trustedHosts[$host])) {
					$latLon = $this->tileToLatLon($z, $x, $y);
					$bounds = $this->trustedHosts[$host]['maxBounds'];
					if (!$this->isInBounds($latLon[0], $latLon[1], $bounds)) {
						$this->rateLimiter ? $this->rateLimiter->softBan() : null;
						header('HTTP/1.1 400 Bad Request');
						die('Invalid parameters.');
					}
				}
			}
		}

		// Builds tile path 
		$tileserver = $this->intendedTileserver();
		$tilePath = $this->storage . $tileserver . '/' . $z . '/' . $x . '/' . $y . '.png';
		if (!is_dir(dirname($tilePath))) {
			mkdir(dirname($tilePath), 0750, true);
		}

		// Checks if tile is in stored in cache or requests a download
		if (file_exists($tilePath)) {
			$age = filemtime($tilePath);
			$modified = gmdate('D, d M Y H:i:s', $age) .' GMT';
			if ($age + $this->tileservers[$tileserver]['ttl'] <= time()) {
				if($this->cron === true) {
					$this->queueTile($z, $x, $y, $tileserver);
				} else {
					$this->downloadTile($z, $x, $y, $tileserver);
				}
			}
		} else {
			$this->downloadTile($z, $x, $y, $tileserver);
			$modified = gmdate('D, d M Y H:i:s', time()) .' GMT';
		}

		// Output
		if (file_exists($tilePath)) {
			header('HTTP/1.1 200 OK');
			header('Last-Modified: ' . $modified);
			header('Content-Type: image/png');
			header('Cache-Control: public, max-age=' . $this->browserTtl);
			readfile($tilePath);
		} else {
			header('HTTP/1.1 500 Internal Server Error');
			die('Internal Server Error');
		}
	}
}
