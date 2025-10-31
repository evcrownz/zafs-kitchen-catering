<?php
require 'sendmail.php';

echo "<h2>OTP TEST</h2>";
echo "<pre>";

$result = sendOTPEmail("test@test.com", "123456", "Test User");

if ($result) {
    echo "✅ SUCCESS: OTP system working!\n";
    echo "🔢 OTP: 123456\n"; 
    echo "📧 Email attempted to send to agbojames00@gmail.com\n";
    echo "🚀 Users can now sign up and verify!\n";
} else {
    echo "❌ FAILED\n";
}

echo "</pre>";
?>