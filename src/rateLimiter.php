<?php

class rateLimiter {
	private $durationInterval;
	private $durationHardBan;
	private $maxHits;
	private $maxSoftBans;

	public function __construct($durationInterval = 60, $durationHardBan = 21600, $maxHits = 1500, $maxSoftBans = 50) {
		$this->durationInterval = $durationInterval;
		$this->durationHardBan = $durationHardBan;
		$this->maxHits = $maxHits;
		$this->maxSoftBans = $maxSoftBans;

		ini_set('session.auto_start', 0);
		ini_set('session.use_strict_mode', 0);

		$this->startSession();
		$this->initializeSession();
		$this->checkRateLimit();
	}

	private function getIp() {
		$ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

		foreach ($ipKeys as $key) {
			if (!empty($_SERVER[$key])) {
				$ip = $_SERVER[$key];
				
				if ($key === 'HTTP_X_FORWARDED_FOR') {
					// Handle the case where HTTP_X_FORWARDED_FOR contains multiple IP addresses
					$ipList = explode(',', $ip);
					$ip = trim(end($ipList)); 
				}

				if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
					return $ip;
				}
			}
		}

		return false;
	}

	private function startSession() {
		$ip = $this->getIp();
		if ($ip !== false) {
			$ipHash = md5($ip);
			session_id($ipHash);
		}
		
		session_start();
	}

	private function initializeSession() {
		$defaults = ['timeStarted' => time(), 'timeBannedUntil' => 0, 'countHits' => 0, 'countBans' => 0];

		foreach ($defaults as $key => $value) {
			$_SESSION[$key] = $_SESSION[$key] ?? $value;
		}
	}

	private function checkRateLimit() {
		if ($_SESSION['timeBannedUntil'] > time()) {
			header('HTTP/1.1 400 Bad Request');
			die('You have been banned. Please try again later.');
		}

		$sinceIntervalStart = time() - $_SESSION['timeStarted'];

		if ($sinceIntervalStart > $this->durationInterval) {
			$_SESSION['timeStarted'] = time();
			$_SESSION['countHits'] = 1;
		} else {
			$_SESSION['countHits']++;
		}

		if ($_SESSION['countHits'] > $this->maxHits) {
			$this->softBan();
		}
	}

	public function softBan() {
		if ($_SESSION['countBans'] < $this->maxSoftBans) {
			$_SESSION['countBans']++;
			if ($_SESSION['countBans'] >= $this->maxSoftBans) {
				$this->hardBan();
			}
		}

		header('HTTP/1.1 429 Too Many Requests');
		die('Too many requests');
	}

	public function hardBan() {
		$_SESSION['timeBannedUntil'] = time() + $this->durationHardBan;
		header('HTTP/1.1 400 Bad Request');
		error_log('Hard ban executed for session '.session_id().' after '.$_SESSION['countBans'].' soft bans till '.date('d-M-Y H:i:s', $_SESSION['timeBannedUntil']), 0);
		die('You have been banned.');
	}
}