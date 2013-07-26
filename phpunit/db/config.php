<?php
return [
	'default' => [
		'user' => 'tango',
		'password' => 'tango',
		'dsn' => 'unix_socket=/var/run/mysqld/mysqld.sock',
	],
	'server' => [
		'test' => [
			'dbname' => 'tango_test',
		],
	],
];
