<?php
// helpers.php - Shared helper functions

// ✅ Load .env file for local development
if (file_exists(__DIR__ . '/.env') && !getenv('RAILWAY_ENVIRONMENT')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Remove quotes if present
        $value = trim($value, '"\'');
        
        // Set environment variable
        if (!getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('getEnv')) {
    function getEnv($key, $default = null) {
        // Check $_ENV first (Railway sets these)
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Check $_SERVER (alternative)
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        // Check getenv()
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Return default
        return $default;
    }
}

if (!function_exists('generateOTP')) {
    function generateOTP($length = 6) {
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }
}

// ✅ Debug function to check environment variables (for testing only)
if (!function_exists('debugEnv')) {
    function debugEnv() {
        if (getEnv('RAILWAY_ENVIRONMENT')) {
            error_log("🚀 Running on Railway");
            error_log("📧 GMAIL_USERNAME: " . (getEnv('GMAIL_USERNAME') ? 'SET' : 'NOT SET'));
            error_log("🔑 GMAIL_PASSWORD: " . (getEnv('GMAIL_PASSWORD') ? 'SET (length: ' . strlen(getEnv('GMAIL_PASSWORD')) . ')' : 'NOT SET'));
        } else {
            error_log("💻 Running locally");
        }
    }
}
?>