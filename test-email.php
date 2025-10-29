<?php
require 'vendor/autoload.php';
require 'sendmail.php';

// Test OTP email
$test_email = 'agbojames00@gmail.com'; // ← PUT YOUR EMAIL HERE
$test_otp = '123456';
$test_name = 'Test User';

echo "Testing email to: $test_email\n";

if (sendOTPEmail($test_email, $test_otp, $test_name)) {
    echo "✅ Email sent successfully!\n";
} else {
    echo "❌ Email failed to send.\n";
    echo "Check error logs above.\n";
}
