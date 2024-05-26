<?php

// Proxy settings
$operator = 'admin@domain.com';
$trustedHosts = [
	'proxy.domain.de' => [
		'referers' => [
			'domain.de' // allowed referers
		],
		'maxBounds' => [ 
			[47.25, 5.875], // south west
			[53.05, 13.85] // north east
		],
		'maxZoom' => 18,
		'minZoom' => 11
	],
	'proxy.domain.com' => [], // without limitations
];
