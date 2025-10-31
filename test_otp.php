<?php
require 'sendmail.php';

echo "<h2>OTP TEST</h2>";
echo "<pre>";

$result = sendOTPEmail("test@test.com", "123456", "Test User");

if ($result) {
    echo "✅ SUCCESS: OTP logged!\n";
    echo "🔢 OTP: 123456\n";
    echo "📧 Check otp_log.txt file\n";
} else {
    echo "❌ FAILED\n";
}

echo "</pre>";
?>