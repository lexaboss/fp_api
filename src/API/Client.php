<?php

namespace Library\Facebook\API;

use Library\Facebook\Exception;

/**
* Provides access to the Facebook Platform.
*/
class Client
{
	self::CLIENT_NAME = 'put_fb_client_here';

  	/**
   	* Default options for curl.
   	*/
  	public static $CURL_OPTS = array
  	(
    	CURLOPT_CONNECTTIMEOUT => 10,
    	CURLOPT_RETURNTRANSFER => true,
    	CURLOPT_TIMEOUT        => 60,
    	CURLOPT_USERAGENT      => 'facebook-php-2.0',
    	CURLOPT_SSL_VERIFYPEER => false,
  	);

  	/**
   	* Maps aliases to Facebook domains.
   	*/
  	protected static $DOMAIN_MAP = array
  	(
    	'video'  => 'https://graph-video.facebook.com/',
    	'graph'  => 'https://graph.facebook.com/',
  		'www'	 => 'https://www.facebook.com/',
  	);

  	/**
  	* Current credentials.
  	*/
  	private $_credentials;

  	/**
   	* The data from the signed_request token.
   	*/
  	protected $signedRequest;

  	/**
   	* Initialize a Facebook Application.
   	*
	* The configuration:
	* - appId: the application ID
	* - secret: the application secret
	* - cookie: (optional) boolean true to enable cookie support
	* - domain: (optional) domain for the cookie
	* - fileUpload: (optional) boolean indicating if file uploads are enabled
	*
	* @param Array $config the application configuration
	*/
  	public function __construct(Credentials $credentials)
  	{
    	$this->_credentials = $credentials;
  	}

  	/**
  	* Set temporary (once-request alive) access token.
  	* Will be reset after getAccessToken called.
  	*
  	* @param  string  $token
  	* @return Facebook_API_Client
  	*/
  	public function setAccessToken($token)
  	{
  		$this->_accessToken = $token;
  		return $this;
  	}

  	/**
  	* Get access token provided from API.
  	* @return string  $token
  	*/
  	public function getAccessToken()
  	{
  		return $this->_accessToken;
  	}

  	/**
   	* Get the data from a signed_request token
   	*
   	* @return String the base domain
   	*/
  	public function getSignedRequest()
  	{
    	if (!$this->signedRequest)
    	{
      		if (isset($_REQUEST['signed_request']))
      		{
        		$this->signedRequest = $this->_parseSignedRequest($_REQUEST['signed_request']);
      		}
    	}
    	return $this->signedRequest;
  	}

  	/**
   	* Parses a signed_request and validates the signature.
   	* Then saves it in $this->signed_data
   	*
   	* @param String A signed token
   	* @param Boolean Should we remove the parts of the payload that
   	*                are used by the algorithm?
   	* @return Array the payload inside it or null if the sig is wrong
   	*/
  	protected function _parseSignedRequest($signed_request)
  	{
    	list($encoded_sig, $payload) = explode('.', $signed_request, 2);

    	// decode the data
    	$sig = self::base64UrlDecode($encoded_sig);
    	$data = json_decode(self::base64UrlDecode($payload), true);

    	if (strtoupper($data['algorithm']) !== 'HMAC-SHA256')
    	{
      		self::errorLog('Unknown algorithm. Expected HMAC-SHA256');
      		return null;
    	}

    	// check sig
    	$expected_sig = hash_hmac('sha256', $payload, $this->_credentials->getApiSecret(), $raw = true);
    	if ($sig !== $expected_sig)
    	{
      		self::errorLog('Bad Signed JSON signature!');
      		return null;
    	}

    	return $data;
  	}

