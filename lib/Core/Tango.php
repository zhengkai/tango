<?php
/**
 * This file is part of the Tango Framework.
 *
 * (c) Zheng Kai <zhengkai@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tango\Core;

Config::setFileDefault('tango', dirname(__DIR__).'/Config/tango.php');

/**
 * 框架核心
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Tango {

	/** www 传给 tpl 的变量，经过 HTML 过滤 */
	public static $T = [];

	/** www 传给 tpl 的变量，没有 HTML 过滤 */
	public static $D = [];

	/** 经过 \Tango\Core\Filter 过滤的输入参数（原 $_GET/$_POST） */
	public static $IN = [];

	/** 页面扩展名（给 Page 用） */
	protected static $_sExt  = 'html';

	/** 确保只初始化一次 */
	protected static $_bInit = FALSE;

	/** 用于计算 www 的执行时间 */
	protected static $_fTimeController;

	/** 结束标志（第一次结束的时候会被 register_shutdown_function 函数激活、继续执行之后的 tpl） */
	protected static $_bShutdown = FALSE;

	/** output buffering */
	protected static $_bOB = TRUE; //

	/** debug 模式 */
	protected static $_bDebug;

	/** 脚本标识 */
	protected static $_sScriptID;

	/** www 遇到哪些报错时不再继续解析 tpl */
	protected static $_lErrorStopCode = [
		E_ERROR,
		E_CORE_ERROR,
		E_COMPILE_ERROR,
		E_USER_ERROR,
		E_RECOVERABLE_ERROR,
	];

	/** 在脚本结束时可以触发的操作类型 */
	protected static $_lEndTrigger = [
		'Delay' => NULL,
		'Mongo' => NULL,
	];

	/**
	 * 在脚本结束时是否触发某类操作
	 *
	 * @param string $sType
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setEndTrigger($sType) {
		if (!array_key_exists($sType, self::$_lEndTrigger)) {
			throw new TangoException('unknown type "' . $sType . '"');
		}
		self::$_lEndTrigger = TRUE;
	}

	/**
	 * 用于计算 www 的执行时间
	 *
	 * @static
	 * @access public
	 * @return integer
	 */
	public static function getTimeController() {
		return self::$_fTimeController;
	}

	/** 生成脚本标识 */
	public static function getScriptID() {
		if (!is_null(self::$_sScriptID)) {
			return self::$_sScriptID;
		}
		self::$_sScriptID = uniqid().sprintf('%07x', mt_rand(0, 0xfffffff));
		return self::$_sScriptID;
	}

	/**
	 * 是否在 debug 模式
	 *
	 * @static
	 * @access public
	 * @return bool
	 */
	public static function isDebug() {
		if (!is_null(self::$_bDebug)) {
			return self::$_bDebug;
		}
		$aConfig = Config::get('tango');
		self::$_bDebug = $aConfig['debug']['enable']
			&& in_array($_SERVER["REMOTE_ADDR"], $aConfig['debug']['allow_ip']);
		return self::$_bDebug;
	}

	/**
	 * 是否已初始化
	 *
	 * @static
	 * @access public
	 * @return bool
	 */
	public static function isInit() {
		return self::$_bInit;
	}

	/**
	 * 初始化
	 *
	 * @param boolean $bForce 为 ture 时即使在 cli 下也执行
	 * @static
	 * @access public
	 * @return void
	 */
	public static function init($bForce = FALSE) {

		if (PHP_SAPI === 'cli' && !$bForce) {
			return ;
		}

		if (self::$_bInit) {
			die('ready inited');
		}
		self::$_bInit = TRUE;

		if (strpos($_SERVER['SCRIPT_NAME'], '..') !== FALSE) {
			die('attack alert');
		}

		$lNameInfo = pathinfo($_SERVER['SCRIPT_NAME']);
		if ($lNameInfo['extension'] != 'php') {
			die('not a php');
		}

		$lFileExt = explode('.', $lNameInfo['filename'], 20);
		if (count($lFileExt) > 1) {
			array_shift($lFileExt);
			while ($sExt = array_pop($lFileExt)) {
				switch ((string)$sExt) {
					case 'unob':
						self::$_bOB = FALSE;
						break;
					case 'post':
						break;
					default:
						if (self::$_bOB) {
							Page::set($sExt, TRUE);
						}
						break 2;
				}
			}
		}

		define('SITE_ROOT', dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file']));

		$sFile = SITE_ROOT.'/www'.$_SERVER['SCRIPT_NAME'];

		$_SERVER['SCRIPT_FILENAME'] = $sFile;

		if (self::$_bOB) {
			register_shutdown_function([__CLASS__, 'shutdown']);
			ob_start();
		}

		self::_start();

		if (self::$_bOB) {
			self::_end();
		}
	}

	/**
	 * 加载 www
	 *
	 * @static
	 * @access protected
	 * @return void
	 */
	protected static function _start() {

		$T =& self::$T;
		$D =& self::$D;
		$_IN =& self::$IN;

		require $_SERVER['SCRIPT_FILENAME'];

		if (!self::$_fTimeController) {
			self::$_fTimeController = microtime(TRUE);
		}
	}

	/**
	 * 页面结束（有错误抛异常，或者继续走 tpl）
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function _end() {

		if ($aError = self::getStopError()) {
			ob_clean();
			TangoException::handler(new \ErrorException($aError['message'], 0, 1, $aError['file'], $aError['line']), FALSE);
		} else {
			$s = ob_get_clean();
			if ($s) {
				echo $s;
				self::$_bShutdown = TRUE;
				return;
			}
		}
		Page::parse();

		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		}
		if (self::$_lEndTrigger['mongo']) {
			\Tango\Drive\Mongo::save();
		}
		if (self::$_lEndTrigger['delay']) {
			Delay::run();
		}
	}

	/**
	 * 要么错误严重返回错误信息，要么返回 false 表示可以继续执行
	 *
	 * @static
	 * @access public
	 * @return boolean|array
	 */
	public static function getStopError() {
		$aError = error_get_last();
		if (!$aError) {
			return FALSE;
		}
		if (!self::isStopError($aError['type'])) {
			return FALSE;
		}
		return $aError;
	}

	/**
	 * 是否判断错误级别是否需要中断脚本
	 *
	 * @param integer $iError
	 * @static
	 * @access public
	 * @return bool
	 */
	public static function isStopError($iError) {
		return in_array($iError, self::$_lErrorStopCode);
	}

	/**
	 * 结束标志（第一次结束的时候会被 register_shutdown_function 函数激活、继续执行之后的 tpl）
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function shutdown() {

		if (self::$_bShutdown) { // run once only
			return;
		}
		self::$_bShutdown = TRUE;

		self::_end();
	}
}
