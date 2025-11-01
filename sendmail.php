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
        'subject' => 'Verify Your Email - Zaf\'s Kitchen',
        'htmlContent' => "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <meta http-equiv='X-UA-Compatible' content='IE=edge'>
                <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
            </head>
            <body style='margin: 0; padding: 0; font-family: \"Poppins\", sans-serif;'>
                <table role='presentation' style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td align='center' style='padding: 40px 20px;'>
                            <table role='presentation' style='max-width: 600px; width: 100%; border-collapse: collapse;'>
                                <!-- Header with Logo -->
                                <tr>
                                    <td style='padding: 40px 30px; text-align: center;'>
                                        <img src='https://your-domain.com/logo/logo.png' alt='Zaf\'s Kitchen' style='width: 120px; height: auto; margin-bottom: 20px;' />
                                        <h1 style='color: #DC2626; margin: 0; font-size: 28px; font-weight: 600; font-family: \"Poppins\", sans-serif;'>Zaf's Kitchen</h1>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style='padding: 0 30px 40px 30px;'>
                                        <h2 style='color: #1f2937; margin: 0 0 20px 0; font-size: 24px; font-weight: 600; font-family: \"Poppins\", sans-serif;'>Email Verification</h2>
                                        <p style='color: #4b5563; margin: 0 0 15px 0; font-size: 16px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>Hello <strong style='color: #1f2937; font-weight: 600;'>$name</strong>,</p>
                                        <p style='color: #4b5563; margin: 0 0 30px 0; font-size: 16px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>Thank you for signing up! Please use the verification code below to complete your registration:</p>
                                        
                                        <!-- OTP Box -->
                                        <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 30px 0;'>
                                            <tr>
                                                <td style='padding: 30px; text-align: center; border: 2px solid #DC2626; border-radius: 12px;'>
                                                    <div style='color: #DC2626; font-size: 42px; font-weight: 700; letter-spacing: 12px; font-family: \"Poppins\", sans-serif; margin: 0;'>$otp</div>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <p style='margin: 25px 0 0 0; color: #6b7280; font-size: 14px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'><strong style='color: #DC2626; font-weight: 600;'>⏰ Important:</strong> This verification code will expire in <strong style='font-weight: 600;'>10 minutes</strong>.</p>
                                        
                                        <p style='color: #9ca3af; margin: 25px 0 0 0; font-size: 14px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>If you didn't create an account with Zaf's Kitchen, you can safely ignore this email.</p>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style='padding: 30px; border-top: 1px solid #e5e7eb;'>
                                        <p style='margin: 0 0 5px 0; color: #9ca3af; font-size: 13px; text-align: center; line-height: 1.5; font-family: \"Poppins\", sans-serif;'>
                                            This is an automated message from Zaf's Kitchen.
                                        </p>
                                        <p style='margin: 0; color: #9ca3af; font-size: 13px; text-align: center; line-height: 1.5; font-family: \"Poppins\", sans-serif;'>
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
        'subject' => 'Reset Your Password - Zaf\'s Kitchen',
        'htmlContent' => "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <meta http-equiv='X-UA-Compatible' content='IE=edge'>
                <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
            </head>
            <body style='margin: 0; padding: 0; font-family: \"Poppins\", sans-serif;'>
                <table role='presentation' style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td align='center' style='padding: 40px 20px;'>
                            <table role='presentation' style='max-width: 600px; width: 100%; border-collapse: collapse;'>
                                <!-- Header with Logo -->
                                <tr>
                                    <td style='padding: 40px 30px; text-align: center;'>
                                        <img src='https://your-domain.com/logo/logo.png' alt='Zaf\'s Kitchen' style='width: 120px; height: auto; margin-bottom: 20px;' />
                                        <h1 style='color: #DC2626; margin: 0; font-size: 28px; font-weight: 600; font-family: \"Poppins\", sans-serif;'>Zaf's Kitchen</h1>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style='padding: 0 30px 40px 30px;'>
                                        <h2 style='color: #1f2937; margin: 0 0 20px 0; font-size: 24px; font-weight: 600; font-family: \"Poppins\", sans-serif;'>Password Reset Request</h2>
                                        <p style='color: #4b5563; margin: 0 0 15px 0; font-size: 16px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>Hello <strong style='color: #1f2937; font-weight: 600;'>$name</strong>,</p>
                                        <p style='color: #4b5563; margin: 0 0 30px 0; font-size: 16px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>We received a request to reset your password. Click the button below to create a new password for your account:</p>
                                        
                                        <!-- CTA Button -->
                                        <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 30px 0;'>
                                            <tr>
                                                <td align='center'>
                                                    <a href='$reset_link' style='display: inline-block; background-color: #DC2626; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; font-family: \"Poppins\", sans-serif;'>Reset Password</a>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <p style='margin: 25px 0 0 0; color: #6b7280; font-size: 14px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'><strong style='color: #DC2626; font-weight: 600;'>⏰ Important:</strong> This reset link will expire in <strong style='font-weight: 600;'>30 minutes</strong> for security reasons.</p>
                                        
                                        <p style='color: #9ca3af; margin: 25px 0 0 0; font-size: 14px; line-height: 1.6; font-family: \"Poppins\", sans-serif;'>If you didn't request a password reset, please ignore this email. Your password will remain unchanged.</p>
                                        
                                        <!-- Alternative Link -->
                                        <p style='margin: 30px 0 0 0; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 13px; line-height: 1.5; font-family: \"Poppins\", sans-serif;'>
                                            <strong style='font-weight: 600;'>Having trouble with the button?</strong><br>
                                            Copy and paste this link into your browser:<br>
                                            <span style='color: #DC2626; word-break: break-all;'>$reset_link</span>
                                        </p>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style='padding: 30px; border-top: 1px solid #e5e7eb;'>
                                        <p style='margin: 0 0 5px 0; color: #9ca3af; font-size: 13px; text-align: center; line-height: 1.5; font-family: \"Poppins\", sans-serif;'>
                                            This is an automated message from Zaf's Kitchen.
                                        </p>
                                        <p style='margin: 0; color: #9ca3af; font-size: 13px; text-align: center; line-height: 1.5; font-family: \"Poppins\", sans-serif;'>
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