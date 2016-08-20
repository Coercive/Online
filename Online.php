<?php
namespace Coercive\Utility\Online;

use Exception;

/**
 * Online
 * PHP Version 	5
 *
 * @version		1
 * @package 	Coercive\Utility\Online
 * @link		@link https://github.com/Coercive/Online
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2016 - 2017 Anthony Moral
 * @license 	http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
class Online {

	# PROPERTIES
	const TIME_OUT = 5;

	/** @var array : MULTITON Try List */
	static private $_aTryList = [];

	/**
	 * EXCEPTION
	 *
	 * @param string $sMessage [optional]
	 * @param string $sMethod [optional]
	 * @param int $iLine [optional]
	 * @throws Exception
	 */
	static private function _Exception($sMessage = 'Unknow', $sMethod = __METHOD__, $iLine = __LINE__) {
		throw new Exception("IsOnline ERROR, message : $sMessage, in method : $sMethod, at line : $iLine.");
	}

	/**
	 * URL FORMAT
	 *
	 * @param string $sUrl
	 * @return string
	 */
	static private function _formatUrl($sUrl) {

		# SKIP ERROR
		if(!is_string($sUrl)) { self::_Exception('The provided URL param is not in a string format.'); }

		# TRIM ERROR
		$sUrl = trim($sUrl, '/ ');
		if(!$sUrl) { self::_Exception('The provided URL param has not a valid content.'); }

		# VALID STRING
		return $sUrl;
	}

	/**
	 * GET STATE
	 *
	 * @param string $sUrl
	 * @return Online
	 */
	static public function get($sUrl) {

		# PREPARE URL
		$sUrl = self::_formatUrl($sUrl);

		# GET
		return isset(self::$_aTryList[$sUrl]) ? self::$_aTryList[$sUrl] : null;
	}

	/**
	 * SET STATE
	 *
	 * @param string $sUrl
	 * @return Online
	 */
	static public function create($sUrl) {

		# PREPARE URL
		$sUrl = self::_formatUrl($sUrl);

		# MULTITON
		return self::$_aTryList[$sUrl] = new self($sUrl);
	}

	/** @var string : Provided Url */
	private $_sProvidedUrl = '';

	/** @var bool : cURL Exec Status */
	private $_bCurlStatus = false;

	/** @var array : cURL Info */
	private $_aCurlInfo = [];

	/**
	 * IsOnline constructor.
	 *
	 * @param string $sUrl
	 */
	private function __construct($sUrl) {

		$this->_sProvidedUrl = $sUrl;
		$this->_analysis();

	}

	/**
	 * URL ANALYSIS
	 *
	 * @return void
	 */
	private function _analysis() {

		/** @var resource Custom URL Session */
		$rCUrlSession = curl_init();

		# OPTIONS
		curl_setopt($rCUrlSession, CURLOPT_URL, $this->_sProvidedUrl);
		curl_setopt($rCUrlSession, CURLOPT_TIMEOUT, self::TIME_OUT);
		curl_setopt($rCUrlSession, CURLOPT_CONNECTTIMEOUT, self::TIME_OUT);
		curl_setopt($rCUrlSession, CURLOPT_RETURNTRANSFER, true);

		# STATUS
		$this->_bCurlStatus = (bool) curl_exec($rCUrlSession);

		# SET RESULT INFO
		$this->_aCurlInfo = curl_getinfo($rCUrlSession);

		curl_close($rCUrlSession);

	}

	/**
	 * URL
	 *
	 * @return string
	 */
	public function url() {
		return isset($this->_aCurlInfo['url']) ? strtolower($this->_aCurlInfo['url']) : '';
	}

	/**
	 * REDIRECT STATUS
	 *
	 * @return bool
	 */
	public function isRedirect() {
		if(empty($this->_aCurlInfo['http_code'])) { return false; }
		return !empty($this->_aCurlInfo['redirect_count']) || $this->_aCurlInfo['http_code'] === 301;
	}

	/**
	 * REDIRECT URL
	 *
	 * @return string
	 */
	public function redirectUrl() {
		return isset($this->_aCurlInfo['redirect_url']) ? $this->_aCurlInfo['redirect_url'] : '';
	}

	/**
	 * IP
	 *
	 * @return string
	 */
	public function ip() {
		return isset($this->_aCurlInfo['primary_ip']) ? $this->_aCurlInfo['primary_ip'] : '';
	}

	/**
	 * TIME
	 *
	 * @return int
	 */
	public function time() {
		return isset($this->_aCurlInfo['total_time']) ? floatval($this->_aCurlInfo['total_time']) : 0;
	}
}