<?php
require_once 'vendor/autoload.php';

// Your Google OAuth Credentials
$google_client_id = '938422003003-d5ih6h032urubb52lh0ima1i2orr5pmo.apps.googleusercontent.com';
$google_client_secret = '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';

// Try this encoding for the apostrophe
$google_redirect_uri = 'http://localhost/zafs_kitchen/auth.php';

// Create Google Client
$google_client = new Google_Client();
$google_client->setClientId($google_client_id);
$google_client->setClientSecret($google_client_secret);
$google_client->setRedirectUri($google_redirect_uri);
$google_client->addScope('email');
$google_client->addScope('profile');

function getGoogleLoginUrl() {
    global $google_client;
    return $google_client->createAuthUrl();
}

function handleGoogleCallback($code) {
    global $google_client;
    
    try {
        $token = $google_client->fetchAccessTokenWithAuthCode($code);
        
        error_log("Token Response: " . print_r($token, true));
        
        if (isset($token['error'])) {
            error_log("Token Error: " . $token['error']);
            return ['success' => false, 'message' => 'Failed to get access token: ' . $token['error']];
        }
        
        if (!isset($token['access_token'])) {
            error_log("No access_token in response");
            return ['success' => false, 'message' => 'No access token received'];
        }
        
        $google_client->setAccessToken($token);
        
        $google_service = new Google_Service_Oauth2($google_client);
        $user_info = $google_service->userinfo->get();
        
        error_log("User Info: " . print_r($user_info, true));
        
        return [
            'success' => true,
            'email' => $user_info->email,
            'name' => $user_info->name,
            'google_id' => $user_info->id,
            'avatar' => $user_info->picture ?? ''
        ];
        
    } catch (Exception $e) {
        error_log("Google Auth Exception: " . $e->getMessage());
        return ['success' => false, 'message' => 'Google Auth Error: ' . $e->getMessage()];
    }
}
?>