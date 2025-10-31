<?php
// test_email_debug.php - Enhanced debugging for Railway

// Force error display
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Log to Railway
error_log("🧪 ========== EMAIL TEST STARTED ==========");

echo "<h2>🧪 Enhanced Email Test (Railway Debug Mode)</h2>";
echo "<pre style='background:#f4f4f4; padding:15px; border-radius:8px;'>";

// Step 1: Check if files exist
echo "📂 STEP 1: Checking Files...\n";
$files_to_check = [
    'helpers.php' => __DIR__ . '/helpers.php',
    'sendmail.php' => __DIR__ . '/sendmail.php',
    'vendor/autoload.php' => __DIR__ . '/vendor/autoload.php'
];

foreach ($files_to_check as $name => $path) {
    if (file_exists($path)) {
        echo "  ✅ $name EXISTS\n";
        error_log("✅ File exists: $name");
    } else {
        echo "  ❌ $name NOT FOUND\n";
        error_log("❌ File missing: $name");
        die("</pre><p style='color:red;'>❌ Missing required file: $name</p>");
    }
}

// Step 2: Load files
echo "\n📥 STEP 2: Loading Files...\n";
try {
    require_once __DIR__ . '/helpers.php';
    echo "  ✅ helpers.php loaded\n";
    error_log("✅ helpers.php loaded");
} catch (Exception $e) {
    echo "  ❌ Failed to load helpers.php: " . $e->getMessage() . "\n";
    error_log("❌ helpers.php error: " . $e->getMessage());
    die("</pre>");
}

try {
    require_once __DIR__ . '/sendmail.php';
    echo "  ✅ sendmail.php loaded\n";
    error_log("✅ sendmail.php loaded");
} catch (Exception $e) {
    echo "  ❌ Failed to load sendmail.php: " . $e->getMessage() . "\n";
    error_log("❌ sendmail.php error: " . $e->getMessage());
    die("</pre>");
}

// Step 3: Check functions
echo "\n🔧 STEP 3: Checking Functions...\n";
if (function_exists('getEnv')) {
    echo "  ✅ getEnv() available\n";
    error_log("✅ getEnv() available");
} else {
    echo "  ❌ getEnv() NOT FOUND\n";
    error_log("❌ getEnv() not found");
    die("</pre>");
}

if (function_exists('sendOTPEmail')) {
    echo "  ✅ sendOTPEmail() available\n";
    error_log("✅ sendOTPEmail() available");
} else {
    echo "  ❌ sendOTPEmail() NOT FOUND\n";
    error_log("❌ sendOTPEmail() not found");
    die("</pre>");
}

// Step 4: Check configuration
echo "\n⚙️  STEP 4: Checking Configuration...\n";
$config = [
    'GMAIL_USERNAME' => getEnv('GMAIL_USERNAME'),
    'GMAIL_PASSWORD' => getEnv('GMAIL_PASSWORD'),
    'GMAIL_FROM_EMAIL' => getEnv('GMAIL_FROM_EMAIL'),
    'GMAIL_FROM_NAME' => getEnv('GMAIL_FROM_NAME')
];

foreach ($config as $key => $value) {
    if (!empty($value)) {
        if ($key === 'GMAIL_PASSWORD') {
            echo "  ✅ $key: SET (length: " . strlen($value) . ")\n";
            error_log("✅ $key is set (length: " . strlen($value) . ")");
            
            // Check for spaces
            if (strlen(trim($value)) !== strlen($value)) {
                echo "     ⚠️  WARNING: Contains leading/trailing spaces!\n";
                error_log("⚠️  $key contains spaces");
            }
        } else {
            echo "  ✅ $key: $value\n";
            error_log("✅ $key: $value");
        }
    } else {
        echo "  ❌ $key: NOT SET\n";
        error_log("❌ $key not set");
    }
}

// Check environment
$is_railway = !empty(getEnv('RAILWAY_ENVIRONMENT'));
echo "\n🌍 Environment: " . ($is_railway ? "✅ RAILWAY" : "💻 LOCAL") . "\n";
error_log("Environment: " . ($is_railway ? "RAILWAY" : "LOCAL"));

// Step 5: Test email sending
echo "\n📧 STEP 5: Sending Test Email...\n";
echo "  📬 To: crownicsjames@gmail.com\n";
echo "  🔢 OTP: 123456\n";
echo "  👤 Name: Test User\n\n";

error_log("🔄 Attempting to send test email...");

$test_email = "crownicsjames@gmail.com";
$test_otp = "123456";
$test_name = "Test User";

// Flush output buffer to show progress
if (ob_get_level()) ob_flush();
flush();

try {
    $result = sendOTPEmail($test_email, $test_otp, $test_name);
    
    if ($result) {
        echo "  ✅ EMAIL SENT SUCCESSFULLY! 🎉\n\n";
        echo "  📬 Check your inbox at: crownicsjames@gmail.com\n";
        echo "  📁 Don't forget to check SPAM folder too!\n";
        error_log("✅ ========== EMAIL SENT SUCCESSFULLY ==========");
    } else {
        echo "  ❌ EMAIL FAILED TO SEND!\n";
        echo "  📋 Check Railway logs for detailed PHPMailer errors\n";
        error_log("❌ ========== EMAIL SEND FAILED ==========");
    }
} catch (Exception $e) {
    echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n";
    error_log("❌ Exception during email send: " . $e->getMessage());
}

echo "\n========================================\n";
echo "🔍 Check Railway logs for detailed PHPMailer debug output\n";
echo "========================================\n";
echo "</pre>";

error_log("🧪 ========== EMAIL TEST COMPLETED ==========");
?>