<?php
include 'sendmail.php';

$otp = generateOTP();
$result = sendOTPEmail('agbojames00@gmail.com', $otp, 'Tester');

if ($result) echo "✅ OTP email sent!";
else echo "❌ Failed to send email.";
?>
