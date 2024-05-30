<?php

// Proxy settings
$operator = 'admin@pepper.green';
$trustedHosts = [
	'osm.pepper.cloud' => [
		'referers' => [
			'pepper.cloud', 'kdm.pepper.cloud'
		],
	],
	'karten.pfd-falkensee.de' => [
		'referers' => [
			'pfd-falkensee.de'
		],
		'maxBounds' => [
			[52.450, 12.930],
			[52.700, 13.250]
		],
		'maxZoom' => 18,
		'minZoom' => 12
	],
	'karten.jugendforum-fks.de' => [
		'referers' => [
			'jugendforum-fks.de'
		],
		'maxBounds' => [
			[52.450, 12.930],
			[52.700, 13.250]
		],
		'maxZoom' => 18,
		'minZoom' => 12
	],
	'karten.jugendbeiratfalkensee.eu' => [
		'referers' => [
			'jugendbeiratfalkensee.eu'
		],
		'maxBounds' => [
			[52.450, 12.930],
			[52.700, 13.250]
		],
		'maxZoom' => 18,
		'minZoom' => 12
	],
	'osm.critical-mass-falkensee.de' => [
		'referers' => [
			'critical-mass-falkensee.de'
		],
		'maxBounds' => [
			[52.450, 12.930],
			[52.700, 13.250]
		],
		'maxZoom' => 18,
		'minZoom' => 12
	]
];