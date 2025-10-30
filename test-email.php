<?php
// TEST EMAIL SCRIPT - DELETE THIS FILE AFTER TESTING!
require_once "sendmail.php";

echo "<h1>Email Test Script</h1>";
echo "<p>Testing OTP email functionality...</p>";

$test_email = "your-test-email@gmail.com"; // CHANGE THIS TO YOUR EMAIL
$test_name = "Test User";
$test_otp = "123456";

echo "<p>Sending test OTP to: <strong>$test_email</strong></p>";
echo "<p>OTP Code: <strong>$test_otp</strong></p>";
echo "<hr>";

$result = sendOTPEmail($test_email, $test_otp, $test_name);

if($result) {
    echo "<h2 style='color: green;'>✅ SUCCESS!</h2>";
    echo "<p>Email sent successfully. Check your inbox and spam folder.</p>";
} else {
    echo "<h2 style='color: red;'>❌ FAILED!</h2>";
    echo "<p>Email sending failed. Check the error logs below.</p>";
}

echo "<hr>";
echo "<h3>Server Error Logs:</h3>";
echo "<pre>";
// Show last 50 lines of error log
$error_log = error_get_last();
if($error_log) {
    print_r($error_log);
} else {
    echo "No errors found in error_get_last()";
}
echo "</pre>";

echo "<hr>";
echo "<p><strong>Note:</strong> Check Railway logs for detailed PHPMailer debug output.</p>";
echo "<p><strong>IMPORTANT:</strong> Delete this file after testing for security!</p>";
?>