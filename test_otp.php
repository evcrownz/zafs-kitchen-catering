<?php
echo "<h2>🔍 UPDATED OTP SYSTEM DIAGNOSTIC</h2>";
echo "<pre>";

// Load sendmail.php first
echo "=== LOADING SENDMAIL.PHP ===\n";
require 'sendmail.php';
echo "✅ sendmail.php loaded successfully\n";

// Test OTP generation
$test_otp = generateOTP();
echo "✅ OTP Generation: $test_otp\n";

// Test actual OTP sending
echo "\n=== ACTUAL OTP SENDING TEST ===\n";
$test_email = "test@example.com"; // This doesn't matter - we send to agbojames00@gmail.com
$test_otp = "999888";
$test_name = "James Diagnostic";

echo "Testing OTP send...\n";
echo "User: $test_name\n";
echo "User Email: $test_email\n";
echo "OTP: $test_otp\n";
echo "Sending to: agbojames00@gmail.com\n\n";

$result = sendOTPEmail($test_email, $test_otp, $test_name);

if ($result) {
    echo "✅ SUCCESS: OTP send function returned TRUE\n";
    echo "📧 Check agbojames00@gmail.com inbox AND spam folder!\n";
} else {
    echo "❌ FAILED: OTP send function returned FALSE\n";
    echo "🔍 Check Railway logs for detailed error messages\n";
}

// Test direct function
echo "\n=== DIRECT FUNCTION TEST ===\n";
$result2 = sendOTPResendDirect($test_email, $test_otp, $test_name);

if ($result2) {
    echo "✅ DIRECT SUCCESS: sendOTPResendDirect returned TRUE\n";
} else {
    echo "❌ DIRECT FAILED: sendOTPResendDirect returned FALSE\n";
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
echo "Check Railway logs for detailed error messages!\n";
echo "</pre>";
?>