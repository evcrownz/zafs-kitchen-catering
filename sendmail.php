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
            </head>
            <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f5f5f5;'>
                <table role='presentation' style='width: 100%; border-collapse: collapse; background-color: #f5f5f5;'>
                    <tr>
                        <td align='center' style='padding: 40px 20px;'>
                            <table role='presentation' style='max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                                <!-- Header with Logo -->
                                <tr>
                                    <td style='background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); padding: 40px 30px; text-align: center;'>
                                        <img src='cid:logo' alt='Zaf\'s Kitchen' style='width: 120px; height: auto; margin-bottom: 15px;' />
                                        <h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: 600; letter-spacing: -0.5px;'>Zaf's Kitchen</h1>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style='padding: 40px 30px;'>
                                        <h2 style='color: #1f2937; margin: 0 0 20px 0; font-size: 24px; font-weight: 600;'>Email Verification</h2>
                                        <p style='color: #4b5563; margin: 0 0 15px 0; font-size: 16px; line-height: 1.6;'>Hello <strong style='color: #1f2937;'>$name</strong>,</p>
                                        <p style='color: #4b5563; margin: 0 0 25px 0; font-size: 16px; line-height: 1.6;'>Thank you for signing up! Please use the verification code below to complete your registration:</p>
                                        
                                        <!-- OTP Box -->
                                        <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 30px 0;'>
                                            <tr>
                                                <td style='background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); padding: 30px; text-align: center; border-radius: 12px; border: 2px solid #fecaca;'>
                                                    <div style='color: #DC2626; font-size: 42px; font-weight: 700; letter-spacing: 12px; font-family: \"Courier New\", monospace; margin: 0;'>$otp</div>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Info Box -->
                                        <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 25px 0;'>
                                            <tr>
                                                <td style='background-color: #fef3c7; padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b;'>
                                                    <p style='margin: 0; color: #92400e; font-size: 14px; line-height: 1.5;'>
                                                        <strong style='font-size: 16px;'>⏰ Important:</strong><br>
                                                        This verification code will expire in <strong>10 minutes</strong>.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <p style='color: #6b7280; margin: 25px 0 0 0; font-size: 14px; line-height: 1.6;'>If you didn't create an account with Zaf's Kitchen, you can safely ignore this email.</p>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style='background-color: #f9fafb; padding: 30px; border-top: 1px solid #e5e7eb;'>
                                        <p style='margin: 0 0 10px 0; color: #9ca3af; font-size: 13px; text-align: center; line-height: 1.5;'>
                                            This is an automated message from Zaf's Kitchen.
                                        </p>
                                        <p style='margin: 0; color: #9ca3af; font-size: 13px; text-align: center; line-height: 1.5;'>
                                            © 2024 Zaf's Kitchen. All rights reserved.
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
            </head>
            <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f5f5f5;'>
                <table role='presentation' style='width: 100%; border-collapse: collapse; background-color: #f5f5f5;'>
                    <tr>
                        <td align='center' style='padding: 40px 20px;'>
                            <table role='presentation' style='max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                                <!-- Header with Logo -->
                                <tr>
                                    <td style='background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); padding: 40px 30px; text-align: center;'>
                                        <img src='cid:logo' alt='Zaf\'s Kitchen' style='width: 120px; height: auto; margin-bottom: 15px;' />
                                        <h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: 600; letter-spacing: -0.5px;'>Zaf's Kitchen</h1>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style='padding: 40px 30px;'>
                                        <h2 style='color: #1f2937; margin: 0 0 20px 0; font-size: 24px; font-weight: 600;'>Password Reset Request</h2>
                                        <p style='color: #4b5563; margin: 0 0 15px 0; font-size: 16px; line-height: 1.6;'>Hello <strong style='color: #1f2937;'>$name</strong>,</p>
                                        <p style='color: #4b5563; margin: 0 0 25px 0; font-size: 16px; line-height: 1.6;'>We received a request to reset your password. Click the button below to create a new password for your account:</p>
                                        
                                        <!-- CTA Button -->
                                        <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 30px 0;'>
                                            <tr>
                                                <td align='center'>
                                                    <a href='$reset_link' style='display: inline-block; background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 2px 4px rgba(220, 38, 38, 0.3);'>Reset Password</a>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Info Box -->
                                        <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 25px 0;'>
                                            <tr>
                                                <td style='background-color: #fef3c7; padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b;'>
                                                    <p style='margin: 0; color: #92400e; font-size: 14px; line-height: 1.5;'>
                                                        <strong style='font-size: 16px;'>⏰ Important:</strong><br>
                                                        This reset link will expire in <strong>30 minutes</strong> for security reasons.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <p style='color: #6b7280; margin: 25px 0 0 0; font-size: 14px; line-height: 1.6;'>If you didn't request a password reset, please ignore this email. Your password will remain unchanged.</p>
                                        
                                        <!-- Alternative Link -->
                                        <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 30px 0 0 0;'>
                                            <tr>
                                                <td style='background-color: #f9fafb; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;'>
                                                    <p style='margin: 0 0 10px 0; color: #6b7280; font-size: 13px; line-height: 1.5;'>
                                                        <strong>Having trouble with the button?</strong><br>
                                                        Copy and paste this link into your browser:
                                                    </p>
                                                    <p style='margin: 0; color: #DC2626; font-size: 12px; word-break: break-all; line-height: 1.5;'>
                                                        $reset_link
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style='background-color: #f9fafb; padding: 30px; border-top: 1px solid #e5e7eb;'>
                                        <p style='margin: 0 0 10px 0; color: #9ca3af; font-size: 13px; text-align: center; line-height: 1.5;'>
                                            This is an automated message from Zaf's Kitchen.
                                        </p>
                                        <p style='margin: 0; color: #9ca3af; font-size: 13px; text-align: center; line-height: 1.5;'>
                                            © 2024 Zaf's Kitchen. All rights reserved.
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