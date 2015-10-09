<?php
return [
	'enable' => TRUE,
	'option' => [
		Memcached::OPT_BINARY_PROTOCOL => TRUE,
		Memcached::OPT_COMPRESSION => TRUE,
		Memcached::OPT_LIBKETAMA_COMPATIBLE => FALSE,
		Memcached::OPT_PREFIX_KEY => 't_',
		Memcached::OPT_CONNECT_TIMEOUT => 300,
	],
	'server' => [
		'default' => [
			['memcached', 11211, 64],
		],
	],
];
