<?php
echo "<h2>🔍 SIMPLE OTP TEST</h2>";
echo "<pre>";

// Load and test
require 'sendmail.php';

echo "Testing OTP system...\n";

$result = sendOTPEmail("test@test.com", "123456", "Test User");

if ($result) {
    echo "✅ SUCCESS: OTP sent!\n";
    echo "📧 Check agbojames00@gmail.com\n";
} else {
    echo "❌ FAILED: OTP not sent\n";
    echo "🔧 Check Railway logs\n";
}

echo "</pre>";
?>