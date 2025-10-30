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
?>