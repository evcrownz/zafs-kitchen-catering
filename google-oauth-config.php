<?php
require_once __DIR__ . '/vendor/autoload.php';

function getGoogleClient() {
    $client = new Google_Client();
    $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
    $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
    $client->setRedirectUri(getenv('GOOGLE_REDIRECT_URI'));
    $client->addScope("email");
    $client->addScope("profile");
    
    return $client;
}

function handleGoogleCallback($code) {
    try {
        $client = getGoogleClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        
        if (isset($token['error'])) {
            throw new Exception('Google OAuth error: ' . $token['error']);
        }
        
        $client->setAccessToken($token['access_token']);
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        return [
            'success' => true,
            'email' => $google_account_info->email,
            'name' => $google_account_info->name,
            'google_id' => $google_account_info->id,
            'avatar' => $google_account_info->picture
        ];
        
    } catch (Exception $e) {
        error_log("Google OAuth Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function getGoogleAuthUrl() {
    $client = getGoogleClient();
    return $client->createAuthUrl();
}
?>