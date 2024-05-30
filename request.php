<?php
/*
 * pepper-osmproxy
 * Author: Marvin Siefke
 * Author URI: https://pepper.green
 * GitHub: https://github.com/marvinsiefke/pepper-osmproxy/
 * License: GNU GPLv3
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 30);

require 'config/config.php';
require 'src/rateLimiter.php';
require 'src/tileProxy.php';

$tileProxy = new tileProxy($operator, $trustedHosts);
$tileProxy->processRequest();
