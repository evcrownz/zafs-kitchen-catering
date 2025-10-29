<?php

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

$email = "";
$name = "";
$errors = array();

function generateOTP($length = 6) {
    // More secure OTP generation
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

// Function to send OTP email
function sendOTPEmail($email, $otp, $name) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // Consider moving these to environment variables or config file
        $mail->Username   = 'zafskitchen95@gmail.com';
        $mail->Password   = 'edsrxcmgytunsawi'; // Consider using environment variable
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // More explicit
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom('zafskitchen95@gmail.com', "Zaf's Kitchen");
        $mail->addAddress($email, htmlspecialchars($name, ENT_QUOTES, 'UTF-8')); // Sanitize name
        $mail->addReplyTo('zafskitchen95@gmail.com', "Zaf's Kitchen Support");

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification - Zaf\'s Kitchen';
        
        // Sanitize variables for HTML output
        $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safe_otp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Email Verification</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #E75925; margin: 0;'>Zaf's Kitchen</h1>
                </div>
                
                <h2 style='color: #E75925; margin-bottom: 20px;'>Welcome to Zaf's Kitchen!</h2>
                <p style='margin-bottom: 15px;'>Hello <strong>$safe_name</strong>,</p>
                <p style='margin-bottom: 20px;'>Thank you for signing up! Please use the following verification code to complete your registration:</p>
                
                <div style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 30px; text-align: center; margin: 30px 0; border-radius: 12px; border-left: 5px solid #E75925;'>
                    <p style='margin: 0 0 10px 0; font-size: 14px; color: #666;'>Your Verification Code:</p>
                    <h1 style='color: #E75925; font-size: 36px; letter-spacing: 8px; margin: 0; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.1);'>$safe_otp</h1>
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
                        © " . date('Y') . " Zaf's Kitchen. All rights reserved.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Alternative plain text version for email clients that don't support HTML
        $mail->AltBody = "
        Welcome to Zaf's Kitchen!
        
        Hello $safe_name,
        
        Thank you for signing up! Please use the following verification code to complete your registration:
        
        Verification Code: $safe_otp
        
        This code will expire in 10 minutes for security purposes.
        
        If you didn't create an account with us, please ignore this email.
        
        This is an automated message from Zaf's Kitchen.
        ";

        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Log the error for debugging (don't expose to user)
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Function to send password reset email (bonus function)
function sendPasswordResetEmail($email, $reset_link, $name) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'zafskitchen95@gmail.com';
        $mail->Password   = 'edsrxcmgytunsawi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('zafskitchen95@gmail.com', "Zaf's Kitchen");
        $mail->addAddress($email, htmlspecialchars($name, ENT_QUOTES, 'UTF-8'));
        $mail->addReplyTo('afskitchen95@gmail.com', "Zaf's Kitchen Support");

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset - Zaf\'s Kitchen';
        
        $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safe_link = htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8');
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #E75925; margin: 0;'>Zaf's Kitchen</h1>
                </div>
                
                <h2 style='color: #E75925; margin-bottom: 20px;'>Password Reset Request</h2>
                <p style='margin-bottom: 15px;'>Hello <strong>$safe_name</strong>,</p>
                <p style='margin-bottom: 20px;'>We received a request to reset your password. Click the button below to create a new password:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$safe_link' style='background-color: #E75925; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>Reset Password</a>
                </div>
                
                <p style='margin-bottom: 20px;'>If the button doesn't work, copy and paste this link into your browser:</p>
                <p style='word-break: break-all; color: #666; font-size: 14px; margin-bottom: 20px;'>$safe_link</p>
                
                <div style='background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0; color: #721c24;'><strong>Security Notice:</strong> If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
                </div>
                
                <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;'>
                    <p style='font-size: 12px; color: #666; text-align: center; margin: 0;'>
                        This is an automated message from Zaf's Kitchen.<br>
                        Please do not reply to this email.<br>
                        <br>
                        © " . date('Y') . " Zaf's Kitchen. All rights reserved.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// ✅ Function to send booking approval email
function sendBookingApprovalEmail($booking) {
    // ✅ DEBUG: Log what we received
    error_log("📧 sendBookingApprovalEmail called with booking ID: " . ($booking['id'] ?? 'UNKNOWN'));
    error_log("📧 Email address: " . ($booking['email'] ?? 'MISSING'));
    error_log("📧 Customer name: " . ($booking['name'] ?? 'MISSING'));
    
    // ✅ Validate required fields
    if (empty($booking['email'])) {
        error_log("❌ ERROR: Email address is empty!");
        return false;
    }
    
    if (empty($booking['name'])) {
        error_log("⚠️ WARNING: Name is empty, using 'Valued Customer'");
        $booking['name'] = 'Valued Customer';
    }
    
    $mail = new PHPMailer(true);

    try {
        // ✅ Enable verbose debug output
        $mail->SMTPDebug = 2; // Enable detailed debug
        $mail->Debugoutput = function($str, $level) {
            error_log("📧 PHPMailer Debug ($level): $str");
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'zafskitchen95@gmail.com';
        $mail->Password   = 'edsrxcmgytunsawi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom('zafskitchen95@gmail.com', "Zaf's Kitchen");
        $mail->addAddress($booking['email'], htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8'));
        $mail->addReplyTo('zafskitchen95@gmail.com', "Zaf's Kitchen Support");

        // Format dates and times with NULL checks
        $eventDate = !empty($booking['event_date']) ? date('F d, Y (l)', strtotime($booking['event_date'])) : 'Not specified';
        $startTime = !empty($booking['start_time']) ? date('g:i A', strtotime($booking['start_time'])) : 'Not specified';
        $endTime = !empty($booking['end_time']) ? date('g:i A', strtotime($booking['end_time'])) : 'Not specified';
        $paymentDeadline = date('F d, Y g:i A', strtotime('+20 hours'));
        $bookingRef = str_pad($booking['id'], 6, '0', STR_PAD_LEFT);
        
        // Sanitize variables with NULL checks
        $safe_name = htmlspecialchars($booking['name'] ?? 'Valued Customer', ENT_QUOTES, 'UTF-8');
        $safe_celebrant = htmlspecialchars($booking['celebrant_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $safe_event_type = htmlspecialchars(ucfirst($booking['event_type'] ?? 'Event'), ENT_QUOTES, 'UTF-8');
        $safe_location = htmlspecialchars($booking['location'] ?? 'Not specified', ENT_QUOTES, 'UTF-8');
        $safe_package = htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['food_package'] ?? 'Standard')), ENT_QUOTES, 'UTF-8');
        $total_price = number_format($booking['total_price'] ?? 0, 2);
        $guest_count = $booking['guest_count'] ?? 0;

        // Content
        $mail->isHTML(true);
        $mail->Subject = "🎉 Your Booking is APPROVED! - Ref #$bookingRef - Zaf's Kitchen";
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Booking Approved</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;'>
            <div style='max-width: 650px; margin: 0 auto; padding: 20px; background-color: #ffffff;'>
                
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); padding: 40px 20px; text-align: center; border-radius: 12px 12px 0 0;'>
                    <h1 style='color: white; margin: 0; font-size: 32px;'>🎉 BOOKING APPROVED!</h1>
                    <p style='color: #FEE2E2; margin: 10px 0 0 0; font-size: 16px;'>Congratulations! Your event is confirmed</p>
                </div>
                
                <!-- Main Content -->
                <div style='padding: 30px;'>
                    <p style='font-size: 16px; margin-bottom: 20px;'>Dear <strong>$safe_name</strong>,</p>
                    
                    <p style='font-size: 16px; line-height: 1.6; margin-bottom: 20px;'>
                        Great news! Your booking for <strong>$safe_celebrant's $safe_event_type</strong> has been 
                        <span style='color: #10B981; font-weight: bold;'>APPROVED</span>! ✅
                    </p>
                    
                    <!-- Payment Deadline Alert -->
                    <div style='background: #FEF3C7; border-left: 5px solid #F59E0B; padding: 20px; margin: 25px 0; border-radius: 8px;'>
                        <p style='margin: 0 0 10px 0; font-size: 18px; font-weight: bold; color: #92400E;'>
                            ⚠️ IMPORTANT: Payment Deadline
                        </p>
                        <p style='margin: 0 0 5px 0; font-size: 15px; color: #78350F;'>
                            Complete your downpayment by:<br>
                            <strong style='font-size: 18px; color: #DC2626;'>$paymentDeadline</strong>
                        </p>
                        <p style='margin: 10px 0 0 0; font-size: 13px; color: #78350F;'>
                            ⏰ Your booking will be automatically cancelled if payment is not received within 20 hours.
                        </p>
                    </div>
                    
                    <!-- Booking Details -->
                    <div style='background: #F9FAFB; border: 2px solid #E5E7EB; border-radius: 12px; padding: 25px; margin: 25px 0;'>
                        <h2 style='color: #DC2626; margin: 0 0 20px 0; font-size: 20px; border-bottom: 2px solid #DC2626; padding-bottom: 10px;'>
                            📋 Booking Details
                        </h2>
                        
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr style='border-bottom: 1px solid #E5E7EB;'>
                                <td style='padding: 12px 0; font-weight: bold; color: #6B7280; width: 40%;'>Booking Reference:</td>
                                <td style='padding: 12px 0; color: #111827;'>#$bookingRef</td>
                            </tr>
                            <tr style='border-bottom: 1px solid #E5E7EB;'>
                                <td style='padding: 12px 0; font-weight: bold; color: #6B7280;'>Event Type:</td>
                                <td style='padding: 12px 0; color: #111827;'>$safe_event_type</td>
                            </tr>
                            <tr style='border-bottom: 1px solid #E5E7EB;'>
                                <td style='padding: 12px 0; font-weight: bold; color: #6B7280;'>Celebrant:</td>
                                <td style='padding: 12px 0; color: #111827;'>$safe_celebrant</td>
                            </tr>
                            <tr style='border-bottom: 1px solid #E5E7EB;'>
                                <td style='padding: 12px 0; font-weight: bold; color: #6B7280;'>Event Date:</td>
                                <td style='padding: 12px 0; color: #111827;'>$eventDate</td>
                            </tr>
                            <tr style='border-bottom: 1px solid #E5E7EB;'>
                                <td style='padding: 12px 0; font-weight: bold; color: #6B7280;'>Event Time:</td>
                                <td style='padding: 12px 0; color: #111827;'>$startTime - $endTime</td>
                            </tr>
                            <tr style='border-bottom: 1px solid #E5E7EB;'>
                                <td style='padding: 12px 0; font-weight: bold; color: #6B7280;'>Location:</td>
                                <td style='padding: 12px 0; color: #111827;'>$safe_location</td>
                            </tr>
                            <tr style='border-bottom: 1px solid #E5E7EB;'>
                                <td style='padding: 12px 0; font-weight: bold; color: #6B7280;'>Number of Guests:</td>
                                <td style='padding: 12px 0; color: #111827;'>$guest_count persons</td>
                            </tr>
                            <tr>
                                <td style='padding: 12px 0; font-weight: bold; color: #6B7280;'>Package:</td>
                                <td style='padding: 12px 0; color: #111827;'>$safe_package</td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Total Amount -->
                    <div style='background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); color: white; padding: 25px; text-align: center; border-radius: 12px; margin: 25px 0;'>
                        <p style='margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;'>Total Amount</p>
                        <p style='margin: 0; font-size: 42px; font-weight: bold; letter-spacing: 1px;'>₱$total_price</p>
                        <p style='margin: 10px 0 0 0; font-size: 13px; opacity: 0.9;'>For $guest_count guests</p>
                    </div>
                    
                    <!-- Contact Information -->
                    <div style='background: #F3F4F6; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                        <h3 style='color: #DC2626; margin: 0 0 15px 0; font-size: 18px;'>📞 Contact Information</h3>
                        <p style='margin: 5px 0; color: #374151;'><strong>Email:</strong> zafskitchen95@gmail.com</p>
                    </div>
                    
                    <p style='margin-top: 30px; color: #374151;'>
                        Best regards,<br>
                        <strong style='color: #DC2626;'>Zaf's Kitchen Team</strong>
                    </p>
                </div>
                
                <!-- Footer -->
                <div style='background: #F9FAFB; padding: 20px; text-align: center; border-top: 1px solid #E5E7EB;'>
                    <p style='margin: 0; font-size: 12px; color: #6B7280;'>
                        © " . date('Y') . " Zaf's Kitchen. All rights reserved.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->send();
        error_log("✅ Approval email SUCCESSFULLY SENT to {$booking['email']} for booking #$bookingRef");
        return true;
        
    } catch (Exception $e) {
        error_log("❌ APPROVAL EMAIL FAILED: " . $mail->ErrorInfo);
        error_log("❌ Exception message: " . $e->getMessage());
        return false;
    }
}
?>

