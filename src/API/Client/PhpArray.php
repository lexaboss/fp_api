<?php

namespace Library\Facebook\API\Client;

use Library\Facebook\API\Credentials;
use Library\Application;

/**
 * Extends the Facebook_API_Client_AbstractClient class with the intent of using
 * PHP sessions to store user ids and access tokens.
 */
class PhpArray extends AbstractClient
{
	const FBSS_COOKIE_NAME = 'fbss';

	// We can set this to a high number because the main session
	// expiration will trump this.
	const FBSS_COOKIE_EXPIRE = 31556926; // 1 year

	// Stores the shared session ID if one is set.
	protected $sharedSessionID;
	protected static $kSupportedKeys = array('state', 'code', 'access_token', 'user_id');

	/**
	 * @var Db
	 */
	//protected $db;

	/**
	 * @var array
	 */
	protected $persistentData = array();

	/**
	 * Identical to the parent constructor, except that
	 * we start a PHP session to store the user ID and
	 * access token if during the course of execution
	 * we discover them.
	 *
	 * @param Array $config the application configuration. Additionally
	 * accepts "sharedSession" as a boolean to turn on a secondary
	 * cookie for environments with a shared session (that is, your app
	 * shares the domain with other apps).
	 * @see AbstractClient::__construct
	 */
	public function __construct(Credentials $credentials)
	{
    	parent::__construct($credentials);
	}

	/**
	 * Initialize php session and cookies.
	 * @return void
	 */
	protected function initSharedSession()
	{
		$cookie_name = $this->getSharedSessionCookieName();
		if (isset($_COOKIE[$cookie_name]))
		{
			$data = $this->parseSignedRequest($_COOKIE[$cookie_name]);
			if ($data AND !empty($data['domain']) AND self::isAllowedDomain($this->getHttpHost(), $data['domain']))
			{
				// good case
				$this->sharedSessionID = $data['id'];
				return;
			}
			// ignoring potentially unreachable data
		}

		// evil/corrupt/missing case
		$base_domain = $this->getBaseDomain();
		$this->sharedSessionID = md5(uniqid(mt_rand(), true));
		$cookie_value = $this->makeSignedRequest(array
		(
			'domain' => $base_domain,
			'id' => $this->sharedSessionID,
		));

		$_COOKIE[$cookie_name] = $cookie_value;
		if (!headers_sent())
		{
			$expire = time() + self::FBSS_COOKIE_EXPIRE;
			setcookie($cookie_name, $cookie_value, $expire, '/', '.'.$base_domain);
		}
		else
		{
			self::errorLog
			(
				'Shared session ID cookie could not be set! You must ensure you '.
				'create the Facebook instance before headers have been sent. This '.
				'will cause authentication issues after the first request.'
			);
		}
	}

	/**
	 * Provides the implementations of the inherited abstract
	 * methods.  The implementation uses PHP sessions to maintain
	 * a store for authorization codes, user ids, CSRF states, and
	 * access tokens.
	 */
	public function setPersistentData($key, $value)
	{
		if (!in_array($key, self::$kSupportedKeys))
		{
			self::errorLog('Unsupported key passed to setPersistentData.');
			return;
		}

		$session_var_name = $this->constructSessionVariableName($key);
		$this->persistentData[$session_var_name] = $value;
	}

	/**
	 * @param  string  $key
	 * @param  string  $default
	 *
	 * @return mixed
	 */
	public function getPersistentData($key, $default = false)
	{
		if (!in_array($key, self::$kSupportedKeys))
		{
			self::errorLog('Unsupported key passed to getPersistentData.');
			return $default;
		}

		$session_var_name = $this->constructSessionVariableName($key);
		return isset($this->persistentData[$session_var_name]) ? $this->persistentData[$session_var_name] : $default;
	}

	/**
	 * @param  string  $key
	 * @return void
	 */
	protected function clearPersistentData($key)
	{
		if (!in_array($key, self::$kSupportedKeys))
		{
			self::errorLog('Unsupported key passed to clearPersistentData.');
			return;
		}

		$session_var_name = $this->constructSessionVariableName($key);
		unset($this->persistentData[$session_var_name]);
	}

	/**
	 * @return void
	 */
	protected function clearAllPersistentData()
	{
		foreach (self::$kSupportedKeys AS $key)
		{
			$this->clearPersistentData($key);
		}

		if ($this->sharedSessionID)
		{
			$this->deleteSharedSessionCookie();
		}
	}

	/**
	 * @return void
	 */
	protected function deleteSharedSessionCookie()
	{
		$cookie_name = $this->getSharedSessionCookieName();
		unset($_COOKIE[$cookie_name]);

		$base_domain = $this->getBaseDomain();
		setcookie($cookie_name, '', 1, '/', '.'.$base_domain);
	}

	/**
	 * @return string
	 */
	protected function getSharedSessionCookieName()
	{
		return self::FBSS_COOKIE_NAME . '_' . $this->getAppId();
	}

	/**
	 * @param  string  $key
	 * @return string
	 */
	protected function constructSessionVariableName($key)
	{
		$parts = array('fb', $this->getAppId(), $key);
		if ($this->sharedSessionID)
		{
			array_unshift($parts, $this->sharedSessionID);
		}

    	return implode('_', $parts);
	}
}
