<?php
return [
	'default' => [
		'debug' => FALSE,
		'db' => 'tango_phpunit_test',
		'custom' => 's1',
	],
	'server' => [
		'IdGen' => [
			'collection' => 'id_gen',
			'custom' => 'ns',
		],
		'User' => [
			'collection' => 'user',
		],
		'UserAI' => [
			'collection' => 'user_ai',
		],
		'Map' => [
			'collection' => 'map',
		],
	],
	'custom' => [
		'ns' => [
			'capacity' => 0,
			'pool' => [
				'127.0.0.1:12306',
			],
		],
		's1' => [
			'capacity' => 10000,
			'pool' => [
				'127.0.0.1:12306',
				'127.0.0.1:12307',
				'127.0.0.1:12308',
			],
		],
	],
];
