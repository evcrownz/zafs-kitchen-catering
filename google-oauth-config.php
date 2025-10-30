<?php
// Check if autoloader exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die('❌ Vendor directory not found. Please run: composer install');
}

require_once __DIR__ . '/vendor/autoload.php';

// Check if Google_Client class exists
if (!class_exists('Google_Client')) {
    error_log('❌ Google_Client class not found. Google API Client may not be installed.');
    die('❌ Google API Client library not found. Please run: composer require google/apiclient');
}

// Your Google OAuth Credentials
$google_client_id = '938422003003-d5ih6h032urubb52lh0ima1i2orr5pmo.apps.googleusercontent.com';
$google_client_secret = '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';

// Detect environment (Railway vs localhost)
$is_railway = !empty(getenv('RAILWAY_ENVIRONMENT'));
$base_url = $is_railway 
    ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN') 
    : 'http://localhost/zafs_kitchen';

$google_redirect_uri = $base_url . '/auth.php';

error_log("🔧 OAuth Redirect URI: " . $google_redirect_uri);

// Create Google Client
try {
    $google_client = new Google_Client();
    $google_client->setClientId($google_client_id);
    $google_client->setClientSecret($google_client_secret);
    $google_client->setRedirectUri($google_redirect_uri);
    $google_client->addScope('email');
    $google_client->addScope('profile');
    
    error_log("✅ Google Client initialized successfully");
} catch (Exception $e) {
    error_log("❌ Failed to initialize Google Client: " . $e->getMessage());
    die("Google OAuth configuration failed");
}

function getGoogleLoginUrl() {
    global $google_client;
    try {
        return $google_client->createAuthUrl();
    } catch (Exception $e) {
        error_log("❌ Failed to create auth URL: " . $e->getMessage());
        return '#';
    }
}

function handleGoogleCallback($code) {
    global $google_client;
    
    try {
        $token = $google_client->fetchAccessTokenWithAuthCode($code);
        
        error_log("🔍 Token Response: " . print_r($token, true));
        
        if (isset($token['error'])) {
            error_log("❌ Token Error: " . $token['error']);
            return ['success' => false, 'message' => 'Failed to get access token: ' . $token['error']];
        }
        
        if (!isset($token['access_token'])) {
            error_log("❌ No access_token in response");
            return ['success' => false, 'message' => 'No access token received'];
        }
        
        $google_client->setAccessToken($token);
        
        $google_service = new Google_Service_Oauth2($google_client);
        $user_info = $google_service->userinfo->get();
        
        error_log("✅ User Info: " . print_r($user_info, true));
        
        return [
            'success' => true,
            'email' => $user_info->email,
            'name' => $user_info->name,
            'google_id' => $user_info->id,
            'avatar' => $user_info->picture ?? ''
        ];
        
    } catch (Exception $e) {
        error_log("❌ Google Auth Exception: " . $e->getMessage());
        return ['success' => false, 'message' => 'Google Auth Error: ' . $e->getMessage()];
    }
}
?>