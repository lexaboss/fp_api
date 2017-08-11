<?php

namespace Library\Facebook;

/**
* Provides access to the Facebook Platform.
*/
class API
{
	/**
   	 * Version.
	 * @var string
   	 */
  	const VERSION = '3.2.2';

  	/**
  	 * API Client for communication.
	 * @var API\Client\PhpSession
  	 */
  	private $_client = null;

  	/**
  	 * API credentials.
  	 */
  	private $_credentials = null;

  	/**
  	 * Default param array.
	 * @var array
  	 */
  	protected $_requestParams = array();

  	/**
   	 * Initialize a Facebook Application.
   	 *
   	 * @param  boolean $autoConnect
   	 * @param  string  $appId
   	 * @param  string  $appSecret
   	 *
   	 * @return Facebook_API
	 */
  	public function __construct($appId = null, $appSecret = null)
  	{
  		$appId = (null !== $appId) ? $appId : FACEBOOK_API_APPID;
  		$appSecret = (null !== $appSecret) ? $appSecret : FACEBOOK_API_APPSECRET;

  		// build credentials
  		$credentials = new API\Credentials(array
  		(
  			'appId'  => $appId,
  			'secret' => $appSecret,
  		));

  		$credentials->setCookieSupport(true);
  		$credentials->setBaseDomain($_SERVER['HTTP_HOST']);
  		$credentials->setFileUploadSupport(true);

  		// build api client
    	$this->_client = new API\Client\PhpSession($credentials);
    	$this->_credentials = $credentials;
  	}

  	/**
   	 * Get the UID from the session.
   	 * @return String the UID if available
   	 */
  	public function getUserId()
  	{
    	return $this->_client->getUser();
  	}

  	/**
  	 * Set request params array.
  	 *
  	 * @param  array   $params
  	 * @return void
  	 */
  	public function setRequestParams($params = array())
  	{
  		$this->_requestParams = array();
  		if (!is_array($params))
  		{
  			return false;
  		}

  		$this->_requestParams = $params;
  		return true;
  	}

  	/**
  	 * Connect to facebook aplication for this user.
  	 */
  	public function fbConnect($force = false)
  	{
  		if (!$this->_client->getUser() OR $force)
		{
			return $this->_client->getLoginUrl($this->_requestParams);
		}
		return true;
  	}

  	/**
  	 * Disconnect current user from facebook.
  	 * !May allso remove facebook permissions and offline token.
  	 */
  	public function fbDisconnect()
  	{
  		// no session exists to disconnect
  		if (!$this->_client->getUser())
  		{
  			return true;
  		}
  		return $this->_client->getLogoutUrl();
  	}

	/**
	 * Return current user data.
	 * @return array
	 */
  	public function getUserData()
  	{
  		if ($this->_client->getUser())
  		{
  			return $this->_client->api('/me');
  		}
  		return null;
  	}

  	/**
  	 * Get facebook user photo.
  	 * $photoType can be (square | small | normal | large)
  	 *
  	 * @param  string  $photoType
  	 * @return mixed
  	 */
  	public function getUserPhoto($photoType = 'square')
  	{
  		// check for photoType
  		if (!in_array($photoType, array('square', 'small', 'normal', 'large')))
  		{
  			$photoType = 'square';
  		}

  		if (null === $userid)
  		{
  			$picture = $this->_client->api('/me', 'GET', array('fields' => 'picture', 'type' => $photoType));
  			return ($picture) ? $picture['picture']['data']['url'] : null;
  		}
  		return null;
	}

	/**
	 * @return API\Client\PhpSession
	 */
	public function getClient()
	{
		return $this->_client;
	}
}
