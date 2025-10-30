<?php
// helpers.php - Shared helper functions

if (!function_exists('getEnv')) {
    function getEnv($key, $default = null) {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
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
?>