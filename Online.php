<?php
namespace Coercive\Utility\Online;

use Exception;

/**
 * Online
 *
 * @package 	Coercive\Utility\Online
 * @link		https://github.com/Coercive/Online
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2025 Anthony Moral
 * @license 	MIT
 */
class Online
{
	# PROPERTIES
	const TIME_OUT = 15;
	const CURL_BUSY_WAIT = 5;
	const CURL_USERAGENT = 'Coercive Online Php cUrl';
	const ERROR_TYPE_CURL_MULTI_ADD_HANDLE = 'ERROR_TYPE_CURL_MULTI_ADD_HANDLE';
	const ERROR_TYPE_CURL_MULTI_EXEC = 'ERROR_TYPE_CURL_MULTI_EXEC';
	const ERROR_TYPE_NO_CURL_RESSOURCE_PROVIDED = 'ERROR_TYPE_NO_CURL_RESSOURCE_PROVIDED';
	const ERROR_TYPE_NO_URL_IN_CURL_RESULT = 'ERROR_TYPE_NO_URL_IN_CURL_RESULT';

	/** @var string : Declared UserAgent for cUrl */
	static private string $useragent = self::CURL_USERAGENT;

	/** @var int : Max time out befor close cUrl */
	static private int $timeout = self::TIME_OUT;

	/** @var array : Try List of url */
	static private array $tryList = [];

	/** @var array : List of each instances of cUrl init */
	static private array $multiCUrlStorage = [];

	/** @var array : MULTITON Processed List */
	static private array $processedList = [];

	/** @var array : List of errors occured */
	static private array $errors = [];

	/**
	 * EXCEPTION
	 *
	 * @param string $msg [optional]
	 * @param string $method [optional]
	 * @param int $line [optional]
	 * @throws Exception
	 */
	static private function exception($msg = 'Unknow', $method = __METHOD__, $line = __LINE__)
	{
		throw new Exception("Online ERROR, message : $msg, in method : $method, at line : $line.");
	}

	/**
	 * SET ERROR
	 *
	 * @param string $url
	 * @param string $type
	 * @param mixed $msg
	 * @return void
	 */
	static private function _setError(string $url, string $type, string $msg)
	{
		self::$errors[$url][$type] = $msg;
	}

