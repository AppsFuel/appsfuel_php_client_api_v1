<?php


class OAuthRequiredException extends Exception { }
class OAuthErrorException extends Exception { }

class AFApi {
	function __construct($clientId, $secret, $state, $oauthRedirectUri, $isSandBox=False) {
		$this->clientId = $clientId;
		$this->secret = $secret;
		$this->state = $state;
		$this->isSandbox = $isSandBox;
		$this->redirectUri = $oauthRedirectUri;
		$this->baseUrl = $isSandBox ? 'https://api.appsfuel.com/v1/sandbox' : 'https://api.appsfuel.com/v1/live';
	}
	
	function getRequestTokenUrl() {
		if ($this->isSandbox)
			$baseUrl = 'https://api.appsfuel.com/v1/sandbox/choose/';
		else
			$baseUrl = 'http://app.appsfuel.com/content/permission';
		return $baseUrl . '?client_id=' . $this->clientId .
			'&redirect_uri=' . urlencode($this->redirectUri) .
			'&state='.$this->state .
			'&response_type=code';
	}
	
	function oauthAuthentication() {
		if (!session_id()) {
			session_start();
		}

		if (($_SESSION['appsfuel_access_token_expiring'] < time()) && 
			array_key_exists('appsfuel_access_token', $_SESSION) && 
			$_SESSION['appsfuel_access_token'])
		{
			return;
		}
		unset($_SESSION['appsfuel_access_token_expiring']);
		unset($_SESSION['appsfuel_access_token']);
		if (array_key_exists('error', $_GET)) {
			throw new OAuthErrorException($_GET['error']);
		}
		if (!array_key_exists('code', $_GET)) {
			header('Location: ' . $this->getRequestTokenUrl(), true, 302);
			exit();
		}
		$resp = $this->requestAccessToken($_GET['code']);
		$_SESSION['appsfuel_access_token'] = $resp['access_token'];
		$_SESSION['appsfuel_access_token_expiring'] = time() + $resp['expire_in'];
	}
	
	function getAccessToken() {
		$this->oauthAuthentication();
		return $_SESSION['appsfuel_access_token'];
	}
	
	function requestAccessToken($code) {
		$url = $this->baseUrl . '/oauth/token';
		$data = array("code" => $code,
			"grant_type" => "authorization_code",
			"state" => $this->state,
			"redirect_uri" => $this->redirectUri,
			"client_id" => $this->clientId,
			"client_secret" => $this->secret);
		$resp = $this->post($url, $data);
		return $resp;
	}
	
	function getUserInfo($accessToken) {
		$url = $this->baseUrl . '/user';
		$url .= '?access_token=' . $accessToken;
		$resp = $this->get($url);
		return $resp;
	}
	
	function getAppInfo($accessToken, $redirectUri) {
		$url = $this->baseUrl . '/app';
		$url .= '?access_token=' . $accessToken .
			'&back_url=' . $redirectUri;
		$resp = $this->get($url);
		return $resp;
	}

	function getActiveProducts($iso_language) {
		$url = $this->baseUrl . '/iap/getActiveProducts';
		$url .= '?access_token=' . $accessToken . '&iso_language=' . $iso_language;
		$resp = $this->get($url);
		return $resp;
	}

	function restorePurchases() {
		$url = $this->baseUrl . '/iap/restorePurchases';
		$url .= '?access_token=' . $accessToken;
		$resp = $this->get($url);
		return $resp;
	}
	
	protected function get($url) {
		return $this->url_request($url);
	}

	protected function post($url, $data) {
		$fields = '';
		foreach($data as $key => $value)
			$fields .= $key . '=' . $value . '&'; 
		rtrim($fields, '&');

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

		return $this->url_request($url,$ch);
	}

	protected function url_request($url, $ch=false) {
		if (!$ch) $ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($ch);

		$http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);
		if (($http_status / 100) != 2) {
			// throw
			throw new OAuthRequiredException($response);
		}

		return json_decode($response, true);
	}
}

