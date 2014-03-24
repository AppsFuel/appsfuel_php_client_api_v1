<?php

include "afapi.php";

$clientId = '<your-app-client-id>';
$clientSecret = '<your-app-client-secret>';
$state = '<your-state-during_oauth2-process>';

$oauthRedirectUri = 'http://'.$_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI']; //Your callback http url for oauth2 process
$premiumRedirectUri = 'http://'.$_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI']; //Your callback http url for paywall process
$isSandbox = true;

$api = new AFApi($clientId, $clientSecret, $state, $oauthRedirectUri, $isSandbox);

//OAuth2 process (php SESSION based). The class will do Automatic header location 
try {
	$api->oauthAuthentication(); //First step of oauth process, this automatically redirect the browser
	$accessToken = $api->getAccessToken(); //Second step addressed when the user is back to $oauthRedirectUri
} catch (Exception $e) {
	// OAuth failed
	die($e->getMessage());
}

// OAuth2 process ok
$userInfo = $api->getUserInfo($accessToken);
echo '<div><b>Appsfuel Oauth id:</b>' . $userInfo['user_id'] . '</div>';
if ($userInfo['is_paid']) {
    echo '<div>You have paid until ' . $userInfo['expire_date']['utc']  .'</div>';
} else {
   $appInfo = $api->getAppInfo($accessToken, $premiumRedirectUri);
   echo '<div>Begin premium payment! <a href="' . $appInfo['paywall_url'] . '">Be premium</a></div>';
}
