<?php
// test_otp.php - SIMPLE TEST LANG
require 'vendor/autoload.php';
require 'sendmail.php';

echo "📧 Testing OTP Email...\n";

// PALITAN MO ITO NG GMAIL MO!
$my_email = "agbojames00@gmail.com"; 

$result = sendOTPEmail($my_email, "123456", "Test User");

if($result) {
    echo "✅ SUCCESS! Check Gmail mo!\n";
} else {
    echo "❌ FAILED! Ayusin muna Railway variables.\n";
}
?>