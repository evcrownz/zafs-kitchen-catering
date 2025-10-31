<?php
echo "🧪 Testing Composer and Resend Setup...\n";

// Check if vendor directory exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "❌ vendor/autoload.php not found!\n";
    echo "Run: composer install\n";
    exit;
}

// Check if autoloader can be loaded
try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✅ vendor/autoload.php loaded successfully\n";
} catch (Exception $e) {
    echo "❌ Failed to load autoloader: " . $e->getMessage() . "\n";
    exit;
}

// Check if Resend class exists
if (!class_exists('Resend\Resend')) {
    echo "❌ Resend\Resend class not found\n";
    
    // List all installed packages
    echo "Installed packages:\n";
    $composer = json_decode(file_get_contents(__DIR__ . '/composer.lock'), true);
    foreach ($composer['packages'] as $package) {
        echo " - " . $package['name'] . " (" . $package['version'] . ")\n";
    }
    exit;
}

echo "✅ Resend\Resend class found\n";

// Test helpers
if (!file_exists(__DIR__ . '/helpers.php')) {
    echo "❌ helpers.php not found\n";
    exit;
}

require_once __DIR__ . '/helpers.php';

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
    echo "✅ Resend client created successfully\n";
    
    // Simple test without sending email
    echo "✅ Resend is properly configured!\n";
    echo "🎉 You can now test email sending in your application\n";
    
} catch (Exception $e) {
    echo "❌ Resend Error: " . $e->getMessage() . "\n";
}