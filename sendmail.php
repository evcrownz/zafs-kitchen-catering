<?php
function sendOTPEmail($email, $otp, $name) {
    $apiKey = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    
    if (!$apiKey) {
        error_log("❌ BREVO_API_KEY not set");
        return false;
    }

    $fromEmail = $_ENV['BREVO_FROM'] ?? getenv('BREVO_FROM') ?? 'crownicsjames@gmail.com';
    $fromName = $_ENV['BREVO_NAME'] ?? getenv('BREVO_NAME') ?? "Zaf's Kitchen";

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
        'subject' => 'Your OTP from Zaf\'s Kitchen',
        'htmlContent' => "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); padding: 20px; text-align: center;'>
                    <h1 style='color: white; margin: 0;'>🍽️ Zaf's Kitchen</h1>
                </div>
                <div style='padding: 30px; background: #f9f9f9;'>
                    <h2 style='color: #DC2626;'>Email Verification</h2>
                    <p>Hello <b>$name</b>,</p>
                    <p>Thank you for signing up! Your one-time password (OTP) is:</p>
                    <div style='background: white; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                        <h1 style='color: #DC2626; font-size: 36px; letter-spacing: 8px; margin: 0; font-family: monospace;'>$otp</h1>
                    </div>
                    <p style='color: #666;'><b>⏰ This code will expire in 10 minutes.</b></p>
                    <p style='color: #888; font-size: 14px;'>If you didn't request this code, please ignore this email.</p>
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px; text-align: center;'>
                        This is an automated message from Zaf's Kitchen.<br>
                        Please do not reply to this email.
                    </p>
                </div>
            </div>
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
        error_log("❌ BREVO_API_KEY not set");
        return false;
    }

    $fromEmail = $_ENV['BREVO_FROM'] ?? getenv('BREVO_FROM') ?? 'crownicsjames@gmail.com';
    $fromName = $_ENV['BREVO_NAME'] ?? getenv('BREVO_NAME') ?? "Zaf's Kitchen";

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
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); padding: 20px; text-align: center;'>
                    <h1 style='color: white; margin: 0;'>🍽️ Zaf's Kitchen</h1>
                </div>
                <div style='padding: 30px; background: #f9f9f9;'>
                    <h2 style='color: #DC2626;'>Reset Your Password</h2>
                    <p>Hello <b>$name</b>,</p>
                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$reset_link' style='background: #DC2626; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>Reset Password</a>
                    </div>
                    <p style='color: #666;'><b>⏰ This link will expire in 30 minutes.</b></p>
                    <p style='color: #888; font-size: 14px;'>If you didn't request this, please ignore this email and your password will remain unchanged.</p>
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px;'>
                        If the button doesn't work, copy and paste this link:<br>
                        <span style='color: #DC2626; word-break: break-all;'>$reset_link</span>
                    </p>
                </div>
            </div>
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
    
    curl_close($ch);

    if ($httpCode === 201) {
        error_log("✅ Reset email sent successfully to $email via Brevo API");
        return true;
    } else {
        error_log("❌ Brevo API Error for reset email - HTTP Code: $httpCode");
        error_log("❌ Response: " . $result);
        return false;
    }
}

function generateOTP($length = 6) {
    return str_pad(random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}
?>