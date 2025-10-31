<?php
require_once 'sendmail.php';

$email = "test@example.com";
$otp = "123456";
$name = "Test User";

echo "🧪 Testing Local Email System...\n";

$result = sendOTPEmail($email, $otp, $name);

if ($result) {
    echo "✅ Local email system working!\n";
    echo "📧 Check your XAMPP mail logs or email client\n";
} else {
    echo "❌ Local email failed\n";
}