  	/**
   	* Validates a session_version=3 style session object.
   	*
   	* @param Array $session the session object
   	* @return Array the session object if it validates, null otherwise
   	*/
  	public function validateSessionObject($session)
  	{
    	// make sure some essential fields exist
    	if (is_array($session) AND isset($session['uid']) AND isset($session['access_token']) AND isset($session['sig']))
    	{
      		// validate the signature
      		$session_without_sig = $session;
     		unset($session_without_sig['sig']);

     		$expected_sig = self::generateSignature($session_without_sig, $this->_credentials->getApiSecret());
      		if ($session['sig'] != $expected_sig)
      		{
        		self::errorLog('Got invalid session signature in cookie.');
        		$session = null;
      		}
      		// check expiry time
    	}
    	else
    	{
      		$session = null;
    	}
    	return $session;
  	}

  	/**
   	* Set a JS Cookie based on the _passed in_ session. It does not use the
   	* currently stored session -- you need to explicitly pass it in.
   	*
   	* @param Array $session the session to use for setting the cookie
   	*/
  	public function setCookieFromSession($session = null)
  	{
    	if (!$this->_credentials->useCookieSupport())
    	{
      		return;
    	}

    	$cookieName = $this->getSessionCookieName();
    	$value = 'deleted';
    	$expires = time() - 3600;
    	$domain = $this->_credentials->getBaseDomain();
    	if ($session)
    	{
      		$value = '"' . http_build_query($session, null, '&') . '"';
      		$expires = $session['expires'];
    	}

    	// if an existing cookie is not set, we dont need to delete it
    	if ($value == 'deleted' AND empty($_COOKIE[$cookieName]))
    	{
      		return;
    	}

    	if (headers_sent())
    	{
      		self::errorLog('Could not set cookie. Headers already sent.');
    	}
    	else
    	{
      		setcookie($cookieName, $value, $expires, '/', $domain, false, true);
    	}
	}

  	/**
   	* The name of the Cookie that contains the session.
   	*
   	* @return String the cookie name
   	*/
  	public function getSessionCookieName()
  	{
    	return 'fbs_' . CLIENT_NAME . ' . $this->_credentials->getAppId();
  	}

  	/**
  	* Make an API call.
  	*
   	* @param Array $params the API call parameters
   	* @return the decoded response
   	*/
  	public function api(/* polymorphic */)
  	{
    	$args = func_get_args();
    	if (is_array($args[0]))
    	{
      		return $this->_restserver($args[0]);
    	}
    	else
    	{
      		return call_user_func_array(array($this, '_graph'), $args);
    	}
  	}

 	/**
   	* Invoke the Graph API.
   	*
   	* @param String $path the path (required)
   	* @param String $method the http method (default 'GET')
   	* @param Array $params the query/post data
   	* @return the decoded response object
   	* @throws FacebookApiException
   	*/
  	protected function _graph($path, $method = 'GET', $params = array())
  	{
    	if (is_array($method) && empty($params))
    	{
      		$params = $method;
      		$method = 'GET';
    	}
    	$params['method'] = $method; // method override as we always do a POST

    	$result = json_decode($this->_oauthRequest
    	(
     		$this->getUrl('graph', $path),
      		$params
    	), true);

    	// results are returned, errors are thrown
    	if (is_array($result) AND isset($result['error']))
    	{
      		$e = new Exception\RuntimeException($result);
     		switch ($e->getType())
     		{
        		// OAuth 2.0 Draft 00 style
        		case 'OAuthException':
        		// OAuth 2.0 Draft 10 style
        		case 'invalid_token':
          		$this->setSession(null);
      		}

      		throw $e;
    	}
    	return $result;
  	}

  	/**
   	* Make a OAuth Request
   	*
   	* @param String $path the path (required)
   	* @param Array $params the query/post data
   	*
   	* @return the decoded response object
   	* @throws FacebookApiException
   	*/
  	protected function _oauthRequest($url, $params)
  	{
    	if (!isset($params['access_token']))
    	{
      		$params['access_token'] = $this->getAccessToken();
    	}

    	// json_encode all params values that are not strings
   		foreach ($params AS $key => $value)
   		{
      		if (!is_string($value))
      		{
        		$params[$key] = json_encode($value);
      		}
    	}
    	return $this->__makeRequest($url, $params);
  	}

