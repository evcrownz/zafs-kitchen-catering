<?php
// test_helpers.php
echo "Testing helpers.php...<br>";

if (file_exists(__DIR__ . '/helpers.php')) {
    echo "✅ helpers.php EXISTS<br>";
    
    try {
        require __DIR__ . '/helpers.php';
        echo "✅ helpers.php LOADED<br>";
        
        if (function_exists('getEnv')) {
            echo "✅ getEnv() function AVAILABLE<br>";
            
            // Test getting variables
            $username = getEnv('GMAIL_USERNAME');
            $password = getEnv('GMAIL_PASSWORD');
            
            echo "<br>Variables:<br>";
            echo "GMAIL_USERNAME: " . ($username ? "✅ " . $username : "❌ NOT SET") . "<br>";
            echo "GMAIL_PASSWORD: " . ($password ? "✅ SET (length: " . strlen($password) . ")" : "❌ NOT SET") . "<br>";
        } else {
            echo "❌ getEnv() function NOT FOUND<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error loading helpers.php: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ helpers.php NOT FOUND<br>";
}
?>