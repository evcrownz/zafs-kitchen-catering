<?php
// test_email_direct.php - Direct SMTP test with browser output

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h2>🔌 Direct SMTP Connection Test</h2>";
echo "<pre style='background:#000; color:#0f0; padding:20px; border-radius:8px; font-size:12px;'>";

// Configuration
$config = [
    'username' => trim(getEnv('GMAIL_USERNAME', 'zafskitchen95@gmail.com')),
    'password' => trim(getEnv('GMAIL_PASSWORD', '')),
    'from_email' => trim(getEnv('GMAIL_FROM_EMAIL', 'zafskitchen95@gmail.com')),
    'from_name' => trim(getEnv('GMAIL_FROM_NAME', "Zaf's Kitchen"))
];

echo "📋 CONFIGURATION:\n";
echo "================\n";
echo "Username: " . $config['username'] . "\n";
echo "Password: " . str_repeat('*', strlen($config['password'])) . " (length: " . strlen($config['password']) . ")\n";
echo "From Email: " . $config['from_email'] . "\n";
echo "From Name: " . $config['from_name'] . "\n\n";

if (empty($config['password'])) {
    echo "❌ ERROR: Password not set!\n";
    die("</pre>");
}

// Create PHPMailer instance
$mail = new PHPMailer(true);

try {
    echo "🔧 CONFIGURING PHPMailer...\n";
    echo "==========================\n\n";
    
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['username'];
    $mail->Password   = $config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->Timeout    = 30;
    
    // CRITICAL: Output debug directly to browser
    $mail->SMTPDebug = 3; // Maximum verbosity
    $mail->Debugoutput = function($str, $level) {
        echo htmlspecialchars($str) . "\n";
        flush();
        if (ob_get_level()) ob_flush();
    };
    
    echo "✅ SMTP configured for smtp.gmail.com:587 (TLS)\n\n";
    echo "🔌 ATTEMPTING CONNECTION...\n";
    echo "============================\n\n";
    
    // Recipients
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress('crownicsjames@gmail.com', 'Test User');
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Railway';
    $mail->Body    = '<h1>Test Successful!</h1><p>This is a test email from Zaf\'s Kitchen on Railway.</p>';
    $mail->AltBody = 'Test Successful! This is a test email from Zaf\'s Kitchen on Railway.';
    
    echo "\n📧 SENDING EMAIL...\n";
    echo "===================\n\n";
    
    // Flush before sending
    flush();
    if (ob_get_level()) ob_flush();
    
    // Send
    $mail->send();
    
    echo "\n\n✅ ========================================\n";
    echo "✅ EMAIL SENT SUCCESSFULLY! 🎉\n";
    echo "✅ ========================================\n";
    echo "Check crownicsjames@gmail.com inbox!\n";
    
} catch (Exception $e) {
    echo "\n\n❌ ========================================\n";
    echo "❌ EMAIL FAILED TO SEND\n";
    echo "❌ ========================================\n\n";
    echo "Error Message: " . $mail->ErrorInfo . "\n";
    echo "Exception: " . $e->getMessage() . "\n\n";
    
    // Additional diagnostic info
    echo "📋 DIAGNOSTIC INFO:\n";
    echo "==================\n";
    echo "PHP Version: " . phpversion() . "\n";
    echo "OpenSSL: " . (extension_loaded('openssl') ? '✅ Loaded' : '❌ Not loaded') . "\n";
    echo "Socket: " . (extension_loaded('sockets') ? '✅ Loaded' : '❌ Not loaded') . "\n";
    
    // Check if it's an auth error
    if (strpos($mail->ErrorInfo, '535') !== false || strpos($mail->ErrorInfo, 'Authentication') !== false) {
        echo "\n🔑 AUTHENTICATION ERROR DETECTED!\n";
        echo "================================\n";
        echo "This means your Gmail App Password is incorrect or expired.\n\n";
        echo "FIX:\n";
        echo "1. Go to: https://myaccount.google.com/apppasswords\n";
        echo "2. Generate NEW App Password for 'Mail'\n";
        echo "3. Update Railway variable:\n";
        echo "   railway variables set GMAIL_PASSWORD='your-new-password'\n";
    }
    
    // Check if it's a connection error
    if (strpos($mail->ErrorInfo, 'connect()') !== false || strpos($mail->ErrorInfo, 'timeout') !== false) {
        echo "\n🔌 CONNECTION ERROR DETECTED!\n";
        echo "============================\n";
        echo "Railway cannot connect to Gmail SMTP server.\n\n";
        echo "Possible causes:\n";
        echo "- Network/firewall blocking port 587\n";
        echo "- Railway region issue\n";
        echo "- Gmail temporarily blocking Railway IPs\n";
    }
}

echo "</pre>";

echo "<div style='margin-top:20px; padding:15px; background:#f0f0f0; border-radius:8px;'>";
echo "<h3>🔍 What to do next:</h3>";
echo "<ul>";
echo "<li>If you see <strong>535 Authentication failed</strong> → Generate new Google App Password</li>";
echo "<li>If you see <strong>Connection timeout</strong> → Railway network issue, try alternative SMTP</li>";
echo "<li>If you see <strong>220 smtp.gmail.com ESMTP</strong> → Good! Connection successful</li>";
echo "<li>If email sent successfully → Check spam folder in crownicsjames@gmail.com</li>";
echo "</ul>";
echo "</div>";
?>