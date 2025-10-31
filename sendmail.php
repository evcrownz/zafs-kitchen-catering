<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendOTPEmail($email, $otp, $name) {
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration for Railway
        $mail->isSMTP();
        $mail->Host = getenv('BREVO_HOST') ?: 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('BREVO_USER');
        $mail->Password = getenv('BREVO_PASS');
        $mail->SMTPSecure = 'tls';
        $mail->Port = getenv('BREVO_PORT') ?: 587;
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = true;

        // Debug logging for Railway
        error_log("📧 Attempting to send OTP email to: $email");
        error_log("🔧 SMTP Host: " . $mail->Host);
        error_log("🔧 SMTP Port: " . $mail->Port);
        error_log("🔧 SMTP User: " . ($mail->Username ? "✓ Set" : "✗ Missing"));
        error_log("🔧 SMTP Pass: " . ($mail->Password ? "✓ Set" : "✗ Missing"));

        // Sender info - Use environment variable or fallback
        $fromEmail = getenv('BREVO_FROM') ?: 'crownicsjames@gmail.com';
        $fromName = getenv('BREVO_NAME') ?: "Zaf's Kitchen";
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name);
        $mail->addReplyTo($fromEmail, $fromName);

        // Email content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Your OTP from Zaf\'s Kitchen';
        $mail->Body = "
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
        ";

        $mail->AltBody = "Hello $name,\n\nYour OTP code is: $otp\n\nThis code will expire in 10 minutes.\n\nThank you,\nZaf's Kitchen";

        $result = $mail->send();
        error_log("✅ OTP Email sent successfully to $email");
        return true;

    } catch (Exception $e) {
        error_log("❌ Mailer Error for $email: {$mail->ErrorInfo}");
        error_log("❌ Exception: " . $e->getMessage());
        error_log("❌ Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

function sendPasswordResetEmail($email, $reset_link, $name) {
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration for Railway
        $mail->isSMTP();
        $mail->Host = getenv('BREVO_HOST') ?: 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('BREVO_USER');
        $mail->Password = getenv('BREVO_PASS');
        $mail->SMTPSecure = 'tls';
        $mail->Port = getenv('BREVO_PORT') ?: 587;
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = true;

        // Debug logging for Railway
        error_log("📧 Attempting to send password reset email to: $email");

        // Sender info
        $fromEmail = getenv('BREVO_FROM') ?: 'crownicsjames@gmail.com';
        $fromName = getenv('BREVO_NAME') ?: "Zaf's Kitchen";
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name);
        $mail->addReplyTo($fromEmail, $fromName);

        // Email content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Password Reset Request - Zaf\'s Kitchen';
        $mail->Body = "
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
        ";

        $mail->AltBody = "Hello $name,\n\nClick this link to reset your password: $reset_link\n\nThis link will expire in 30 minutes.\n\nIf you didn't request this, please ignore this email.\n\nThank you,\nZaf's Kitchen";

        $mail->send();
        error_log("✅ Reset email sent successfully to $email");
        return true;

    } catch (Exception $e) {
        error_log("❌ Reset Email Error for $email: {$mail->ErrorInfo}");
        error_log("❌ Exception: " . $e->getMessage());
        return false;
    }
}

function generateOTP($length = 6) {
    return str_pad(random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}
?>