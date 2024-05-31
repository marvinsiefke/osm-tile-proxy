<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 600);

require 'config/config.php';
require 'src/tileProxy.php';

if ($config['cron'] !== true) {
	die('Cron is not allowed.');
}

if ($config['forceCli'] === true && php_sapi_name() !== 'cli') {
	die('This file can only be run from the command line.');
}

$tileProxy = new tileProxy($config['operator'], $config['trustedHosts'], $config['cron'], $config['browserTtl'], $config['tileserver'], $config['tolerance'], $config['storage']);
$tileProxy->processQueue();
