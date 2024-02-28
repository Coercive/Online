<?php
namespace Coercive\Utility\Online;

use Exception;

/**
 * Online
 * PHP Version 	7
 *
 * @version		1
 * @package 	Coercive\Utility\Online
 * @link		@link https://github.com/Coercive/Online
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2017 - 2018 Anthony Moral
 * @license 	http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
class Online {

	# PROPERTIES
	const TIME_OUT = 15;
	const CURL_BUSY_WAIT = 5;
	const ERROR_TYPE_CURL_MULTI_ADD_HANDLE = 'ERROR_TYPE_CURL_MULTI_ADD_HANDLE';
	const ERROR_TYPE_CURL_MULTI_EXEC = 'ERROR_TYPE_CURL_MULTI_EXEC';
	const ERROR_TYPE_NO_CURL_RESSOURCE_PROVIDED = 'ERROR_TYPE_NO_CURL_RESSOURCE_PROVIDED';
	const ERROR_TYPE_NO_URL_IN_CURL_RESULT = 'ERROR_TYPE_NO_URL_IN_CURL_RESULT';

	/** @var int : Max time out befor close cUrl */
	static private $_iTimeoutInSecond = self::TIME_OUT;

	/** @var array : Try List of url */
	static private $_aTryList = [];

	/** @var array : List of each instances of cUrl init */
	static private $_aMultiCUrlStorage = [];

	/** @var array : MULTITON Processed List */
	static private $_aProcessedList = [];

	/** @var array : List of errors occured */
	static private $_aErrors = [];

	/**
	 * EXCEPTION
	 *
	 * @param string $sMessage [optional]
	 * @param string $sMethod [optional]
	 * @param int $iLine [optional]
	 * @throws Exception
	 */
	static private function _Exception($sMessage = 'Unknow', $sMethod = __METHOD__, $iLine = __LINE__) {
		throw new Exception("Online ERROR, message : $sMessage, in method : $sMethod, at line : $iLine.");
	}

	/**
	 * SET ERROR
	 *
	 * @param string $sUrl
	 * @param string $sType
	 * @param mixed $mState
	 * @return void
	 */
	static private function _setError($sUrl, $sType, $mState) {
		self::$_aErrors[$sUrl][$sType] = $mState;
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
		$sUrl = trim($sUrl, " \t\n\r\0\x0B/");
		if(!$sUrl) { self::_Exception('The provided URL param has not a valid content.'); }

		# VALID STRING
		return $sUrl;

	}

	/**
	 * CUSTOM CURL EXEC
	 *
	 * @link http://php.net/manual/en/function.curl-multi-select.php#108928
	 *
	 * @param resource $rMultiCUrl
	 * @param int $iStillRunning
	 * @return int
	 */
	static private function _cUrlMultiExec($rMultiCUrl, &$iStillRunning) {

		do {
			$iMultiCUrlState = curl_multi_exec($rMultiCUrl, $iStillRunning);
			if($iMultiCUrlState > 0) {
				$sMessage = $iMultiCUrlState . ' - ' . curl_multi_strerror($iMultiCUrlState);
				self::_setError($iStillRunning, self::ERROR_TYPE_CURL_MULTI_EXEC, $sMessage);
			}
		}
		while($iMultiCUrlState === CURLM_CALL_MULTI_PERFORM || $iStillRunning > 0 );

		return $iMultiCUrlState;

	}

	/**
	 * CUSTOM CURL ADD HANDLE
	 *
	 * @param resource $rMultiCUrl
	 * @param string $sUrl
	 * @return int
	 */
	static private function _cUrlAddHandle($rMultiCUrl, string $sUrl) {

		/** @var resource Init CUrl Session */
		$rCUrlSession = curl_init();

		# OPTIONS
		curl_setopt($rCUrlSession, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($rCUrlSession, CURLOPT_POST, false);
		curl_setopt($rCUrlSession, CURLOPT_USERAGENT, 'PHP CURL AGENT');
		curl_setopt($rCUrlSession, CURLOPT_HEADER, true);
		curl_setopt($rCUrlSession, CURLOPT_URL, $sUrl);
		curl_setopt($rCUrlSession, CURLOPT_TIMEOUT, self::$_iTimeoutInSecond);
		curl_setopt($rCUrlSession, CURLOPT_CONNECTTIMEOUT, self::$_iTimeoutInSecond);
		curl_setopt($rCUrlSession, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($rCUrlSession, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($rCUrlSession, CURLOPT_ENCODING, '');
		curl_setopt($rCUrlSession, CURLOPT_FRESH_CONNECT, true);

		# Add to thread
		$iErrorNo = curl_multi_add_handle($rMultiCUrl, $rCUrlSession);

		# Set Ressource
		self::$_aMultiCUrlStorage[$sUrl] = $rCUrlSession;

		# Return error (0 if not)
		return $iErrorNo;

	}

	/**
	 * SET CUSTOM TIMEOUT
	 *
	 * @param int $iSeconds
	 */
	static public function setTimeout($iSeconds) {
		self::$_iTimeoutInSecond = (int) $iSeconds;
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
		return self::$_aProcessedList[$sUrl] ?? null;

	}

	/**
	 * CREATE ITEM TO CURL
	 *
	 * @param string $sUrl
	 * @return string
	 */
	static public function create($sUrl) {

		# PREPARE URL
		$sUrl = self::_formatUrl($sUrl);

		# RETURN PREPARED URL
		return self::$_aTryList[$sUrl] = $sUrl;

	}

	/**
	 * GET ERRORS
	 *
	 * @return array
	 */
	static public function getErrors() {
		return self::$_aErrors;
	}

	/**
	 * URL ANALYSIS
	 *
	 * @return void
	 */
	static public function run() {

		# Skip if no url provide
		if(!self::$_aTryList) { return; }

		# Init multi cUrl
		$rMultiCUrl = curl_multi_init();

		# Process nodes
		foreach (self::$_aTryList as $sUrl) {

			$iHandlerErrorNo = self::_cUrlAddHandle($rMultiCUrl, $sUrl);
			if($iHandlerErrorNo) {
				self::_setError($sUrl, self::ERROR_TYPE_CURL_MULTI_ADD_HANDLE, $iHandlerErrorNo);
			}

		}

		# Process all
		$iMultiCUrlState = self::_cUrlMultiExec($rMultiCUrl, $iStillRunning);

		# Wait for completion
		do {

			# Non-busy (!) wait for state change : https://bugs.php.net/bug.php?id=61141:
			if (curl_multi_select($rMultiCUrl) === -1) { sleep(self::CURL_BUSY_WAIT); }

			# Get new state
			self::_cUrlMultiExec($rMultiCUrl, $iStillRunning);

			# Get datas
			while ($aInfo = curl_multi_info_read($rMultiCUrl)) {

				/** @var resource $rCUrlSession */
				$rCUrlSession = $aInfo['handle'] ?? null;
				if(!$rCUrlSession) {
					self::_setError(
						uniqid('unknown_', true),
						self::ERROR_TYPE_NO_CURL_RESSOURCE_PROVIDED,
						'no curl handle provided'
					);
					continue;
				}

				# Load in Online instance
				$oOnline = new self($rCUrlSession);
				$sUrl = $oOnline->url();
				if(!$sUrl) {
					self::_setError(
						uniqid('unknown_', true),
						self::ERROR_TYPE_NO_URL_IN_CURL_RESULT,
						'no url in curl result'
					);
					continue;
				}

				# Add to processed list
				self::$_aProcessedList[$sUrl] = $oOnline;

				# Close handler
				curl_multi_remove_handle($rMultiCUrl, $rCUrlSession);

			}
		} while ($iMultiCUrlState === CURLM_OK && $iStillRunning);

		# Close multi handler
		curl_multi_close($rMultiCUrl);

	}

########################################################################################################################
# INSTANCE OF CURL RESULT HANDLER
########################################################################################################################

	/** @var string : Provided Url */
	private $_sProvidedUrl = '';

	/** @var string : cURL Exec Result */
	private $_sCurlContent = '';

	/** @var string : cURL Exec Message */
	private $_sCurlMessage = false;

	/** @var bool : cURL Exec Status */
	private $_bCurlStatus = false;

	/** @var int : cURL Exec Result */
	private $_iCurlResult = 0;

	/** @var int : cURL Error Number */
	private $_iCurlErrNo = 0;

	/** @var string : cURL Error Message */
	private $_sCurlError = '';

	/** @var array : cURL Info */
	private $_aCurlInfo = [];

	/**
	 * Online constructor.
	 *
	 * @param resource $rCUrlSession
	 */
	private function __construct($rCUrlSession) {

		try {
			# ERROR
			$this->_iCurlErrNo = curl_errno($rCUrlSession);
			$this->_sCurlError = curl_error($rCUrlSession);

			# CONTENT
			$this->_sCurlContent = curl_multi_getcontent($rCUrlSession);

			# STATUS
			$this->_bCurlStatus = (bool) $this->_sCurlContent;

			# SET RESULT INFO
			$this->_aCurlInfo = curl_getinfo($rCUrlSession) ?? [];

			# MESSAGE
			$this->_sCurlMessage = $this->_aCurlInfo['msg'] ?? '';

			# RESULT
			$this->_iCurlResult = $this->_aCurlInfo['result'] ?? 0;

			# URL
			$this->_sProvidedUrl = $this->_aCurlInfo['url'] ?? '';
		}
		catch (Exception $oException) {

			self::_setError(
				uniqid('unknown_', true),
				self::ERROR_TYPE_NO_URL_IN_CURL_RESULT,
				'An error occured when prepare Online instance : ' .
				$oException->getMessage() .
				' - trace : ' . $oException->getTraceAsString()
			);

		}

	}

	/**
	 * URL
	 *
	 * @return string
	 */
	public function url() {
		return isset($this->_aCurlInfo['url']) ? self::_formatUrl($this->_aCurlInfo['url']) : '';
	}

	/**
	 * IS OK
	 *
	 * @return bool
	 */
	public function isOk() {
		if(empty($this->_aCurlInfo['http_code'])) { return false; }
		return $this->_aCurlInfo['http_code'] >= 200 && $this->_aCurlInfo['http_code'] < 300;
	}

	/**
	 * HTTP_CODE
	 *
	 * @return int
	 */
	public function http_code() {
		return empty($this->_aCurlInfo['http_code']) ? 0 : $this->_aCurlInfo['http_code'];
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
		return $this->_aCurlInfo['redirect_url'] ?? '';
	}

	/**
	 * IP
	 *
	 * @return string
	 */
	public function ip() {
		return $this->_aCurlInfo['primary_ip'] ?? '';
	}

	/**
	 * TIME
	 *
	 * @return int
	 */
	public function time() {
		return isset($this->_aCurlInfo['total_time']) ? floatval($this->_aCurlInfo['total_time']) : 0;
	}

	/**
	 * MESSAGE (curl msg field)
	 *
	 * @return string
	 */
	public function getMessage() {
		return $this->_sCurlMessage;
	}

	/**
	 * ERR NUMBER
	 *
	 * @return int
	 */
	public function getErrNo() {
		return $this->_iCurlErrNo;
	}

	/**
	 * ERROR MESSAGE
	 *
	 * @return string
	 */
	public function getErrorMessage() {
		return $this->_sCurlError;
	}

	/**
	 * GET CONTENT
	 *
	 * @return string
	 */
	public function getContent() {
		return $this->_sCurlContent;
	}

}