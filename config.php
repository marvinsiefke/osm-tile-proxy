<?php

// Proxy settings
$operator = 'admin@domain.com';
$allowedReferers = [
	'domain.com' => [],
	'domain2.com' => [
		'hostname' => 'proxy.domain2.com',
		'maxBounds' => [
			[52.250, 12.550],
			[52.950, 13.750]
		]
	],
];
