<?php
// sendmail.php - With fallback for local development

if (defined('SENDMAIL_LOADED')) {
    return;
}
define('SENDMAIL_LOADED', true);

require_once __DIR__ . '/helpers.php';

// ✅ OTP Generator
if (!function_exists('generateOTP')) {
    function generateOTP($length = 6) {
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }
}

/**
 * ✅ Smart Email Sender - Uses Resend in production, fallback in local
 */
if (!function_exists('sendOTPEmail')) {
    function sendOTPEmail($email, $otp, $name) {
        // Check if we're in production (Railway)
        $isProduction = (getEnv('RAILWAY_ENVIRONMENT', '') === 'production') || 
                       (getEnv('RAILWAY_GIT_COMMIT_SHA', '') !== '');
        
        if ($isProduction) {
            return sendOTPEmailResend($email, $otp, $name);
        } else {
            return sendOTPEmailLocal($email, $otp, $name);
        }
    }
}

/**
 * ✅ Production: Use Resend
 */
function sendOTPEmailResend($email, $otp, $name) {
    try {
        // Try to load Resend
        if (!class_exists('Resend\Resend')) {
            $resendPath = __DIR__ . '/vendor/resend/resend-php/src/';
            if (file_exists($resendPath . 'Resend.php')) {
                require_once $resendPath . 'Resend.php';
                require_once $resendPath . 'ServiceFactory.php';
                require_once $resendPath . 'Collection.php';
                require_once $resendPath . 'Email.php';
                require_once $resendPath . 'Emails.php';
            }
        }
        
        if (!class_exists('Resend\Resend')) {
            error_log("❌ Resend not available, using fallback");
            return sendOTPEmailLocal($email, $otp, $name);
        }

        $api_key = trim(getEnv('RESEND_API_KEY', ''));
        if (empty($api_key)) {
            return sendOTPEmailLocal($email, $otp, $name);
        }

        error_log("📤 [PRODUCTION] Sending OTP via Resend to: $email");

        $resend = new Resend\Resend($api_key);

        $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safe_otp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $current_year = date('Y');

        $html_body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #E75925; margin: 0;">Zaf's Kitchen</h1>
        </div>
        <h2 style="color: #E75925; margin-bottom: 20px;">Welcome to Zaf's Kitchen!</h2>
        <p style="margin-bottom: 15px;">Hello <strong>{$safe_name}</strong>,</p>
        <p style="margin-bottom: 20px;">Thank you for signing up! Please use the following verification code to complete your registration:</p>
        <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 30px; text-align: center; margin: 30px 0; border-radius: 12px; border-left: 5px solid #E75925;">
            <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">Your Verification Code:</p>
            <h1 style="color: #E75925; font-size: 36px; letter-spacing: 8px; margin: 0; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.1);">{$safe_otp}</h1>
        </div>
        <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <p style="margin: 0; color: #856404;"><strong>Important:</strong> This code will expire in <strong>10 minutes</strong> for security purposes.</p>
        </div>
        <p style="margin-bottom: 20px;">If you didn't create an account with us, please ignore this email.</p>
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;">
            <p style="font-size: 12px; color: #666; text-align: center; margin: 0;">
                This is an automated message from Zaf's Kitchen.<br>
                Please do not reply to this email.<br><br>
                © {$current_year} Zaf's Kitchen. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
HTML;

        $text_body = "Welcome to Zaf's Kitchen!\n\nHello {$safe_name},\n\nYour verification code: {$safe_otp}\n\nThis code expires in 10 minutes.\n\n© {$current_year} Zaf's Kitchen.";

        $result = $resend->emails->send([
            'from' => "Zaf's Kitchen <onboarding@resend.dev>",
            'to' => [$email],
            'subject' => "Email Verification - Zaf's Kitchen",
            'html' => $html_body,
            'text' => $text_body
        ]);

        error_log("✅ [PRODUCTION] OTP Email sent via Resend to: $email");
        return true;

    } catch (Exception $e) {
        error_log("❌ [PRODUCTION] Resend Error: " . $e->getMessage());
        // Fallback to local method
        return sendOTPEmailLocal($email, $otp, $name);
    }
}

/**
 * ✅ Local Development: Use simple mail() function
 */
function sendOTPEmailLocal($email, $otp, $name) {
    error_log("📤 [LOCAL] Using fallback email for: $email");
    
    $subject = "Email Verification - Zaf's Kitchen";
    
    $message = "
    <html>
    <head>
        <title>Email Verification</title>
    </head>
    <body>
        <h2>Welcome to Zaf's Kitchen!</h2>
        <p>Hello $name,</p>
        <p>Your verification code: <strong style='font-size: 24px; color: #E75925;'>$otp</strong></p>
        <p>This code expires in 10 minutes.</p>
        <p><em>This is a local development email.</em></p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Zaf's Kitchen <zafskitchen95@gmail.com>" . "\r\n";
    
    $result = mail($email, $subject, $message, $headers);
    
    if ($result) {
        error_log("✅ [LOCAL] Fallback email sent successfully to: $email");
        // Also display OTP in logs for easy testing
        error_log("🔑 [LOCAL] OTP for $email: $otp");
    } else {
        error_log("❌ [LOCAL] Fallback email failed for: $email");
    }
    
    return $result;
}

// Similar functions for password reset...
if (!function_exists('sendPasswordResetEmail')) {
    function sendPasswordResetEmail($email, $reset_link, $name) {
        $isProduction = (getEnv('RAILWAY_ENVIRONMENT', '') === 'production') || 
                       (getEnv('RAILWAY_GIT_COMMIT_SHA', '') !== '');
        
        if ($isProduction) {
            // Use Resend implementation
            return sendPasswordResetResend($email, $reset_link, $name);
        } else {
            // Use local fallback
            return sendPasswordResetLocal($email, $reset_link, $name);
        }
    }
}

function sendPasswordResetLocal($email, $reset_link, $name) {
    error_log("📤 [LOCAL] Password reset link for $email: $reset_link");
    
    $subject = "Password Reset - Zaf's Kitchen";
    $message = "
    <html>
    <head>
        <title>Password Reset</title>
    </head>
    <body>
        <h2>Password Reset Request</h2>
        <p>Hello $name,</p>
        <p>Click here to reset your password: <a href='$reset_link'>$reset_link</a></p>
        <p><em>This is a local development email.</em></p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Zaf's Kitchen <zafskitchen95@gmail.com>" . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}
?>