<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🧪 Resend.io Test</h1>";
echo "<pre>";

// Load autoloader
require 'vendor/autoload.php';

echo "✅ Autoloader loaded\n\n";

// Check if Resend class exists
if (class_exists('\Resend')) {
    echo "✅ Resend class found\n\n";
} else {
    echo "❌ Resend class NOT found\n\n";
    die("STOP: Resend not installed properly!");
}

// Get environment variables
echo "=== ENVIRONMENT VARIABLES ===\n";
$resend_key = getenv('RESEND_API_KEY') ?: ($_ENV['RESEND_API_KEY'] ?? $_SERVER['RESEND_API_KEY'] ?? null);
$sender_email = getenv('SENDER_EMAIL') ?: ($_ENV['SENDER_EMAIL'] ?? $_SERVER['SENDER_EMAIL'] ?? null);
$sender_name = getenv('SENDER_NAME') ?: ($_ENV['SENDER_NAME'] ?? $_SERVER['SENDER_NAME'] ?? "Zaf's Kitchen");

echo "RESEND_API_KEY: " . ($resend_key ? "✅ SET (" . strlen($resend_key) . " chars) - " . substr($resend_key, 0, 20) . "..." : "❌ NOT SET") . "\n";
echo "SENDER_EMAIL: " . ($sender_email ?: "❌ NOT SET") . "\n";
echo "SENDER_NAME: " . $sender_name . "\n\n";

if (!$resend_key || !$sender_email) {
    die("❌ STOP: Missing required environment variables!\n");
}

// Test email sending
echo "=== SENDING TEST EMAIL ===\n";
echo "To: YOUR_EMAIL_HERE@gmail.com\n"; // ⚠️ PALITAN MO TO NG EMAIL MO!
echo "From: $sender_name <$sender_email>\n\n";

try {
    $resend = \Resend::client($resend_key);
    echo "✅ Resend client initialized\n\n";
    
    $result = $resend->emails->send([
        'from' => $sender_name . ' <' . $sender_email . '>',
        'to' => ['YOUR_EMAIL_HERE@gmail.com'], // ⚠️ PALITAN MO TO!
        'subject' => 'Test Email from Resend.io',
        'html' => '<h1>✅ Success!</h1><p>Resend.io is working correctly!</p>',
        'text' => 'Success! Resend.io is working correctly!'
    ]);
    
    echo "📬 RESULT:\n";
    echo "Email ID: " . ($result->id ?? 'N/A') . "\n";
    echo "Status: " . (isset($result->id) ? '✅ SENT SUCCESSFULLY!' : '❌ FAILED') . "\n";
    
    if (isset($result->id)) {
        echo "\n🎉 SUCCESS! Check your email inbox!\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "</pre>";
?>A