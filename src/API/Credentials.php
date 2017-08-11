<?php

namespace Library\Facebook\API;

class Credentials
{
  	/**
   	* The Application ID.
   	*/
  	protected $appId;

  	/**
   	* The Application API Secret.
   	*/
  	protected $apiSecret;

  	/**
   	* Indicates if Cookie support should be enabled.
   	*/
  	protected $cookieSupport = false;

  	/**
   	* Base domain for the Cookie.
   	*/
  	protected $baseDomain = '';

  	/**
   	* Indicates if the CURL based @ syntax for file uploads is enabled.
   	*/
  	protected $fileUploadSupport = false;

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
  	public function __construct($config)
  	{
  		$this->setAppId($config['appId']);
    	$this->setApiSecret($config['secret']);

    	if (isset($config['cookie']))
    	{
      		$this->setCookieSupport($config['cookie']);
    	}
    	if (isset($config['domain']))
    	{
      		$this->setBaseDomain($config['domain']);
    	}
    	if (isset($config['fileUpload']))
    	{
      		$this->setFileUploadSupport($config['fileUpload']);
    	}
  	}

  	/**
   	* Set the Application ID.
   	*
   	* @param String $appId the Application ID
   	*/
  	public function setAppId($appId)
  	{
    	$this->appId = $appId;
    	return $this;
  	}

  	/**
   	* Get the Application ID.
   	*
   	* @return String the Application ID
   	*/
  	public function getAppId()
  	{
    	return $this->appId;
  	}

  	/**
   	* Set the API Secret.
   	*
   	* @param String $appId the API Secret
   	*/
  	public function setApiSecret($apiSecret)
  	{
    	$this->apiSecret = $apiSecret;
   		return $this;
  	}

  	/**
   	* Get the API Secret.
   	*
   	* @return String the API Secret
   	*/
  	public function getApiSecret()
  	{
    	return $this->apiSecret;
  	}

  	/**
   	* Set the Cookie Support status.
   	*
   	* @param Boolean $cookieSupport the Cookie Support status
   	*/
  	public function setCookieSupport($cookieSupport)
  	{
    	$this->cookieSupport = ($cookieSupport) ? true : false;
    	return $this;
  	}

  	/**
   	* Get the Cookie Support status.
   	*
   	* @return Boolean the Cookie Support status
   	*/
  	public function useCookieSupport()
  	{
    	return $this->cookieSupport;
  	}

  	/**
   	* Set the base domain for the Cookie.
   	*
   	* @param String $domain the base domain
   	*/
  	public function setBaseDomain($domain)
  	{
    	$this->baseDomain = $domain;
    	return $this;
  	}

  	/**
   	* Get the base domain for the Cookie.
   	*
   	* @return String the base domain
   	*/
  	public function getBaseDomain()
  	{
    	return $this->baseDomain;
  	}

  	/**
   	* Set the file upload support status.
   	*
   	* @param String $domain the base domain
   	*/
  	public function setFileUploadSupport($fileUploadSupport)
  	{
    	$this->fileUploadSupport = $fileUploadSupport;
    	return $this;
  	}

  	/**
   	* Get the file upload support status.
   	*
   	* @return String the base domain
   	*/
  	public function useFileUploadSupport()
  	{
    	return $this->fileUploadSupport;
  	}
}
