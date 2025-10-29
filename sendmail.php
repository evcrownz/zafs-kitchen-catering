<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

$email = "";
$name = "";
$errors = array();

function generateOTP($length = 6) {
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

// ✅ UPDATED: Enhanced OTP Email with Error Logging
function sendOTPEmail($email, $otp, $name) {
    $mail = new PHPMailer(true);

    try {
        // Enable verbose debug output
        $mail->SMTPDebug = 2; // ← TEMPORARILY ENABLE FOR DEBUGGING
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer DEBUG: $str");
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'zafskitchen95@gmail.com';
        $mail->Password   = 'edsrxcmgytunsawi'; // ⚠️ CHANGE THIS TO APP PASSWORD
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->Timeout    = 30; // ← Add timeout

        // Recipients
        $mail->setFrom('zafskitchen95@gmail.com', "Zaf's Kitchen");
        $mail->addAddress($email, htmlspecialchars($name, ENT_QUOTES, 'UTF-8'));
        $mail->addReplyTo('zafskitchen95@gmail.com', "Zaf's Kitchen Support");

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification - Zaf\'s Kitchen';
        
        $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safe_otp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Email Verification</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #E75925; margin: 0;'>Zaf's Kitchen</h1>
                </div>
                
                <h2 style='color: #E75925; margin-bottom: 20px;'>Welcome to Zaf's Kitchen!</h2>
                <p style='margin-bottom: 15px;'>Hello <strong>$safe_name</strong>,</p>
                <p style='margin-bottom: 20px;'>Thank you for signing up! Please use the following verification code:</p>
                
                <div style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 30px; text-align: center; margin: 30px 0; border-radius: 12px; border-left: 5px solid #E75925;'>
                    <p style='margin: 0 0 10px 0; font-size: 14px; color: #666;'>Your Verification Code:</p>
                    <h1 style='color: #E75925; font-size: 36px; letter-spacing: 8px; margin: 0; font-weight: bold;'>$safe_otp</h1>
                </div>
                
                <div style='background-color: #fff3cd; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0; color: #856404;'><strong>Important:</strong> This code will expire in <strong>10 minutes</strong>.</p>
                </div>
                
                <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;'>
                    <p style='font-size: 12px; color: #666; text-align: center; margin: 0;'>
                        © " . date('Y') . " Zaf's Kitchen. All rights reserved.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->AltBody = "
        Welcome to Zaf's Kitchen!
        
        Hello $safe_name,
        
        Your verification code: $safe_otp
        
        This code expires in 10 minutes.
        ";

        $mail->send();
        error_log("✅ OTP email sent successfully to: $email");
        return true;
        
    } catch (Exception $e) {
        // Detailed error logging
        error_log("❌ PHPMailer Error: " . $mail->ErrorInfo);
        error_log("❌ Exception Message: " . $e->getMessage());
        error_log("❌ Full Trace: " . $e->getTraceAsString());
        return false;
    }
}

// Password reset function (keep existing)
function sendPasswordResetEmail($email, $reset_link, $name) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'zafskitchen95@gmail.com';
        $mail->Password   = 'edsrxcmgytunsawi'; // ⚠️ CHANGE THIS TO APP PASSWORD
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('zafskitchen95@gmail.com', "Zaf's Kitchen");
        $mail->addAddress($email, htmlspecialchars($name, ENT_QUOTES, 'UTF-8'));
        $mail->addReplyTo('zafskitchen95@gmail.com', "Zaf's Kitchen Support");

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset - Zaf\'s Kitchen';
        
        $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safe_link = htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8');
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;'>
                <h2>Password Reset Request</h2>
                <p>Hello <strong>$safe_name</strong>,</p>
                <p>Click the button below to reset your password:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$safe_link' style='background-color: #E75925; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block;'>Reset Password</a>
                </div>
                
                <p>Or copy this link: $safe_link</p>
                <p>© " . date('Y') . " Zaf's Kitchen. All rights reserved.</p>
            </div>
        </body>
        </html>
        ";

        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("❌ Password Reset Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Booking approval email function
function sendBookingApprovalEmail($booking) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'zafskitchen95@gmail.com';
        $mail->Password   = 'edsrxcmgytunsawi'; // ⚠️ CHANGE THIS TO APP PASSWORD
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('zafskitchen95@gmail.com', "Zaf's Kitchen");
        $mail->addAddress($booking['email'], htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8'));

        $eventDate = date('F d, Y (l)', strtotime($booking['event_date']));
        $startTime = date('g:i A', strtotime($booking['start_time']));
        $endTime = date('g:i A', strtotime($booking['end_time']));
        $bookingRef = str_pad($booking['id'], 6, '0', STR_PAD_LEFT);
        
        $mail->isHTML(true);
        $mail->Subject = "🎉 Booking APPROVED - Ref #$bookingRef";
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #DC2626;'>🎉 BOOKING APPROVED!</h1>
                <p>Dear <strong>{$booking['name']}</strong>,</p>
                <p>Your booking for <strong>{$booking['celebrant_name']}'s {$booking['event_type']}</strong> has been approved!</p>
                
                <div style='background: #FEF3C7; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p><strong>⏰ Payment Deadline:</strong> 20 hours from now</p>
                </div>
                
                <p><strong>Event Date:</strong> $eventDate</p>
                <p><strong>Time:</strong> $startTime - $endTime</p>
                <p><strong>Total:</strong> ₱" . number_format($booking['total_price'], 2) . "</p>
                
                <p>Best regards,<br><strong>Zaf's Kitchen Team</strong></p>
            </div>
        </body>
        </html>
        ";

        $mail->send();
        error_log("✅ Approval email sent to {$booking['email']}");
        return true;
        
    } catch (Exception $e) {
        error_log("❌ Approval Email Error: " . $mail->ErrorInfo);
        return false;
    }
}
```

---

## STEP 2: Generate Gmail App Password

### **CRITICAL: Hindi gumana dahil WRONG PASSWORD**

1. **Go to:** https://myaccount.google.com/apppasswords
   
2. **Requirements:**
   - 2-Step Verification must be ON
   
3. **Create App Password:**
   - App: Mail
   - Device: Other (type: "Zaf's Kitchen PHP")
   - Click **Generate**
   
4. **Copy the 16-character password**
```
   Example: abcd efgh ijkl mnop
