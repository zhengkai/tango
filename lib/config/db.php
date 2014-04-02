<?php
return [
	'default' => [
		'user' => 'tango',
		'password' => 'tango',
		'dsn' => 'unix_socket=/var/run/mysqld/mysqld.sock',
		'debug' => FALSE,
	],
	'log' => [
		'collection' => FALSE,
		'debug' => FALSE,
		'max_size' => 1000000000, // 1GB
		'disk_free_space' => 100000000, // 100MB
	],
	'server' => [
	],
];
