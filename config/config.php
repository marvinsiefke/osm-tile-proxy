<?php

// Proxy settings
$config['operator'] = 'admin@domain.com';
$config['browserTtl'] = 86400 * 7;
$config['tolerance'] = 0.5;
$config['storage'] = 'tmp/';
$config['cron'] = [
	'enabled' => true,
	'forceCli' => true,
	'batchSize' => 25,
];
$config['ratelimits'] = [
	'enabled' => true,
	'durationInterval' => 60,
	'durationHardBan' => 21600,
	'maxHits' => 1500,
	'maxSoftBans' => 50
];
$config['tileservers'] = [
	'openstreetmap' => [
		'urls' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
		'ttl' => 86400 * 45,
		'batch' => 25
	],
	'maptiler-osm' => [
		'urls' => 'https://api.maptiler.com/maps/openstreetmap/256/{z}/{x}/{y}@2x.jpg?key={KEY}',
		'ttl' => 86400 * 45,
		'batch' => 50
	],
	'maptiler-streets' => [
		'urls' => 'https://api.maptiler.com/maps/streets-v2/256/{z}/{x}/{y}@2x.png?key={KEY}',
		'ttl' => 86400 * 45,
		'batch' => 50
	],
	'maptiler-basic' => [
		'urls' => 'https://api.maptiler.com/maps/basic-v2/256/{z}/{x}/{y}@2x.png?key={KEY}',
		'ttl' => 86400 * 45,
		'batch' => 50
	],
];
$config['trustedHosts'] = [
	'proxy.domain.de' => [
		'tileserver' => 'openstreetmap',
		'referers' => 'domain.de',
		'maxBounds' => [ 
			[47.25, 5.875], // south west
			[53.05, 13.85] // north east
		],
		'maxZoom' => 18,
		'minZoom' => 11
	],
	'proxy.domain.com' => [], // without limitations
];
