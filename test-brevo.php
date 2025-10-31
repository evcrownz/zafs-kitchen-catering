<?php
require 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "<h2>🔍 Brevo Configuration Check</h2>";
echo "<pre>";

// Check if .env variables are loaded
echo "✅ Environment Variables:\n";
echo "BREVO_HOST: " . ($_ENV['BREVO_HOST'] ?? 'NOT SET') . "\n";
echo "BREVO_PORT: " . ($_ENV['BREVO_PORT'] ?? 'NOT SET') . "\n";
echo "BREVO_USER: " . ($_ENV['BREVO_USER'] ?? 'NOT SET') . "\n";
echo "BREVO_PASS: " . (isset($_ENV['BREVO_PASS']) ? '***hidden***' : 'NOT SET') . "\n";
echo "BREVO_FROM: " . ($_ENV['BREVO_FROM'] ?? 'NOT SET') . "\n";
echo "BREVO_NAME: " . ($_ENV['BREVO_NAME'] ?? 'NOT SET') . "\n\n";

// Test email sending
echo "📧 Testing Email Send...\n\n";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // SMTP configuration
    $mail->SMTPDebug = 2; // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host = $_ENV['BREVO_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['BREVO_USER'];
    $mail->Password = $_ENV['BREVO_PASS'];
    $mail->SMTPSecure = 'tls';
    $mail->Port = (int)$_ENV['BREVO_PORT'];

    // Recipients
    $mail->setFrom($_ENV['BREVO_FROM'], $_ENV['BREVO_NAME']);
    $mail->addAddress($_ENV['BREVO_FROM'], 'Test User'); // Send to yourself for testing

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Zaf\'s Kitchen';
    $mail->Body = '<h1>✅ Brevo is working!</h1><p>Your OTP system is ready to go.</p>';
    $mail->AltBody = 'Brevo is working! Your OTP system is ready to go.';

    $mail->send();
    echo "\n\n✅ SUCCESS! Email sent successfully!\n";
    echo "Check your inbox: " . $_ENV['BREVO_FROM'] . "\n";
    
} catch (Exception $e) {
    echo "\n\n❌ FAILED! Email could not be sent.\n";
    echo "Error: {$mail->ErrorInfo}\n";
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
```

### 3. Run the test:

Open your browser and go to:
```
http://localhost/Zafs_kitchen/test-brevo.php