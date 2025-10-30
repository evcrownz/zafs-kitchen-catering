<?php
// test-gmail.php - Debug Gmail SMTP connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

echo "<h1>Gmail SMTP Test</h1>";
echo "<pre>";

// Check environment
echo "🔍 Environment Variables:\n";
echo "   GMAIL_EMAIL: " . (getenv('GMAIL_EMAIL') ?: $_ENV['GMAIL_EMAIL'] ?? 'NOT SET') . "\n";
echo "   GMAIL_APP_PASSWORD: " . (getenv('GMAIL_APP_PASSWORD') || isset($_ENV['GMAIL_APP_PASSWORD']) ? 'SET ✅' : 'NOT SET ❌') . "\n\n";

// Check PHP extensions
echo "🔧 PHP Extensions:\n";
echo "   OpenSSL: " . (extension_loaded('openssl') ? 'YES ✅' : 'NO ❌') . "\n";
echo "   Sockets: " . (extension_loaded('sockets') ? 'YES ✅' : 'NO ❌') . "\n\n";

// Check PHPMailer
echo "📦 PHPMailer:\n";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "   PHPMailer installed: YES ✅\n\n";
} else {
    echo "   PHPMailer installed: NO ❌\n";
    echo "   Run: composer require phpmailer/phpmailer\n\n";
    exit;
}

// Test email sending
if (isset($_POST['test_email'])) {
    $test_email = trim($_POST['test_email']);
    
    echo "📧 Sending test email to: " . htmlspecialchars($test_email) . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    
    try {
        $mail = new PHPMailer(true);
        
        // Enable verbose debug output
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            echo htmlspecialchars($str) . "\n";
        };
        
        $gmail_email = getenv('GMAIL_EMAIL') ?: $_ENV['GMAIL_EMAIL'] ?? 'zafskitchen95@gmail.com';
        $gmail_password = getenv('GMAIL_APP_PASSWORD') ?: $_ENV['GMAIL_APP_PASSWORD'] ?? null;
        
        if (!$gmail_password) {
            throw new Exception("GMAIL_APP_PASSWORD not set in environment!");
        }
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmail_email;
        $mail->Password   = $gmail_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom($gmail_email, "Zaf's Kitchen Test");
        $mail->addAddress($test_email, "Test User");
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Test Email - " . date('H:i:s');
        $mail->Body    = "<h1>Test Successful!</h1><p>This email was sent at " . date('Y-m-d H:i:s') . "</p>";
        $mail->AltBody = "Test email sent at " . date('Y-m-d H:i:s');
        
        echo "\n📤 Sending email...\n";
        $mail->send();
        
        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✅ <strong style='color: green;'>EMAIL SENT SUCCESSFULLY!</strong>\n";
        echo "Check your inbox!\n";
        
    } catch (Exception $e) {
        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "❌ <strong style='color: red;'>FAILED!</strong>\n";
        echo "Error: {$mail->ErrorInfo}\n";
        echo "Exception: " . $e->getMessage() . "\n";
    }
}

echo "</pre>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gmail SMTP Test</title>
    <style>
        body {
            font-family: monospace;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            line-height: 1.5;
        }
        input[type="email"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background: #DC2626;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background: #B91C1C;
        }
        h1 { color: #DC2626; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Gmail SMTP Connection Test</h1>
        
        <form method="POST">
            <label><strong>Test Email Address:</strong></label>
            <input type="email" name="test_email" placeholder="your.email@example.com" required>
            <button type="submit">📧 Send Test Email</button>
        </form>
        
        <hr style="margin: 30px 0;">
        
        <h3>📋 Troubleshooting Checklist:</h3>
        <ol>
            <li>✅ PHPMailer installed (composer require phpmailer/phpmailer)</li>
            <li>✅ Environment variables set in Railway</li>
            <li>✅ Gmail App Password created (16 characters, no spaces)</li>
            <li>✅ 2-Step Verification enabled in Gmail</li>
            <li>✅ OpenSSL PHP extension enabled</li>
        </ol>
    </div>
</body>
</html>