<?php

// Tile server settings
$storage = 'cache/';
$tileserver = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
$ttl = 86400 * 31;  // 31 days
$operator = 'admin@domain.com';

// Access control
$trustedHosts = array(
	'domain.com',
	'anotherdomain.net'
);
