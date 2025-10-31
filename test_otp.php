<?php
echo "<h2>🔍 COMPLETE OTP SYSTEM DIAGNOSTIC</h2>";
echo "<pre>";

// Test 1: Basic PHP Environment
echo "=== PHP ENVIRONMENT ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n";

// Test 2: Required Files
echo "\n=== REQUIRED FILES ===\n";
$files = [
    'vendor/autoload.php',
    'sendmail.php', 
    'controllerUserData.php',
    'composer.json'
];

foreach ($files as $file) {
    $exists = file_exists($file);
    $size = $exists ? filesize($file) : 0;
    echo "$file: " . ($exists ? "✅ EXISTS ($size bytes)" : "❌ MISSING") . "\n";
}

// Test 3: Environment Variables
echo "\n=== ENVIRONMENT VARIABLES ===\n";
$vars = [
    'RESEND_API_KEY',
    'GMAIL_USERNAME',
    'GMAIL_PASSWORD',
    'DB_HOST',
    'DB_NAME',
    'DB_USER'
];

foreach ($vars as $var) {
    $value = $_ENV[$var] ?? getenv($var);
    if ($value) {
        if (in_array($var, ['RESEND_API_KEY', 'GMAIL_PASSWORD', 'DB_PASSWORD'])) {
            echo "$var: ✅ FOUND (" . substr($value, 0, 10) . "...)\n";
        } else {
            echo "$var: ✅ FOUND ($value)\n";
        }
    } else {
        echo "$var: ❌ NOT FOUND\n";
    }
}

// Test 4: PHP Classes and Extensions
echo "\n=== PHP CLASSES & EXTENSIONS ===\n";
$extensions = ['curl', 'openssl', 'json', 'pdo', 'mbstring'];
foreach ($extensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? "✅ LOADED" : "❌ MISSING") . "\n";
}

$classes = [
    'Resend',
    'PHPMailer\PHPMailer\PHPMailer',
    'Dotenv\Dotenv',
    'PDO'
];

foreach ($classes as $class) {
    echo "$class: " . (class_exists($class) ? "✅ LOADED" : "❌ MISSING") . "\n";
}

// Test 5: Load and Test sendmail.php
echo "\n=== SENDMAIL.PHP LOAD TEST ===\n";
try {
    require 'sendmail.php';
    echo "✅ sendmail.php loaded successfully\n";
    
    // Test OTP generation
    $otp = generateOTP();
    echo "✅ OTP Generation: $otp\n";
    
    // Test connection function
    if (function_exists('testEmailConnection')) {
        testEmailConnection();
        echo "✅ Email connection test completed\n";
    }
    
} catch (Exception $e) {
    echo "❌ sendmail.php load failed: " . $e->getMessage() . "\n";
}

// Test 6: Actual Email Sending Test
echo "\n=== ACTUAL EMAIL SENDING TEST ===\n";
if (function_exists('sendOTPEmail')) {
    $test_email = "crownicsjames@gmail.com"; // Your verified email
    $test_otp = "123456";
    $test_name = "James Test";
    
    echo "Testing OTP send to: $test_email\n";
    echo "OTP: $test_otp\n";
    echo "Name: $test_name\n\n";
    
    echo "Starting OTP send...\n";
    $result = sendOTPEmail($test_email, $test_otp, $test_name);
    
    if ($result) {
        echo "✅ SUCCESS: OTP send function returned TRUE\n";
        echo "📧 Check your email inbox AND spam folder!\n";
    } else {
        echo "❌ FAILED: OTP send function returned FALSE\n";
        echo "🔍 Check Railway logs for detailed error messages\n";
    }
} else {
    echo "❌ sendOTPEmail function not found\n";
}

// Test 7: Direct Resend Test
echo "\n=== DIRECT RESEND API TEST ===\n";
try {
    $apiKey = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY');
    if ($apiKey) {
        $resend = \Resend::client($apiKey);
        echo "✅ Resend client created successfully\n";
        
        // Try to send to verified email
        $result = $resend->emails->send([
            'from' => 'Zaf\'s Kitchen <onboarding@resend.dev>',
            'to' => ['crownicsjames@gmail.com'],
            'subject' => 'Direct Resend Test - Zaf\'s Kitchen',
            'html' => '<h1>Direct Resend Test</h1><p>If you receive this, Resend API is working!</p>'
        ]);
        
        echo "✅ Direct Resend email sent successfully\n";
        echo "📧 Email ID: " . $result->id . "\n";
    } else {
        echo "❌ Resend API key not available\n";
    }
} catch (Exception $e) {
    echo "❌ Direct Resend Error: " . $e->getMessage() . "\n";
}

// Test 8: Database Connection Test
echo "\n=== DATABASE CONNECTION TEST ===\n";
try {
    require 'connection.php';
    echo "✅ Database connection successful\n";
    
    // Test if usertable exists and can be queried
    $stmt = $conn->query("SELECT COUNT(*) as count FROM usertable");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Users table accessible: " . $result['count'] . " users\n";
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
echo "🎯 NEXT STEPS:\n";
echo "1. Check Railway logs for 'OTP SEND STARTED' messages\n";
echo "2. Check email inbox AND spam folder\n";
echo "3. Look for any error messages in the logs\n";
echo "4. Test actual user registration\n";
echo "</pre>";

// Log the diagnostic run
error_log("🔧 OTP Diagnostic run completed at " . date('Y-m-d H:i:s'));
?>