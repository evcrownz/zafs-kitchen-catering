<?php
// TEST SIGNUP FLOW
// This simulates exactly what happens during signup

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🧪 Testing Signup Flow</h2>";
echo "<pre>";

// Include required files
require 'vendor/autoload.php';
require_once 'sendmail.php';

use PHPMailer\PHPMailer\PHPMailer;

// Test data
$test_email = 'zafskitchen95@gmail.com';
$test_name = 'Test User Signup';
$test_otp = generateOTP();

echo "1️⃣  Test Configuration:\n";
echo "   Email: $test_email\n";
echo "   Name: $test_name\n";
echo "   OTP: $test_otp\n\n";

echo "2️⃣  Calling sendOTPEmail()...\n";
echo "   Function call: sendOTPEmail('$test_email', '$test_otp', '$test_name')\n\n";

// This is EXACTLY what controllerUserData.php does
$email_sent = sendOTPEmail($test_email, $test_otp, $test_name);

echo "3️⃣  Result:\n";
echo "   Return value: ";
var_dump($email_sent);
echo "\n";
echo "   Type: " . gettype($email_sent) . "\n\n";

echo "4️⃣  Checking condition (exactly as in controllerUserData.php):\n";
echo "   if(\$email_sent) {\n";
echo "       // Success block\n";
echo "   } else {\n";
echo "       // Error block\n";
echo "   }\n\n";

if($email_sent) {
    echo "   ✅ Condition is TRUE - Success block executed\n";
    echo "   Message: 'OTP has been sent to your email address.'\n\n";
} else {
    echo "   ❌ Condition is FALSE - Error block executed\n";
    echo "   Message: 'Account created but failed to send OTP. Please contact support.'\n\n";
}

echo "5️⃣  Debugging return value:\n";
echo "   \$email_sent === true: " . ($email_sent === true ? 'YES' : 'NO') . "\n";
echo "   \$email_sent == true: " . ($email_sent == true ? 'YES' : 'NO') . "\n";
echo "   (bool)\$email_sent: " . ((bool)$email_sent ? 'TRUE' : 'FALSE') . "\n\n";

echo "6️⃣  Testing with direct PHPMailer call:\n";
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'zafskitchen95@gmail.com';
    $mail->Password = 'edsrxcmgytunsawi';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    
    $mail->setFrom('zafskitchen95@gmail.com', "Zaf's Kitchen");
    $mail->addAddress($test_email, $test_name);
    
    $mail->isHTML(true);
    $mail->Subject = 'Direct PHPMailer Test - Signup Flow';
    $mail->Body = "<h2>Direct Test</h2><p>OTP: <strong>$test_otp</strong></p>";
    
    $result = $mail->send();
    
    echo "   Direct PHPMailer->send() result: ";
    var_dump($result);
    echo "\n";
    echo "   Type: " . gettype($result) . "\n";
    echo "   ✅ Direct call SUCCEEDED\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Direct call FAILED: {$mail->ErrorInfo}\n\n";
}

echo "7️⃣  Checking sendmail.php source code:\n";
$sendmail_content = file_get_contents('sendmail.php');

// Check if there's a return statement
if (strpos($sendmail_content, 'return true;') !== false) {
    echo "   ✅ Found 'return true;' in sendmail.php\n";
} else {
    echo "   ❌ 'return true;' NOT FOUND in sendmail.php!\n";
}

if (strpos($sendmail_content, 'return false;') !== false) {
    echo "   ✅ Found 'return false;' in sendmail.php\n";
} else {
    echo "   ❌ 'return false;' NOT FOUND in sendmail.php!\n";
}

// Check for common issues
$issues = [];

if (strpos($sendmail_content, '$mail->send();') === false) {
    $issues[] = "Missing \$mail->send() call";
}

if (preg_match('/function\s+sendOTPEmail.*?\{(.*?)\}/s', $sendmail_content, $matches)) {
    $function_body = $matches[1];
    
    // Check if return is AFTER send()
    if (strpos($function_body, '$mail->send();') !== false && 
        strpos($function_body, 'return true;') !== false) {
        
        $send_pos = strpos($function_body, '$mail->send();');
        $return_pos = strpos($function_body, 'return true;');
        
        if ($return_pos < $send_pos) {
            $issues[] = "'return true;' appears BEFORE \$mail->send() - will return before sending!";
        }
    }
}

if (!empty($issues)) {
    echo "\n   ⚠️  POTENTIAL ISSUES FOUND:\n";
    foreach ($issues as $issue) {
        echo "   - $issue\n";
    }
} else {
    echo "   ✅ No obvious issues found in sendmail.php structure\n";
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "CONCLUSION:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if ($email_sent === true) {
    echo "✅ sendOTPEmail() is working correctly!\n";
    echo "   The issue must be elsewhere in controllerUserData.php\n\n";
    echo "🔍 Check these:\n";
    echo "   1. Is there a redirect() before checking \$email_sent?\n";
    echo "   2. Is \$email_sent being overwritten?\n";
    echo "   3. Is the if() condition checking the wrong variable?\n";
} else {
    echo "❌ sendOTPEmail() is NOT returning TRUE!\n";
    echo "   This is why signup shows the error message\n\n";
    echo "🔧 Fix needed in sendmail.php:\n";
    echo "   1. Make sure 'return true;' comes AFTER \$mail->send()\n";
    echo "   2. Make sure it's inside the try{} block\n";
    echo "   3. Make sure 'return false;' is in the catch{} block\n";
}

echo "\n</pre>";
?>