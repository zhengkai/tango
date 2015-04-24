<?php
namespace Tango\Drive;

use Tango\Core\Config;
use Tango\Core\TangoException;
use Tango\Core\Log;

// http://docs.basho.com/riak/latest/dev/references/http/

class Riak {

	protected $_sName;
	protected static $_lInstance = [];

	protected $_iCURLError = 0;
	protected $_iJSONError = 0;

	protected $_aConfig = [];

	public static function getInstance($sName = 'default') {

		if (!$oClient =& self::$_lInstance[$sName]) {

			$lConfig = Config::get('riak');

			$lServer =& $lConfig['server'][$sName];
			if (!is_array($lServer)) {
				throw new TangoException('unknown server "'.$sName.'"');
			}

			$oClient = new self($lServer, $sName);
		}
		return $oClient;
	}

	public function __construct($lServer, $sName) {

		$this->_sName = $sName;

		$this->_aConfig = [
			'server' => $lServer,
		];
	}

	protected static function _json($mData) {
		return json_encode($mData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	public function _http($sURI, array $aOpt = []) {

		list($sHost, $iPort) = current($this->_aConfig['server']);

		$sURL = 'http://'.$sHost.':'.$iPort;

		$this->_iJSONError = 0;
		$this->_iCURLError = 0;

		$hCURL = curl_init();
		$aOpt += [
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_BINARYTRANSFER => TRUE,
			CURLOPT_ENCODING => 'gzip',
			//CURLOPT_VERBOSE => TRUE,
			CURLOPT_URL => $sURL.$sURI,
			CURLOPT_ENCODING => 'gzip,deflate',
		];

		curl_setopt_array($hCURL, $aOpt);

		Log::debug('riak', $sURI);

		$sReturn = curl_exec($hCURL);

		$aInfo = curl_getinfo($hCURL) + [
			'content_type' => '',
		];

		if ($aInfo['http_code'] < 200 || $aInfo['http_code'] > 299) {
			Log::debug('riak', $aInfo);
		}

		$this->_iCURLError = curl_errno($hCURL);

		curl_close($hCURL);

		if ($this->_iCURLError) {
			return FALSE;
		}

		Log::debug('riak', sprintf('%7s %s', ' ', $aInfo['content_type']));
		Log::debug('riak', '');

		switch ((string)$aInfo['content_type']) {

		 	case 'application/json':
				if ($sReturn === '' && $aOpt[CURLOPT_CUSTOMREQUEST] === 'DELETE') {
					// for deleteObj interface bug
					return $sReturn;
				}
				$aReturn = json_decode($sReturn, TRUE);
				if ($iJSONError = json_last_error()) {
					$this->_iJSONError = $iJSONError;
					return FALSE;
				}
				return $aReturn;
				break;

			default:
				return $sReturn;
				break;
		}
	}

	// http://docs.basho.com/riak/latest/dev/references/http/list-buckets/
	public function listBuckets() {
		$aReturn = $this->_http('/buckets?buckets=true');
		if (!is_array($aReturn) || empty($aReturn['buckets'])) {
			return [];
		}
		return $aReturn['buckets'];
	}

	// http://docs.basho.com/riak/latest/dev/references/http/list-keys/
	public function listKeys($sBucket) {
		$aReturn = $this->_http('/buckets/'.$sBucket.'/keys?keys=true');
		if (!is_array($aReturn) || empty($aReturn['keys'])) {
			return [];
		}
		return $aReturn['keys'];
	}

	// http://docs.basho.com/riak/latest/dev/references/http/get-bucket-props/
	public function getBucketProps($sBucket) {
		$aReturn = $this->_http('/buckets/'.$sBucket.'/props');
		if (!is_array($aReturn) || empty($aReturn['props'])) {
			return [];
		}
		return $aReturn['props'];
	}

	// http://docs.basho.com/riak/latest/dev/references/http/set-bucket-props/
	public function setBucketProps($sBucket, array $aProp) {

		$aOpt = [
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_HTTPHEADER => [
				'Content-type: application/json'
			],
			CURLOPT_POSTFIELDS => self::_json(['props' => $aProp]),
		];

		$aReturn = $this->_http('/buckets/'.$sBucket.'/props', $aOpt);
		return $aReturn;
	}

	// http://docs.basho.com/riak/latest/dev/references/http/reset-bucket-props/
	public function resetBucketProps($sBucket, $aProp) {

		$aOpt = [
			CURLOPT_CUSTOMREQUEST => 'DELETE',
		];

		$aReturn = $this->_http('/buckets/'.$sBucket.'/props');
		return $aReturn;
	}

	// http://docs.basho.com/riak/latest/dev/references/http/fetch-object/
	public function fetchObj($sBucket, $sKey) {
		$aReturn = $this->_http('/buckets/'.$sBucket.'/keys/'.$sKey);
		return $aReturn;
	}

	// http://docs.basho.com/riak/latest/dev/references/http/store-object/
	public function storeObj($sBucket, $sKey = NULL, $mData, array $aIndex = []) {

		$aOpt = [];

		$sURI = '/buckets/'.$sBucket.'/keys';
		if ($sKey) {
			$sURI .= '/'.$sKey;
			$aOpt[CURLOPT_CUSTOMREQUEST] = 'PUT';
		} else {
			$aOpt[CURLOPT_POST] = TRUE;
		}

		// $sURI .= '?returnbody=true';

		$aAddHeader = [];

		if (is_array($mData)) {
			$mData = self::_json($mData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			$aAddHeader[] = 'Content-type: application/json';
		} else {
			$aAddHeader[] = 'Content-type: text/plain';
		}

		if ($aIndex) {
			foreach ($aIndex as $sIndexKey => $aRow) {
				if (!$aRow) {
					continue;
				}
				if (!is_array($aRow)) {
					$aRow = [$aRow];
				}
				$bInt = is_integer(current($aRow));
				foreach ($aRow as $mValue) {
					if (($bInt && !is_integer($mValue))
						|| (!$bInt && !is_string($mValue))
					) {
						throw new TangoException('index value type error');
					}
				}
				$aAddHeader[] = 'x-riak-index-'.$sIndexKey.'_'.($bInt ? 'int' : 'bin').': '
					.implode(', ', $aRow);
			}
		}
		$aOpt[CURLOPT_HTTPHEADER] = $aAddHeader;

		$aOpt[CURLOPT_POSTFIELDS] = $mData;

		return $this->_http($sURI, $aOpt);
	}

	// http://docs.basho.com/riak/latest/dev/references/http/delete-object/
	public function deleteObj($sBucket, $sKey) {
		$aOpt = [
			CURLOPT_CUSTOMREQUEST => 'DELETE',
		];
		$mReturn = $this->_http('/buckets/'.$sBucket.'/keys/'.$sKey, $aOpt);
		return $mReturn === '';
	}

	// http://docs.basho.com/riak/latest/dev/references/http/ping/
	public function ping() {
		$sReturn = $this->_http('/ping', $aOpt);
		return $sReturn === 'OK';
	}

	// http://docs.basho.com/riak/latest/dev/references/http/status/
	public function status() {
		$aOpt = [
			CURLOPT_HTTPHEADER => [
				'Accept: application/json',
			],
		];
		$aReturn = $this->_http('/stats', $aOpt);
		return $aReturn;
	}

	// http://docs.basho.com/riak/latest/dev/references/http/list-resources/
	public function listResources() {
		$aOpt = [
			CURLOPT_HTTPHEADER => [
				'Accept: application/json',
			],
		];
		$aReturn = $this->_http('/', $aOpt);
		return $aReturn;
	}

	// http://docs.basho.com/riak/latest/dev/references/http/secondary-indexes/
	public function queryIndex($sBucket, $sIndex, $mVal, $aArg) {

		// for special '$bucket', skip it
		if (!is_string($sIndex) || substr($sIndex, 0, 1) !== '$') {
			$sIndex .= is_integer($mVal) ? '_int' : '_bin';
		}

		$sURI = '/buckets/'.$sBucket.'/index/'.$sIndex.'/'.$mVal;

		if ($aArg) {
			$sURI .= '?'.http_build_query($aArg, '', '&');
		}

		$aReturn = $this->_http($sURI);
		return $aReturn;
	}

	public function genLoop($sBucket, $sIndex, $mVal, $iLimit) {
		return new RiakLoopQuery($this, $sBucket, $sIndex, $mVal, $iLimit);
	}

	// http://docs.basho.com/riak/latest/dev/references/http/counters/
	public function counters($sBucket, $sKey, $iAdd = NULL) {
		$aOpt = [];
		if ($iAdd && is_integer($iAdd)) {
			$aOpt = [
				CURLOPT_POST => TRUE,
				CURLOPT_POSTFIELDS => $iAdd,
			];
		}
		$iReturn = $this->_http('/buckets/'.$sBucket.'/counters/'.$sKey, $aOpt);
		if ($iAdd) {
			return $iReturn === '';
		}
		return (int)$iReturn;
	}

	// http://docs.basho.com/riak/latest/dev/references/http/status/
	// http://docs.basho.com/riak/latest/dev/using/mapreduce/
	public function mapreduce($mInput, $aQuery) {
		$aOpt = [
			CURLOPT_POST => TRUE,
			CURLOPT_HTTPHEADER => [
				'Content-type: application/json',
			],
			CURLOPT_POSTFIELDS => self::_json([
				'inputs' => $mInput,
				'query' => $aQuery,
			]),
		];
		$aReturn = $this->_http('/mapred', $aOpt);
		return $aReturn;
	}

	// http://docs.basho.com/riak/latest/dev/using/2i/
	// "Count Bucket Objects via $bucket Index"
	public function countKeys($sBucket) {

		$aInput = [
			'bucket' => $sBucket,
			'index' => '$bucket',
			'key' => $sBucket,
		];

		$aQuery = [
			[
				'reduce' => [
					'language' => 'erlang',
					'module' => 'riak_kv_mapreduce',
					'function' => 'reduce_count_inputs',
					'arg' => [
						'reduce_phase_batch_size' => 1000,
					],
				],
			],
		];

		$aReturn = $this->mapreduce($aInput, $aQuery);
		if (empty($aReturn[0]) || !is_integer($aReturn[0])) {
			return FALSE;
		}
		return $aReturn[0];
	}

	public function countKeysByIndex($sBucket, $sIndex, $mValue) {

		$aInput = [
			'bucket' => $sBucket,
			'index' => '$bucket',
			'key' => $sBucket,
		];

		$aQuery = [
			[
				'reduce' => [
					'language' => 'erlang',
					'module' => 'riak_kv_mapreduce',
					'function' => 'reduce_count_inputs',
					'arg' => [
						'reduce_phase_batch_size' => 1000,
					],
				],
			],
		];

		$aReturn = $this->mapreduce($aInput, $aQuery);
		if (empty($aReturn[0]) || !is_integer($aReturn[0])) {
			return FALSE;
		}
		return $aReturn[0];
	}
}
