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
        'subject' => 'Email Verification - Zaf\'s Kitchen',
        'htmlContent' => "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <meta http-equiv='X-UA-Compatible' content='IE=edge'>
                <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap' rel='stylesheet'>
            </head>
            <body style='margin: 0; padding: 0; font-family: \"Poppins\", sans-serif; background-color: #1a1a1a;'>
                <table role='presentation' style='width: 100%; border-collapse: collapse; background-color: #1a1a1a;'>
                    <tr>
                        <td align='center' style='padding: 40px 20px;'>
                            <table role='presentation' style='max-width: 600px; width: 100%; border-collapse: collapse; background-color: #1a1a1a;'>
                                
                                <!-- Welcome Text -->
                                <tr>
                                    <td style='padding: 40px 30px 30px 30px; text-align: center;'>
                                        <h2 style='color: #ff5722; margin: 0; font-size: 26px; font-weight: 600; font-family: \"Poppins\", sans-serif;'>Welcome to Zaf's Kitchen!</h2>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style='padding: 0 30px;'>
                                        <p style='color: #ffffff; margin: 0 0 10px 0; font-size: 16px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>Hello <strong style='font-weight: 600;'>$name</strong>,</p>
                                        <p style='color: #cccccc; margin: 0 0 30px 0; font-size: 16px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>Thank you for signing up! Please use the following verification code to complete your registration:</p>
                                        
                                        <!-- OTP Box -->
                                        <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 30px 0;'>
                                            <tr>
                                                <td style='background-color: #2d2d2d; padding: 25px; text-align: center; border-radius: 8px; border-left: 4px solid #ff5722;'>
                                                    <p style='color: #999999; margin: 0 0 12px 0; font-size: 13px; font-family: \"Poppins\", sans-serif; font-weight: 500;'>Your Verification Code:</p>
                                                    <div style='color: #ff5722; font-size: 36px; font-weight: 700; letter-spacing: 10px; font-family: \"Poppins\", sans-serif; margin: 0;'>$otp</div>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Important Notice -->
                                        <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 25px 0;'>
                                            <tr>
                                                <td style='background-color: #3d3d00; padding: 18px; border-radius: 6px; border-left: 4px solid #ffd700;'>
                                                    <p style='margin: 0; color: #ffd700; font-size: 14px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>
                                                        <strong style='font-weight: 600;'>Important:</strong> This code will expire in <strong style='font-weight: 600;'>10 minutes</strong> for security purposes.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <p style='color: #999999; margin: 25px 0 40px 0; font-size: 14px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>If you didn't create an account with us, please ignore this email.</p>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style='padding: 30px; border-top: 1px solid #333333;'>
                                        <p style='margin: 0 0 10px 0; color: #666666; font-size: 13px; text-align: center; line-height: 1.5; font-family: \"Poppins\", sans-serif;'>
                                            This is an automated message from Zaf's Kitchen.<br>
                                            Please do not reply to this email.
                                        </p>
                                        <p style='margin: 0; color: #666666; font-size: 12px; text-align: center; line-height: 1.5; font-family: \"Poppins\", sans-serif;'>
                                            © 2020 Zaf's Kitchen. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
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
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <meta http-equiv='X-UA-Compatible' content='IE=edge'>
                <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap' rel='stylesheet'>
            </head>
            <body style='margin: 0; padding: 0; font-family: \"Poppins\", sans-serif; background-color: #1a1a1a;'>
                <table role='presentation' style='width: 100%; border-collapse: collapse; background-color: #1a1a1a;'>
                    <tr>
                        <td align='center' style='padding: 40px 20px;'>
                            <table role='presentation' style='max-width: 600px; width: 100%; border-collapse: collapse; background-color: #1a1a1a;'>
                                
                                <!-- Title -->
                                <tr>
                                    <td style='padding: 40px 30px 30px 30px; text-align: center;'>
                                        <h2 style='color: #ff5722; margin: 0; font-size: 26px; font-weight: 600; font-family: \"Poppins\", sans-serif;'>Reset Your Password</h2>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style='padding: 0 30px;'>
                                        <p style='color: #ffffff; margin: 0 0 10px 0; font-size: 16px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>Hello <strong style='font-weight: 600;'>$name</strong>,</p>
                                        <p style='color: #cccccc; margin: 0 0 30px 0; font-size: 16px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>We received a request to reset your password. Click the button below to create a new password:</p>
                                        
                                        <!-- CTA Button -->
                                        <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 30px 0;'>
                                            <tr>
                                                <td align='center'>
                                                    <a href='$reset_link' style='display: inline-block; background-color: #ff5722; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; font-family: \"Poppins\", sans-serif;'>Reset Password</a>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Important Notice -->
                                        <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 25px 0;'>
                                            <tr>
                                                <td style='background-color: #3d3d00; padding: 18px; border-radius: 6px; border-left: 4px solid #ffd700;'>
                                                    <p style='margin: 0; color: #ffd700; font-size: 14px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>
                                                        <strong style='font-weight: 600;'>Important:</strong> This link will expire in <strong style='font-weight: 600;'>30 minutes</strong> for security purposes.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <p style='color: #999999; margin: 25px 0 0 0; font-size: 14px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>If you didn't request a password reset, please ignore this email. Your password will remain unchanged.</p>
                                        
                                        <!-- Alternative Link -->
                                        <p style='margin: 30px 0 40px 0; padding-top: 20px; border-top: 1px solid #333333; color: #999999; font-size: 13px; line-height: 1.5; font-family: \"Poppins\", sans-serif;'>
                                            <strong style='font-weight: 600; color: #cccccc;'>Button not working?</strong><br>
                                            Copy and paste this link:<br>
                                            <span style='color: #ff5722; word-break: break-all;'>$reset_link</span>
                                        </p>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style='padding: 30px; border-top: 1px solid #333333;'>
                                        <p style='margin: 0 0 10px 0; color: #666666; font-size: 13px; text-align: center; line-height: 1.5; font-family: \"Poppins\", sans-serif;'>
                                            This is an automated message from Zaf's Kitchen.<br>
                                            Please do not reply to this email.
                                        </p>
                                        <p style='margin: 0; color: #666666; font-size: 12px; text-align: center; line-height: 1.5; font-family: \"Poppins\", sans-serif;'>
                                            © 2020 Zaf's Kitchen. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
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