  	/**
   	* Makes an HTTP request. This method can be overriden by subclasses if
  	* developers want to do fancier things or use something other than curl to
   	* make the request.
  	*
   	* @param String $url the URL to make the request to
   	* @param Array $params the parameters to use for the POST body
   	* @param CurlHandler $ch optional initialized curl handle
   	* @return String the response text
   	*/
  	private function __makeRequest($url, $params, $ch = null)
  	{
    	if (!$ch)
    	{
      		$ch = curl_init();
    	}

    	$opts = self::$CURL_OPTS;

      	$opts[CURLOPT_POSTFIELDS] = ($this->_credentials->useFileUploadSupport()) ? $params : http_build_query($params, null, '&');
    	$opts[CURLOPT_URL] = $url;

    	// disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
    	// for 2 seconds if the server does not support this header.
    	if (isset($opts[CURLOPT_HTTPHEADER]))
    	{
      		$existing_headers = $opts[CURLOPT_HTTPHEADER];
      		$existing_headers[] = 'Expect:';
      		$opts[CURLOPT_HTTPHEADER] = $existing_headers;
    	}
    	else
    	{
      		$opts[CURLOPT_HTTPHEADER] = array('Expect:');
    	}

    	curl_setopt_array($ch, $opts);
    	$result = curl_exec($ch);

    	// reset request auth token
    	$this->_accessToken = null;

    	if (curl_errno($ch) == 60) // CURLE_SSL_CACERT
    	{
      		self::errorLog('Invalid or no certificate authority found, using bundled information');

			curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/fb_ca_chain_bundle.crt');
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

      		$result = curl_exec($ch);
    	}

    	if ($result === false)
    	{
      		$e = new Exception\cUrlException(array
      		(
        		'error_code' => curl_errno($ch),
        		'error'      => array
      			(
          			'message' => curl_error($ch),
          			'type'    => 'CurlException',
        		),
      		));

      		curl_close($ch);
      		throw $e;
    	}

    	curl_close($ch);
    	return $result;
  	}

  	/**
   	* Build the URL for given domain alias, path and parameters.
   	*
   	* @param $name String the name of the domain
   	* @param $path String optional path (without a leading slash)
   	* @param $params Array optional query parameters
   	* @return String the URL for the given parameters
   	*/
  	public function getUrl($name, $path = '', $params = array())
  	{
    	$url = self::$DOMAIN_MAP[$name];
    	if ($path)
    	{
      		if ($path[0] === '/')
      		{
        		$path = substr($path, 1);
      		}
      		$url .= $path;
    	}
    	if ($params)
    	{
      		$url .= '?' . http_build_query($params, null, '&');
    	}
    	return $url;
  	}

  	/**
   	* Generate a signature for the given params and secret.
   	*
   	* @param Array $params the parameters to sign
   	* @param String $secret the secret to sign with
   	* @return String the generated signature
   	*/
  	public static function generateSignature($params, $secret)
  	{
    	// work with sorted data
    	ksort($params);

    	// generate the base string
    	$base_string = '';
    	foreach($params AS $key => $value)
    	{
      		$base_string .= $key . '=' . $value;
    	}
   		$base_string .= $secret;

    	return md5($base_string);
  	}

  	/**
   	* Base64 encoding that doesn't need to be urlencode()ed.
   	* Exactly the same as base64_encode except it uses
   	*   - instead of +
   	*   _ instead of /
   	*
   	* @param String base64UrlEncodeded string
   	*/
  	public static function base64UrlDecode($input)
  	{
    	return base64_decode(strtr($input, '-_', '+/'));
  	}

  	/**
   	* Prints to the error log if you aren't in command line mode.
   	*
   	* @param String log message
   	*/
  	protected static function errorLog($msg)
  	{
    	// disable error log if we are running in a CLI environment
    	// @codeCoverageIgnoreStart
    	if (php_sapi_name() != 'cli')
    	{
      		error_log($msg);
    	}

    	// uncomment this if you want to see the errors on the page
    	// print 'error_log: '.$msg."\n";
    	// @codeCoverageIgnoreEnd
  	}
}
