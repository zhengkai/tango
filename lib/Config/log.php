<?php
return [
	'enable' => FALSE,
	'debug_path' => 'tango.d/', // 相对目录基于临时目录（Config/tango 的 tmp_dir）
	'max_size' => 1000000000, // 1GB
	'disk_free_space' => 100000000, // 100MB
	'time_format' => 'm-d H:i:s',
	'file' => ini_get('error_log'),
];
