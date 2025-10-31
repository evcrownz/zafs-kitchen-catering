<?php
// test_raw_smtp.php - Ultra minimal test with immediate output

// Force immediate output
ob_implicit_flush(true);
ob_end_flush();

echo "<!DOCTYPE html><html><head><title>SMTP Test</title></head><body>";
echo "<h1>🔌 Raw SMTP Connection Test</h1>";
echo "<pre style='background:#000; color:#0f0; padding:20px; font-family:monospace;'>";

flush();

echo "Step 1: Loading PHPMailer...\n";
flush();

try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✅ PHPMailer loaded\n\n";
    flush();
} catch (Exception $e) {
    echo "❌ Failed: " . $e->getMessage() . "\n";
    die("</pre></body></html>");
}

echo "Step 2: Loading helpers...\n";
flush();

try {
    require_once __DIR__ . '/helpers.php';
    echo "✅ Helpers loaded\n\n";
    flush();
} catch (Exception $e) {
    echo "❌ Failed: " . $e->getMessage() . "\n";
    die("</pre></body></html>");
}

echo "Step 3: Getting credentials...\n";
flush();

$username = getEnv('GMAIL_USERNAME');
$password = getEnv('GMAIL_PASSWORD');

echo "Username: " . ($username ? $username : "❌ NOT SET") . "\n";
echo "Password: " . ($password ? "✅ SET (" . strlen($password) . " chars)" : "❌ NOT SET") . "\n\n";
flush();

if (empty($username) || empty($password)) {
    echo "❌ Credentials missing!\n";
    die("</pre></body></html>");
}

echo "Step 4: Creating PHPMailer instance...\n";
flush();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
echo "✅ PHPMailer instance created\n\n";
flush();

echo "Step 5: Configuring SMTP...\n";
flush();

$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = trim($username);
$mail->Password = trim($password);
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL instead of STARTTLS
$mail->Port = 465; // Port 465 instead of 587
$mail->Timeout = 30;

echo "✅ SMTP configured\n";
echo "   Host: smtp.gmail.com\n";
echo "   Port: 587\n";
echo "   Security: STARTTLS\n\n";
flush();

echo "Step 6: Setting up debug output...\n";
flush();

$mail->SMTPDebug = 3;
$mail->Debugoutput = function($str, $level) {
    echo htmlspecialchars($str) . "\n";
    flush();
};

echo "✅ Debug enabled\n\n";
flush();

echo "Step 7: Setting recipients...\n";
flush();

try {
    $mail->setFrom(trim($username), "Zaf's Kitchen");
    $mail->addAddress('crownicsjames@gmail.com', 'Test User');
    echo "✅ Recipients set\n\n";
    flush();
} catch (Exception $e) {
    echo "❌ Failed: " . $e->getMessage() . "\n";
    die("</pre></body></html>");
}

echo "Step 8: Setting email content...\n";
flush();

$mail->isHTML(true);
$mail->Subject = 'Test from Railway - ' . date('Y-m-d H:i:s');
$mail->Body = '<h1>Success!</h1><p>Email working on Railway!</p>';
$mail->AltBody = 'Success! Email working on Railway!';

echo "✅ Content set\n\n";
flush();

echo "========================================\n";
echo "Step 9: SENDING EMAIL NOW...\n";
echo "========================================\n\n";
flush();

try {
    $result = $mail->send();
    
    echo "\n\n";
    echo "========================================\n";
    echo "✅✅✅ SUCCESS! EMAIL SENT! ✅✅✅\n";
    echo "========================================\n";
    echo "Check crownicsjames@gmail.com\n";
    echo "(Check spam folder too!)\n";
    
} catch (Exception $e) {
    echo "\n\n";
    echo "========================================\n";
    echo "❌❌❌ FAILED TO SEND EMAIL ❌❌❌\n";
    echo "========================================\n\n";
    echo "Error: " . $mail->ErrorInfo . "\n\n";
    echo "Exception: " . $e->getMessage() . "\n\n";
    
    // Analyze error
    $error = $mail->ErrorInfo . ' ' . $e->getMessage();
    
    if (stripos($error, '535') !== false || stripos($error, 'authentication') !== false) {
        echo "\n🚨 AUTHENTICATION ERROR!\n";
        echo "======================\n";
        echo "Your Gmail App Password is WRONG or EXPIRED.\n\n";
        echo "TO FIX:\n";
        echo "1. Go to: https://myaccount.google.com/apppasswords\n";
        echo "2. Make sure 2-Step Verification is ON\n";
        echo "3. Generate NEW App Password for 'Mail'\n";
        echo "4. Copy the 16-character password (no spaces!)\n";
        echo "5. Update in Railway:\n";
        echo "   railway variables set GMAIL_PASSWORD='xxxx-xxxx-xxxx-xxxx'\n";
        echo "6. Redeploy and test again\n";
    }
    
    if (stripos($error, 'connect') !== false || stripos($error, 'timeout') !== false) {
        echo "\n🚨 CONNECTION ERROR!\n";
        echo "===================\n";
        echo "Cannot connect to Gmail SMTP.\n";
        echo "Railway might be blocking port 587.\n";
    }
}

echo "</pre>";
echo "<hr>";
echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "</body></html>";
?>