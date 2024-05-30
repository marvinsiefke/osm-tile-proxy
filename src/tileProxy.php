<?php

class tileProxy {
	private $operator;
	private $trustedHosts;
	private $cron;
	private $serverTtl;
	private $browserTtl;
	private $tileserver;
	private $tolerance;
	private $storage;
	private $rateLimiter;

	public function __construct($operator, $trustedHosts = [], $cron = true, $serverTtl = 86400 * 31, $browserTtl = 86400 * 7, $tileserver = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', $tolerance = 0.5, $storage = 'tmp/') {
		$this->operator = $operator;
		$this->trustedHosts = $trustedHosts;
		$this->cron = $cron;
		$this->serverTtl = $serverTtl;
		$this->browserTtl = $browserTtl;
		$this->tileserver = $tileserver;
		$this->tolerance = $tolerance;
		$this->storage = $storage;
		$this->queuePath = $this->storage . 'queue.txt';

		if (class_exists('rateLimiter')) {
			$this->rateLimiter = new rateLimiter();
		}
	}

	private function tileToLatLon($z, $x, $y) {
		$n = pow(2, $z);
		$lonDeg = $x / $n * 360 - 180;
		$latRad = atan(sinh(pi() * (1 - 2 * $y / $n)));
		$latDeg = rad2deg($latRad);
		return [$latDeg, $lonDeg];
	}

	private function isInBounds($lat, $lon, $bounds) {
		return $lat >= ($bounds[0][0] - $this->tolerance) && $lat <= ($bounds[1][0] + $this->tolerance) && $lon >= ($bounds[0][1] - $this->tolerance) && $lon <= ($bounds[1][1] + $this->tolerance);
	}

	private function downloadTile($z, $x, $y) {
		$source = str_replace(['{x}', '{y}', '{z}'], [$x, $y, $z], $this->tileserver);

		$filePath = $this->storage . $z . '/' . $x . '/' . $y . '.png';
		if (!is_dir(dirname($filePath))) {
			mkdir(dirname($filePath), 0750, true);
		}

		$fh = fopen($filePath, 'w');
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $source);
		curl_setopt($ch, CURLOPT_FILE, $fh);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Tile Proxy, Operator: ' . $this->operator);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);

		if (!curl_exec($ch)) {
			unlink($filePath);
			error_log('Error getting tile. cURL Error ('.curl_errno($ch).'): '.curl_error($ch), 0);
			throw new Exception('Error getting tile');
		}

		curl_close($ch);
		fclose($fh);
	}

	private function queueTile($z, $x, $y) {
		if (!is_dir(dirname($this->queuePath))) {
			mkdir(dirname($this->queuePath), 0750, true);
		}

		$entry = "$z,$x,$y\n";
		file_put_contents($this->queuePath, $entry, FILE_APPEND | LOCK_EX);
	}

	public function processQueue() {
		if (!file_exists($this->queuePath)) {
			return;
		}

		$queue = file($this->queuePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!$queue) {
			return;
		}

		$batchSize = 25;
		$batch = array_slice($queue, 0, $batchSize);
		$remaining = array_slice($queue, $batchSize);

		foreach ($batch as $line) {
			list($z, $x, $y) = explode(',', $line);
			try {
				echo "Updating $z/$x/$y ...\n";
				$this->downloadTile($z, $x, $y);
			} catch (Exception $error) {
				echo "Error on updating $z/$x/$y: ",$error->getMessage(),"\n";
				error_log('Failed to update tile '.$z.'/'.$x.'/'.$y.': '.$error->getMessage(), 0);
			}
		}

		file_put_contents($this->queuePath, implode("\n", $remaining) . (empty($remaining) ? '' : "\n"));
	}

	public function processRequest() {
		header('X-Content-Type-Options: nosniff');
		header('X-XSS-Protection: 1; mode=block');

		$z = filter_input(INPUT_GET, 'z', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 20]]);
		$x = filter_input(INPUT_GET, 'x', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		$y = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		if ($z === false || $x === false || $y === false) {
			$this->rateLimiter->hardBan(); 
		}

		$host = $_SERVER['HTTP_HOST'];
		if (!empty($this->trustedHosts) && !empty($host)) {
			if (!array_key_exists($host, $this->trustedHosts)) {
				$this->rateLimiter->hardBan();
			} else {
				$referer = $_SERVER['HTTP_REFERER'] ?? '';
				if (!empty($referer) && array_key_exists('referers', $this->trustedHosts[$host])) {
					if (!in_array(parse_url($referer, PHP_URL_HOST), $this->trustedHosts[$host]['referers'])) {
						$this->rateLimiter->softBan(); 
					}
				}

				if (array_key_exists('maxZoom', $this->trustedHosts[$host])) {
					if($z > $this->trustedHosts[$host]['maxZoom']) {
						$this->rateLimiter->softBan();
					}
				}

				if (array_key_exists('minZoom', $this->trustedHosts[$host])) {
					if($z < $this->trustedHosts[$host]['minZoom']) {
						$this->rateLimiter->softBan();
					}
				}

				if (array_key_exists('maxBounds', $this->trustedHosts[$host])) {
					$latLon = $this->tileToLatLon($z, $x, $y);
					$bounds = $this->trustedHosts[$host]['maxBounds'];
					if (!$this->isInBounds($latLon[0], $latLon[1], $bounds)) {
						$this->rateLimiter->softBan();
					}
				}
			}
		}

		$tileDirectory = $this->storage . $z . '/' . $x;
		if (!is_dir($tileDirectory)) {
			mkdir($tileDirectory, 0750, true);
		}

		$tilePath = $tileDirectory . '/' . $y . '.png';
		if (file_exists($tilePath)) {
			$age = filemtime($tilePath);
			$modified = gmdate('D, d M Y H:i:s', $age) .' GMT';
			if ($age + $this->serverTtl <= time()) {
				if($this->cron === true) {
					$this->queueTile($z, $x, $y);
				} else {
					$this->downloadTile($z, $x, $y);
				}
			}
		} else {
			$this->downloadTile($z, $x, $y);
			$modified = gmdate('D, d M Y H:i:s', time()) .' GMT';
		}

		if (file_exists($tilePath)) {
			$expires = gmdate('D, d M Y H:i:s', time() + $this->browserTtl) .' GMT';
			header('HTTP/1.1 200 OK');
			header('Expires: ' . $expires);
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
