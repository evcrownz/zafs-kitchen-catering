<?php
require_once 'vendor/autoload.php';
require_once 'helpers.php';

echo "🧪 Testing Resend Configuration...\n";

// Test API Key
$api_key = getEnv('RESEND_API_KEY', '');
if (empty($api_key)) {
    echo "❌ RESEND_API_KEY is empty\n";
    exit;
}

echo "✅ RESEND_API_KEY found: " . substr($api_key, 0, 8) . "...\n";

// Test Resend Connection
try {
    $resend = new Resend\Resend($api_key);
    
    // Test email send
    $result = $resend->emails->send([
        'from' => 'Zaf\'s Kitchen <onboarding@resend.dev>',
        'to' => ['zafskitchen95@gmail.com'],
        'subject' => 'Test Email from Resend',
        'html' => '<strong>Hello from Zaf\'s Kitchen! Resend is working! 🎉</strong>',
        'text' => 'Hello from Zaf\'s Kitchen! Resend is working! 🎉'
    ]);
    
    echo "✅ Resend test email sent successfully!\n";
    echo "✅ Email ID: " . $result->id . "\n";
    echo "🎉 Resend is properly configured!\n";
    
} catch (Exception $e) {
    echo "❌ Resend Error: " . $e->getMessage() . "\n";
}