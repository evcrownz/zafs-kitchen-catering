<?php
// Terminal OTP Email Test
// Run: php test-email-terminal.php

echo "\n";
echo "╔════════════════════════════════════════════════╗\n";
echo "║     🧪 OTP EMAIL CONFIGURATION TEST           ║\n";
echo "║        Zaf's Kitchen - Terminal Version       ║\n";
echo "╚════════════════════════════════════════════════╝\n";
echo "\n";

// Check if PHPMailer is installed
if (!file_exists('vendor/autoload.php')) {
    echo "❌ ERROR: vendor/autoload.php not found!\n";
    echo "Run: composer require phpmailer/phpmailer\n\n";
    exit(1);
}

require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

// Configuration
$test_email = 'zafskitchen95@gmail.com'; // ⬅️ Change to YOUR email
$test_name = 'Test User';
$test_otp = rand(100000, 999999);

echo "📧 Sending test email to: $test_email\n";
echo "🔢 Test OTP Code: $test_otp\n";
echo "⏳ Please wait...\n\n";
echo str_repeat("-", 50) . "\n";

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'zafskitchen95@gmail.com';
    $mail->Password = 'edsrxcmgytunsawi';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    
    // Show debug info in terminal
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'echo';
    
    // Recipients
    $mail->setFrom('zafskitchen95@gmail.com', "Zaf's Kitchen");
    $mail->addAddress($test_email, $test_name);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'OTP Test - Zaf\'s Kitchen [Terminal Test]';
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <body style='font-family: Arial; padding: 20px;'>
        <h2 style='color: #DC2626;'>✅ Email Working!</h2>
        <p>Hello <strong>$test_name</strong>,</p>
        <p>Your OTP email configuration is working correctly!</p>
        <div style='background: #f5f5f5; padding: 20px; text-align: center; margin: 20px 0;'>
            <p style='margin: 0; color: #666;'>Test OTP:</p>
            <h1 style='color: #DC2626; font-size: 36px; letter-spacing: 8px;'>$test_otp</h1>
        </div>
        <p><strong>Tested via Terminal:</strong> " . date('Y-m-d H:i:s') . "</p>
    </body>
    </html>
    ";
    
    $mail->AltBody = "Test OTP: $test_otp";
    
    echo "\n" . str_repeat("-", 50) . "\n";
    echo "🚀 Sending email...\n";
    
    $mail->send();
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ SUCCESS! Email sent successfully!\n";
    echo str_repeat("=", 50) . "\n\n";
    echo "📬 Check your email: $test_email\n";
    echo "   (Check spam folder if not in inbox)\n\n";
    echo "🎉 Your sendmail.php is configured correctly!\n\n";
    echo "Next steps:\n";
    echo "  1. Go to your website's auth.php page\n";
    echo "  2. Try registering a new account\n";
    echo "  3. You should receive the OTP email\n\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "❌ FAILED!\n";
    echo str_repeat("=", 50) . "\n\n";
    echo "Error Message:\n";
    echo $mail->ErrorInfo . "\n\n";
    
    echo "🔍 Troubleshooting:\n";
    echo "  1. Check app password: edsrxcmgytunsawi\n";
    echo "  2. Verify 2FA is enabled on Gmail\n";
    echo "  3. Generate new app password:\n";
    echo "     https://myaccount.google.com/apppasswords\n";
    echo "  4. Check if port 465 is blocked\n";
    echo "  5. Try port 587 instead (change in sendmail.php)\n\n";
    
    exit(1);
}
?>
