<?php

if (php_sapi_name() !== 'cli') {
	die('This file can only be run from the command line.');
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 600);

require 'config/config.php';
require 'src/tileProxy.php';

$tileProxy = new tileProxy($operator, $trustedHosts);
$tileProxy->processQueue();