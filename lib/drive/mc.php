<?php
namespace Tango\Drive;

use Tango\Core\Config;

Config::setFileDefault('memcache', dirname(dirname(__DIR__)).'/config/memcache.php');

class MC extends \Memcached {

	static protected $_lInstance = [];

	public static function getInstance($sName = 'default') {
		$o =& self::$_lInstance[$sName];
		if (!$o) {
			$o = new self();

			$aConfig = Config::get('memcache');

			$o->setOptions($aConfig['option']);

			$aServer =& $aConfig['server'][$sName];
			if (!$aServer) {
				throw new \Tango\Core\TangoException('server "'.$sName.'" not found');
			}
			$o->addServers($aServer);

			// $o->setOption(Memcached::OPT_BINARY_PROTOCOL, TRUE);
			// 二进制协议目前有问题 https://bugs.php.net/bug.php?id=59434
		}
		return $o;
	}
}
