<?php
// ============================================
// SENDMAIL.PHP - 100% FIXED VERSION
// For: Zaf's Kitchen Password Reset
// ============================================

function sendOTPEmail($email, $otp, $name) {
    $apiKey = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    
    if (!$apiKey) {
        error_log("❌ BREVO_API_KEY not set");
        return false;
    }

    $fromEmail = $_ENV['BREVO_FROM'] ?? getenv('BREVO_FROM') ?? 'crownicsjames@gmail.com';
    $fromName = $_ENV['BREVO_NAME'] ?? getenv('BREVO_NAME') ?? "Zaf's Kitchen";

    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safe_otp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $current_year = date('Y');

    $data = [
        'sender' => [
            'name' => $fromName,
            'email' => $fromEmail
        ],
        'to' => [
            [
                'email' => $email,
                'name' => $name
            ]
        ],
        'subject' => 'Email Verification - Zaf\'s Kitchen',
        'htmlContent' => "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Email Verification</title>
            </head>
            <body style='font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;'>
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <h1 style='color: #DC2626; margin: 0;'>Zaf's Kitchen</h1>
                    </div>
                    
                    <h2 style='color: #DC2626; margin-bottom: 20px;'>Welcome to Zaf's Kitchen!</h2>
                    <p style='margin-bottom: 15px;'>Hello <strong>$safe_name</strong>,</p>
                    <p style='margin-bottom: 20px;'>Thank you for signing up! Please use the following verification code to complete your registration:</p>
                    
                    <div style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 30px; text-align: center; margin: 30px 0; border-radius: 12px; border-left: 5px solid #DC2626;'>
                        <p style='margin: 0 0 10px 0; font-size: 14px; color: #666;'>Your Verification Code:</p>
                        <h1 style='color: #DC2626; font-size: 36px; letter-spacing: 8px; margin: 0; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.1);'>$safe_otp</h1>
                    </div>
                    
                    <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                        <p style='margin: 0; color: #856404;'><strong>Important:</strong> This code will expire in <strong>10 minutes</strong> for security purposes.</p>
                    </div>
                    
                    <p style='margin-bottom: 20px;'>If you didn't create an account with us, please ignore this email.</p>
                    
                    <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;'>
                        <p style='font-size: 12px; color: #666; text-align: center; margin: 0;'>
                            This is an automated message from Zaf's Kitchen.<br>
                            Please do not reply to this email.<br>
                            <br>
                            © $current_year Zaf's Kitchen. All rights reserved.
                        </p>
                    </div>
                </div>
            </body>
            </html>
        "
    ];

    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $headers = [
        'accept: application/json',
        'api-key: ' . $apiKey,
        'content-type: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);

    if ($httpCode === 201) {
        error_log("✅ OTP Email sent successfully to $email via Brevo API");
        return true;
    } else {
        error_log("❌ Brevo API Error for $email - HTTP Code: $httpCode");
        error_log("❌ Response: " . $result);
        if ($error) {
            error_log("❌ cURL Error: " . $error);
        }
        return false;
    }
}

function sendPasswordResetEmail($email, $reset_link, $name) {
    $apiKey = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    
    if (!$apiKey) {
        error_log("❌ CRITICAL: BREVO_API_KEY not set for password reset!");
        return false;
    }

    $fromEmail = $_ENV['BREVO_FROM'] ?? getenv('BREVO_FROM') ?? 'crownicsjames@gmail.com';
    $fromName = $_ENV['BREVO_NAME'] ?? getenv('BREVO_NAME') ?? "Zaf's Kitchen";

    error_log("📧 PASSWORD RESET EMAIL ATTEMPT:");
    error_log("   To: $email");
    error_log("   From: $fromEmail");
    error_log("   Link: $reset_link");
    error_log("   API Key Length: " . strlen($apiKey));

    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safe_link = htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8');
    $current_year = date('Y');

    $data = [
        'sender' => [
            'name' => $fromName,
            'email' => $fromEmail
        ],
        'to' => [
            [
                'email' => $email,
                'name' => $name
            ]
        ],
        'subject' => 'Password Reset Request - Zaf\'s Kitchen',
        'htmlContent' => "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Password Reset</title>
            </head>
            <body style='font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border-radius: 10px;'>
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <h1 style='color: #DC2626; margin: 0; font-size: 32px;'>🔐 Zaf's Kitchen</h1>
                    </div>
                    
                    <h2 style='color: #DC2626; margin-bottom: 20px; font-size: 24px;'>Password Reset Request</h2>
                    <p style='margin-bottom: 15px; font-size: 16px;'>Hello <strong>$safe_name</strong>,</p>
                    <p style='margin-bottom: 20px; font-size: 15px; line-height: 1.6;'>We received a request to reset your password for your Zaf's Kitchen account. Click the button below to create a new password:</p>
                    
                    <div style='text-align: center; margin: 35px 0;'>
                        <a href='$safe_link' style='background-color: #DC2626; color: white; padding: 16px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 16px; box-shadow: 0 4px 8px rgba(220, 38, 38, 0.3);'>Reset My Password</a>
                    </div>
                    
                    <p style='margin-bottom: 15px; font-size: 14px; color: #555;'>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #DC2626; font-size: 13px; margin-bottom: 25px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #DC2626;'>$safe_link</p>
                    
                    <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 16px; margin: 25px 0;'>
                        <p style='margin: 0; color: #856404; font-size: 14px;'><strong>⏰ Important:</strong> This link will expire in <strong>30 minutes</strong> for security purposes.</p>
                    </div>
                    
                    <div style='background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 16px; margin: 25px 0;'>
                        <p style='margin: 0; color: #721c24; font-size: 14px;'><strong>🔒 Security Notice:</strong> If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
                    </div>
                    
                    <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;'>
                        <p style='font-size: 12px; color: #666; text-align: center; margin: 0; line-height: 1.6;'>
                            This is an automated message from Zaf's Kitchen.<br>
                            Please do not reply to this email.<br>
                            <br>
                            © $current_year Zaf's Kitchen. All rights reserved.
                        </p>
                    </div>
                </div>
            </body>
            </html>
        "
    ];

    error_log("📤 Sending to Brevo API...");

    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $headers = [
        'accept: application/json',
        'api-key: ' . $apiKey,
        'content-type: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);

    error_log("📨 Brevo Response:");
    error_log("   HTTP Code: $httpCode");
    error_log("   Response: " . $result);
    
    if ($error) {
        error_log("   cURL Error: " . $error);
    }

    if ($httpCode === 201) {
        error_log("✅ SUCCESS! Password reset email sent to $email");
        return true;
    } else {
        error_log("❌ FAILED! Password reset email NOT sent to $email");
        error_log("❌ Full Response: " . print_r(json_decode($result, true), true));
        return false;
    }
}

function generateOTP($length = 6) {
    return str_pad(random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}

function generateResetToken($length = 32) {
    // Generate secure 64-character token (32 bytes = 64 hex chars)
    return bin2hex(random_bytes($length));
}
?>