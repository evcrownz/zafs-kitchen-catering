<?php
require 'vendor/autoload.php';

// Load environment variables
use Dotenv\Dotenv;
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

echo "<h2>🔍 OTP Sending Diagnostic Test</h2>";
echo "<pre>";

// Test 1: Check Environment Variables
echo "=== ENVIRONMENT VARIABLES TEST ===\n";
$resend_key = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY');
$gmail_user = $_ENV['GMAIL_USERNAME'] ?? getenv('GMAIL_USERNAME');
$gmail_pass = $_ENV['GMAIL_PASSWORD'] ?? getenv('GMAIL_PASSWORD');

echo "RESEND_API_KEY: " . (empty($resend_key) ? "❌ NOT FOUND" : "✅ FOUND (" . substr($resend_key, 0, 10) . "...)") . "\n";
echo "GMAIL_USERNAME: " . (empty($gmail_user) ? "❌ NOT FOUND" : "✅ FOUND ($gmail_user)") . "\n";
echo "GMAIL_PASSWORD: " . (empty($gmail_pass) ? "❌ NOT FOUND" : "✅ FOUND (" . substr($gmail_pass, 0, 3) . "...)") . "\n";

// Test 2: Check Required Classes
echo "\n=== REQUIRED CLASSES TEST ===\n";
$classes = [
    'Resend' => class_exists('Resend'),
    'PHPMailer\PHPMailer\PHPMailer' => class_exists('PHPMailer\PHPMailer\PHPMailer'),
    'Dotenv\Dotenv' => class_exists('Dotenv\Dotenv')
];

foreach ($classes as $class => $exists) {
    echo "$class: " . ($exists ? "✅ LOADED" : "❌ NOT FOUND") . "\n";
}

// Test 3: Test Resend API Connection
echo "\n=== RESEND API CONNECTION TEST ===\n";
try {
    if (!empty($resend_key)) {
        $resend = \Resend::client($resend_key);
        echo "✅ Resend client created successfully\n";
        
        // Try to send a test email
        $test_email = "agbojames040@gmail.com"; // Your test email
        $result = $resend->emails->send([
            'from' => 'Zaf\'s Kitchen <onboarding@resend.dev>',
            'to' => [$test_email],
            'subject' => 'Resend API Test - Zaf\'s Kitchen',
            'html' => '<h1>Resend API Test</h1><p>If you receive this, Resend is working!</p>'
        ]);
        
        echo "✅ Resend test email sent successfully\n";
        echo "📧 Email ID: " . $result->id . "\n";
    } else {
        echo "❌ Cannot test Resend - API key missing\n";
    }
} catch (Exception $e) {
    echo "❌ Resend API Error: " . $e->getMessage() . "\n";
}

// Test 4: Test Gmail SMTP Connection
echo "\n=== GMAIL SMTP CONNECTION TEST ===\n";
try {
    if (!empty($gmail_user) && !empty($gmail_pass)) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $gmail_user;
        $mail->Password = $gmail_pass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 10;
        
        // Test connection
        if (!$mail->smtpConnect()) {
            echo "❌ Gmail SMTP Connection Failed\n";
        } else {
            echo "✅ Gmail SMTP Connection Successful\n";
            $mail->smtpClose();
            
            // Try to send test email
            $mail->isHTML(true);
            $mail->setFrom($gmail_user, 'Zaf\'s Kitchen');
            $mail->addAddress('agbojames040@gmail.com');
            $mail->Subject = 'Gmail SMTP Test - Zaf\'s Kitchen';
            $mail->Body = '<h1>Gmail SMTP Test</h1><p>If you receive this, Gmail SMTP is working!</p>';
            
            if ($mail->send()) {
                echo "✅ Gmail test email sent successfully\n";
            } else {
                echo "❌ Gmail test email failed: " . $mail->ErrorInfo . "\n";
            }
        }
    } else {
        echo "❌ Cannot test Gmail - credentials missing\n";
    }
} catch (Exception $e) {
    echo "❌ Gmail SMTP Error: " . $e->getMessage() . "\n";
}

// Test 5: Test PHP mail() function
echo "\n=== PHP MAIL() FUNCTION TEST ===\n";
$test_email = "agbojames040@gmail.com";
$subject = "PHP mail() Test - Zaf's Kitchen";
$message = "If you receive this, PHP mail() is working!";
$headers = "From: zafskitchen98@gmail.com\r\n";

if (mail($test_email, $subject, $message, $headers)) {
    echo "✅ PHP mail() test sent successfully\n";
} else {
    echo "❌ PHP mail() test failed\n";
}

// Test 6: Check File Permissions and Paths
echo "\n=== FILE SYSTEM CHECK ===\n";
$files_to_check = [
    'vendor/autoload.php' => file_exists('vendor/autoload.php'),
    'sendmail.php' => file_exists('sendmail.php'),
    'composer.json' => file_exists('composer.json'),
    '.env' => file_exists('.env')
];

foreach ($files_to_check as $file => $exists) {
    echo "$file: " . ($exists ? "✅ EXISTS" : "❌ MISSING") . "\n";
}

// Test 7: Test Actual OTP Sending
echo "\n=== ACTUAL OTP SENDING TEST ===\n";
function testOTPSending() {
    try {
        require 'sendmail.php';
        
        $test_email = "agbojames040@gmail.com";
        $test_otp = "123456";
        $test_name = "James Test";
        
        echo "Testing OTP send to: $test_email\n";
        echo "OTP: $test_otp\n";
        echo "Name: $test_name\n";
        
        $result = sendOTPEmail($test_email, $test_otp, $test_name);
        
        if ($result) {
            echo "✅ OTP SEND SUCCESSFUL!\n";
        } else {
            echo "❌ OTP SEND FAILED!\n";
        }
        
        return $result;
    } catch (Exception $e) {
        echo "❌ OTP Test Error: " . $e->getMessage() . "\n";
        return false;
    }
}

testOTPSending();

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
echo "Check your email inbox and spam folder for test emails!\n";
echo "</pre>";
?>