	/**
	 * URL FORMAT
	 *
	 * @param string $sUrl
	 * @return string
	 * @throws Exception
	 */
	static private function cleanUrl(string $sUrl): string
	{
		# TRIM ERROR
		$sUrl = trim($sUrl, " \t\n\r\0\x0B/");
		if(!$sUrl) {
			self::exception('The provided URL param has not a valid content.');
		}

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
	static private function _cUrlMultiExec($rMultiCUrl, int &$iStillRunning): int
	{
		do {
			$status = curl_multi_exec($rMultiCUrl, $iStillRunning);
			if($status > 0) {
				$message = $status . ' - ' . curl_multi_strerror($status);
				self::_setError($iStillRunning, self::ERROR_TYPE_CURL_MULTI_EXEC, $message);
			}
		}
		while($status === CURLM_CALL_MULTI_PERFORM || $iStillRunning > 0 );

		return $status;
	}

	/**
	 * CUSTOM CURL ADD HANDLE
	 *
	 * @param resource $rMultiCUrl
	 * @param string $url
	 * @return int
	 */
	static private function _cUrlAddHandle($rMultiCUrl, string $url): int
	{
		/** @var resource Init CUrl Session */
		$r = curl_init();

		# OPTIONS
		curl_setopt($r, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($r, CURLOPT_POST, false);
		curl_setopt($r, CURLOPT_USERAGENT, self::$useragent);
		curl_setopt($r, CURLOPT_HEADER, true);
		curl_setopt($r, CURLOPT_URL, $url);
		curl_setopt($r, CURLOPT_TIMEOUT, self::$timeout);
		curl_setopt($r, CURLOPT_CONNECTTIMEOUT, self::$timeout);
		curl_setopt($r, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($r, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($r, CURLOPT_ENCODING, '');
		curl_setopt($r, CURLOPT_FRESH_CONNECT, true);

		# Add to thread
		$error = curl_multi_add_handle($rMultiCUrl, $r);

		# Set Ressource
		self::$multiCUrlStorage[$url] = $r;

		# Return error (0 if not)
		return $error;
	}

	/**
	 * SET CUSTOM TIMEOUT
	 *
	 * @param int $seconds
	 */
	static public function setTimeout(int $seconds)
	{
		self::$timeout = $seconds;
	}

	/**
	 * SET CUSTOM USER AGENT
	 *
	 * @param string $name
	 */
	static public function setUserAgent(string $name)
	{
		self::$useragent = $name;
	}

	/**
	 * GET STATE
	 *
	 * @param string $url
	 * @return Online
	 * @throws Exception
	 */
	static public function get(string $url): ? Online
	{
		$url = self::cleanUrl($url);
		return self::$processedList[$url] ?? null;
	}

	/**
	 * CREATE ITEM TO CURL
	 *
	 * @param string $url
	 * @return string
	 * @throws Exception
	 */
	static public function create(string $url): string
	{
		$url = self::cleanUrl($url);
		return self::$tryList[$url] = $url;
	}

	/**
	 * GET ERRORS
	 *
	 * @return array
	 */
	static public function getErrors(): array
	{
		return self::$errors;
	}

	/**
	 * URL ANALYSIS
	 *
	 * @return void
	 * @throws Exception
	 */
	static public function run()
	{
		# Skip if no url provide
		if(!self::$tryList) {
			return;
		}

		# Init multi cUrl
		$r = curl_multi_init();

		# Process nodes
		foreach (self::$tryList as $url) {
			$error = self::_cUrlAddHandle($r, $url);
			if($error) {
				self::_setError($url, self::ERROR_TYPE_CURL_MULTI_ADD_HANDLE, strval($error));
			}
		}

		# Process all
		$iStillRunning = 0;
		$iMultiCUrlState = self::_cUrlMultiExec($r, $iStillRunning);

		# Wait for completion
		do {

			# Non-busy (!) wait for state change : https://bugs.php.net/bug.php?id=61141:
			if (curl_multi_select($r) === -1) {
				sleep(self::CURL_BUSY_WAIT);
			}

			# Get new state
			self::_cUrlMultiExec($r, $iStillRunning);

			# Get datas
			while ($infos = curl_multi_info_read($r)) {

				/** @var resource $rCUrlSession */
				$rCUrlSession = $infos['handle'] ?? null;
				if(!$rCUrlSession) {
					self::_setError(
						uniqid('unknown_', true),
						self::ERROR_TYPE_NO_CURL_RESSOURCE_PROVIDED,
						'no curl handle provided'
					);
					continue;
				}

				# Load in Online instance
				$online = new self($rCUrlSession);
				$url = $online->url();
				if(!$url) {
					self::_setError(
						uniqid('unknown_', true),
						self::ERROR_TYPE_NO_URL_IN_CURL_RESULT,
						'no url in curl result'
					);
					continue;
				}

				# Add to processed list
				self::$processedList[$url] = $online;

				# Close handler
				curl_multi_remove_handle($r, $rCUrlSession);

			}
		} while ($iMultiCUrlState === CURLM_OK && $iStillRunning);

		# Close multi handler
		curl_multi_close($r);
	}

########################################################################################################################
# INSTANCE OF CURL RESULT HANDLER
########################################################################################################################

	/** @var string : Provided Url */
	private string $_sProvidedUrl = '';

	/** @var string : cURL Exec Result */
	private string $_sCurlContent = '';

	/** @var string : cURL Exec Message */
	private string $_sCurlMessage = '';

	/** @var bool : cURL Exec Status */
	private bool $_bCurlStatus = false;

	/** @var int : cURL Exec Result */
	private int $_iCurlResult = 0;

	/** @var int : cURL Error Number */
	private int $_iCurlErrNo = 0;

	/** @var string : cURL Error Message */
	private string $_sCurlError = '';

	/** @var array : cURL Info */
	private array $_aCurlInfo = [];

	/**
	 * Online constructor.
	 *
	 * @param resource $rCUrlSession
	 * @return void
	 */
	private function __construct($rCUrlSession)
	{
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
	 * @throws Exception
	 */
	public function url(): string
	{
		return isset($this->_aCurlInfo['url']) ? self::cleanUrl($this->_aCurlInfo['url']) : '';
	}

	/**
	 * IS OK
	 *
	 * @return bool
	 */
	public function isOk(): bool
	{
		if(empty($this->_aCurlInfo['http_code'])) {
			return false;
		}
		return $this->_aCurlInfo['http_code'] >= 200 && $this->_aCurlInfo['http_code'] < 300;
	}

	/**
	 * HTTP_CODE
	 *
	 * @return int
	 */
	public function http_code(): int
	{
		return intval($this->_aCurlInfo['http_code'] ?? 0);
	}

	/**
	 * REDIRECT STATUS
	 *
	 * @return bool
	 */
	public function isRedirect(): bool
	{
		if(empty($this->_aCurlInfo['http_code'])) {
			return false;
		}
		return !empty($this->_aCurlInfo['redirect_count']) || $this->_aCurlInfo['http_code'] === 301;
	}

	/**
	 * REDIRECT URL
	 *
	 * @return string
	 */
	public function redirectUrl(): string
	{
		return $this->_aCurlInfo['redirect_url'] ?? '';
	}

	/**
	 * IP
	 *
	 * @return string
	 */
	public function ip(): string
	{
		return $this->_aCurlInfo['primary_ip'] ?? '';
	}

	/**
	 * TIME
	 *
	 * @return int
	 */
	public function time(): int
	{
		return isset($this->_aCurlInfo['total_time']) ? floatval($this->_aCurlInfo['total_time']) : 0;
	}

	/**
	 * MESSAGE (curl msg field)
	 *
	 * @return string
	 */
	public function getMessage(): string
	{
		return $this->_sCurlMessage;
	}

	/**
	 * ERR NUMBER
	 *
	 * @return int
	 */
	public function getErrNo(): int
	{
		return $this->_iCurlErrNo;
	}

	/**
	 * ERROR MESSAGE
	 *
	 * @return string
	 */
	public function getErrorMessage(): string
	{
		return $this->_sCurlError;
	}

	/**
	 * GET CONTENT
	 *
	 * @return string
	 */
	public function getContent(): string
	{
		return $this->_sCurlContent;
	}
}