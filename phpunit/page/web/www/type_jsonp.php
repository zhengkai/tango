<?php
$T['foo'] = 'bar';

$_GET['callback'] = '中文utf8测试';

self::setContentType('jsonp');
