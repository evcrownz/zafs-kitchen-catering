<?php
// test_autoload.php
echo "Testing autoload...<br>";

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "✅ vendor/autoload.php EXISTS<br>";
    
    try {
        require __DIR__ . '/vendor/autoload.php';
        echo "✅ vendor/autoload.php LOADED<br>";
        
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            echo "✅ PHPMailer class AVAILABLE<br>";
        } else {
            echo "❌ PHPMailer class NOT FOUND<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error loading autoload: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ vendor/autoload.php NOT FOUND<br>";
    echo "Run: composer install<br>";
}
?>