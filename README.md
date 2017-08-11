# Facebook API
Facebook API Client v3.2.2

usage:

```php
<?php
	// set facebook API
	$this->api = new FacebookAPI();
	
	// set needed permissions for facebook logins
	$this->api->setRequestParams(array('scope' => array 
	(
		'email', 'user_birthday', 'user_about_me',
		'user_hometown', 'user_location', 'user_website'
	)));
	
	// already logged in app
	if (true !== ($result = $this->api->fbConnect()))
	{
		// try to redirect user to facebook for login
		$this->response->getHeaders()->addHeaderLine('Location', $result);
	    $this->response->setStatusCode(302);
		
		return new Result(Result::FAILURE_IDENTITY_NOT_FOUND, $result);
	}
	
	// retrieve long-lived access token for server
	if (!$longTokenData = $this->api->getClient()->setExtendedAccessToken())
	{
		return new Result(Result::FAILURE_UNCATEGORIZED, $longTokenData);
	}
	
	// get current facebook user data
	$fbUser = $this->api->getUserData();
