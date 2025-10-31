<?php
// Test if logging works in Railway
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr');

error_log("🧪 TEST: Railway logging test - OTP System");
error_log("🧪 TEST: Resend API Key: " . (getenv('RESEND_API_KEY') ? 'SET' : 'NOT SET'));

// Test Resend
if (class_exists('Resend\Resend')) {
    error_log("✅ Resend class loaded");
} else {
    error_log("❌ Resend class NOT loaded");
}

echo "Check Railway logs for test messages!";
?>