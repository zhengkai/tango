<?php
return [
	'site_url' => 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/',
	'salt' => '',
	'cookie_domain' => '.' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
];
