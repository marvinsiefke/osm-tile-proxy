<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 600);

require 'config/config.php';
require 'src/tileProxy.php';

$tileProxy = new tileProxy($config['operator'], $config['trustedHosts'], $config['cron'], $config['browserTtl'], $config['tileservers'], $config['tolerance'], $config['storage'], ['enabled' => false]);

// Checks if cron is allowed
$enabled = array_key_exists('enabled', $tileProxy->cron) ? $tileProxy->cron['enabled'] : true;
if($enabled !== true) {
	die('Cron is not allowed.');
}

// Checks if cron is limited to run on cli
$forceCli = array_key_exists('forceCli', $tileProxy->cron) ? $tileProxy->cron['forceCli'] : true;
if($forceCli === true && php_sapi_name() !== 'cli') {
	die('This file can only be run from the command line.');
}

// Processes queue
$tileProxy->processQueue();
