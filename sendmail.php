<?php
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
        'sender' => ['name' => $fromName, 'email' => $fromEmail],
        'to' => [['email' => $email, 'name' => $name]],
        'subject' => 'Email Verification - Zaf\'s Kitchen',
        'htmlContent' => "
            <!DOCTYPE html>
            <html lang='en'>
            <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
            <body style='font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;'>
                    <div style='text-align: center; margin-bottom: 30px;'><h1 style='color: #DC2626; margin: 0;'>Zaf's Kitchen</h1></div>
                    <h2 style='color: #DC2626; margin-bottom: 20px;'>Welcome to Zaf's Kitchen!</h2>
                    <p style='margin-bottom: 15px;'>Hello <strong>$safe_name</strong>,</p>
                    <p style='margin-bottom: 20px;'>Please use the following verification code:</p>
                    <div style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 30px; text-align: center; margin: 30px 0; border-radius: 12px; border-left: 5px solid #DC2626;'>
                        <p style='margin: 0 0 10px 0; font-size: 14px; color: #666;'>Your Verification Code:</p>
                        <h1 style='color: #DC2626; font-size: 36px; letter-spacing: 8px; margin: 0;'>$safe_otp</h1>
                    </div>
                    <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                        <p style='margin: 0; color: #856404;'><strong>Important:</strong> This code expires in <strong>10 minutes</strong>.</p>
                    </div>
                    <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;'>
                        <p style='font-size: 12px; color: #666; text-align: center;'>© $current_year Zaf's Kitchen. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        "
    ];

    return sendBrevoEmail($data);
}

function sendPasswordResetEmail($email, $reset_link, $name) {
    $apiKey = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    if (!$apiKey) { error_log("❌ BREVO_API_KEY not set"); return false; }

    $fromEmail = $_ENV['BREVO_FROM'] ?? getenv('BREVO_FROM') ?? 'crownicsjames@gmail.com';
    $fromName = $_ENV['BREVO_NAME'] ?? getenv('BREVO_NAME') ?? "Zaf's Kitchen";
    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safe_link = htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8');
    $current_year = date('Y');

    $data = [
        'sender' => ['name' => $fromName, 'email' => $fromEmail],
        'to' => [['email' => $email, 'name' => $name]],
        'subject' => 'Password Reset - Zaf\'s Kitchen',
        'htmlContent' => "
            <!DOCTYPE html>
            <html lang='en'>
            <head><meta charset='UTF-8'></head>
            <body style='font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;'>
                    <div style='text-align: center; margin-bottom: 30px;'><h1 style='color: #DC2626; margin: 0;'>Zaf's Kitchen</h1></div>
                    <h2 style='color: #DC2626;'>Password Reset Request</h2>
                    <p>Hello <strong>$safe_name</strong>,</p>
                    <p>Click the button below to reset your password:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$safe_link' style='background-color: #DC2626; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>Reset Password</a>
                    </div>
                    <p style='word-break: break-all; color: #666; font-size: 14px;'>$safe_link</p>
                    <div style='background-color: #fff3cd; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                        <p style='margin: 0; color: #856404;'><strong>Important:</strong> Expires in <strong>30 minutes</strong>.</p>
                    </div>
                    <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;'>
                        <p style='font-size: 12px; color: #666; text-align: center;'>© $current_year Zaf's Kitchen.</p>
                    </div>
                </div>
            </body>
            </html>
        "
    ];

    return sendBrevoEmail($data);
}

// ✅ NEW: Booking Approval Email with GCash Payment Instructions
function sendBookingApprovalEmail($booking) {
    $apiKey = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    
    if (!$apiKey) {
        error_log("❌ BREVO_API_KEY not set for approval email");
        return false;
    }

    $fromEmail = $_ENV['BREVO_FROM'] ?? getenv('BREVO_FROM') ?? 'crownicsjames@gmail.com';
    $fromName = $_ENV['BREVO_NAME'] ?? getenv('BREVO_NAME') ?? "Zaf's Kitchen";
    
    // Extract booking details
    $email = $booking['email'];
    $name = htmlspecialchars($booking['name'] ?? $booking['full_name'], ENT_QUOTES, 'UTF-8');
    $booking_id = htmlspecialchars($booking['id'], ENT_QUOTES, 'UTF-8');
    $celebrant = htmlspecialchars($booking['celebrant_name'], ENT_QUOTES, 'UTF-8');
    $event_type = ucfirst(htmlspecialchars($booking['event_type'], ENT_QUOTES, 'UTF-8'));
    $event_date = date('F d, Y (l)', strtotime($booking['event_date']));
    $start_time = date('g:i A', strtotime($booking['start_time']));
    $end_time = date('g:i A', strtotime($booking['end_time']));
    $guest_count = htmlspecialchars($booking['guest_count'], ENT_QUOTES, 'UTF-8');
    $package = ucfirst(str_replace('_', ' ', htmlspecialchars($booking['food_package'], ENT_QUOTES, 'UTF-8')));
    $location = htmlspecialchars($booking['location'] ?? 'To be confirmed', ENT_QUOTES, 'UTF-8');
    $total_price = number_format($booking['total_price'], 2);
    $downpayment = number_format($booking['total_price'] * 0.5, 2); // 50% downpayment
    
    // Calculate deadline (20 hours from now)
    $deadline = date('F d, Y - g:i A', strtotime('+20 hours'));
    $current_year = date('Y');
    
    // GCash Details - UPDATE THESE WITH YOUR ACTUAL DETAILS
    $gcash_number = '0917-XXX-XXXX'; // Replace with actual GCash number
    $gcash_name = 'Zaf\'s Kitchen / Your Name'; // Replace with actual registered name

    $data = [
        'sender' => ['name' => $fromName, 'email' => $fromEmail],
        'to' => [['email' => $email, 'name' => $name]],
        'subject' => "🎉 Booking Approved! Payment Required - Zaf's Kitchen #$booking_id",
        'htmlContent' => "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            </head>
            <body style='font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;'>
                <div style='max-width: 650px; margin: 0 auto; padding: 20px;'>
                    
                    <!-- Header -->
                    <div style='background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0;'>
                        <h1 style='margin: 0 0 10px 0; font-size: 28px;'>🎉 Booking Approved!</h1>
                        <p style='margin: 0; opacity: 0.9; font-size: 16px;'>Your event is confirmed</p>
                    </div>
                    
                    <!-- Main Content -->
                    <div style='background: #ffffff; padding: 30px; border: 1px solid #e5e7eb;'>
                        
                        <p style='font-size: 16px; margin-bottom: 20px;'>Hi <strong style='color: #DC2626;'>$name</strong>,</p>
                        
                        <p style='margin-bottom: 25px; line-height: 1.6;'>
                            Great news! Your booking has been <strong style='color: #16a34a;'>APPROVED</strong> by our team. 
                            We're excited to cater your upcoming event!
                        </p>
                        
                        <!-- Booking Details Box -->
                        <div style='background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 12px; padding: 25px; margin: 25px 0; border-left: 5px solid #DC2626;'>
                            <h3 style='color: #DC2626; margin: 0 0 20px 0; font-size: 18px;'>📋 Booking Details</h3>
                            <table style='width: 100%; font-size: 14px;'>
                                <tr><td style='padding: 8px 0; color: #666;'>Booking ID:</td><td style='padding: 8px 0; font-weight: bold;'>#$booking_id</td></tr>
                                <tr><td style='padding: 8px 0; color: #666;'>Event Type:</td><td style='padding: 8px 0; font-weight: bold;'>$event_type</td></tr>
                                <tr><td style='padding: 8px 0; color: #666;'>Celebrant:</td><td style='padding: 8px 0; font-weight: bold;'>$celebrant</td></tr>
                                <tr><td style='padding: 8px 0; color: #666;'>Date:</td><td style='padding: 8px 0; font-weight: bold;'>$event_date</td></tr>
                                <tr><td style='padding: 8px 0; color: #666;'>Time:</td><td style='padding: 8px 0; font-weight: bold;'>$start_time - $end_time</td></tr>
                                <tr><td style='padding: 8px 0; color: #666;'>Guests:</td><td style='padding: 8px 0; font-weight: bold;'>$guest_count persons</td></tr>
                                <tr><td style='padding: 8px 0; color: #666;'>Package:</td><td style='padding: 8px 0; font-weight: bold;'>$package</td></tr>
                                <tr><td style='padding: 8px 0; color: #666;'>Location:</td><td style='padding: 8px 0; font-weight: bold;'>$location</td></tr>
                            </table>
                        </div>
                        
                        <!-- Payment Amount Box -->
                        <div style='background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; border-radius: 12px; padding: 25px; margin: 25px 0; text-align: center;'>
                            <p style='margin: 0 0 5px 0; font-size: 14px; opacity: 0.9;'>Total Amount</p>
                            <h2 style='margin: 0 0 15px 0; font-size: 36px;'>₱$total_price</h2>
                            <div style='background: rgba(255,255,255,0.2); border-radius: 8px; padding: 15px; margin-top: 15px;'>
                                <p style='margin: 0 0 5px 0; font-size: 12px; opacity: 0.9;'>Required Downpayment (50%)</p>
                                <h3 style='margin: 0; font-size: 24px;'>₱$downpayment</h3>
                            </div>
                        </div>
                        
                        <!-- URGENT Warning -->
                        <div style='background: #fef3c7; border: 2px solid #f59e0b; border-radius: 12px; padding: 20px; margin: 25px 0;'>
                            <h3 style='color: #92400e; margin: 0 0 10px 0; font-size: 16px;'>⚠️ IMPORTANT - Payment Deadline</h3>
                            <p style='margin: 0 0 10px 0; color: #92400e; font-size: 14px;'>
                                Please complete your <strong>50% downpayment</strong> within:
                            </p>
                            <p style='margin: 0; color: #DC2626; font-size: 20px; font-weight: bold;'>
                                ⏰ 20 HOURS
                            </p>
                            <p style='margin: 10px 0 0 0; color: #92400e; font-size: 13px;'>
                                <strong>Deadline:</strong> $deadline
                            </p>
                            <p style='margin: 10px 0 0 0; color: #b91c1c; font-size: 12px;'>
                                ❌ Your booking will be <strong>automatically cancelled</strong> if payment is not received by the deadline.
                            </p>
                        </div>
                        
                        <!-- GCash Payment Instructions -->
                        <div style='background: #eff6ff; border: 2px solid #3b82f6; border-radius: 12px; padding: 25px; margin: 25px 0;'>
                            <h3 style='color: #1e40af; margin: 0 0 20px 0; font-size: 18px;'>💳 How to Pay via GCash</h3>
                            
                            <div style='background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px;'>
                                <p style='margin: 0 0 10px 0; font-size: 14px; color: #666;'>Send payment to:</p>
                                <p style='margin: 0; font-size: 24px; font-weight: bold; color: #1e40af; letter-spacing: 2px;'>$gcash_number</p>
                                <p style='margin: 5px 0 0 0; font-size: 14px; color: #666;'>Account Name: <strong>$gcash_name</strong></p>
                            </div>
                            
                            <h4 style='color: #1e40af; margin: 0 0 15px 0; font-size: 14px;'>📱 Step-by-Step Guide:</h4>
                            <ol style='margin: 0; padding-left: 20px; color: #374151; font-size: 14px; line-height: 2;'>
                                <li>Open your <strong>GCash app</strong></li>
                                <li>Tap <strong>\"Send Money\"</strong></li>
                                <li>Select <strong>\"Express Send\"</strong></li>
                                <li>Enter mobile number: <strong>$gcash_number</strong></li>
                                <li>Enter amount: <strong>₱$downpayment</strong></li>
                                <li>Add message: <strong>\"Booking #$booking_id - $celebrant\"</strong></li>
                                <li>Review and tap <strong>\"Send\"</strong></li>
                                <li><strong>Screenshot</strong> your receipt</li>
                            </ol>
                            
                            <div style='background: #dbeafe; border-radius: 8px; padding: 15px; margin-top: 20px;'>
                                <p style='margin: 0; color: #1e40af; font-size: 13px;'>
                                    💡 <strong>Tip:</strong> Include your Booking ID in the message for faster verification!
                                </p>
                            </div>
                        </div>
                        
                        <!-- After Payment -->
                        <div style='background: #f0fdf4; border: 1px solid #22c55e; border-radius: 12px; padding: 20px; margin: 25px 0;'>
                            <h3 style='color: #166534; margin: 0 0 15px 0; font-size: 16px;'>✅ After Payment</h3>
                            <ol style='margin: 0; padding-left: 20px; color: #166534; font-size: 14px; line-height: 1.8;'>
                                <li>Take a <strong>screenshot</strong> of your GCash receipt</li>
                                <li>Send the screenshot to our Facebook page or email</li>
                                <li>Wait for <strong>payment confirmation</strong> (usually within 1-2 hours)</li>
                                <li>You'll receive a confirmation email once verified</li>
                            </ol>
                        </div>
                        
                        <!-- Contact Box -->
                        <div style='background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 25px 0; text-align: center;'>
                            <h4 style='color: #374151; margin: 0 0 15px 0;'>Need Help?</h4>
                            <p style='margin: 0 0 10px 0; font-size: 14px; color: #666;'>
                                📧 Email: <a href='mailto:zafskitchen95@gmail.com' style='color: #DC2626;'>zafskitchen95@gmail.com</a>
                            </p>
                            <p style='margin: 0; font-size: 14px; color: #666;'>
                                📱 Contact: 0917-XXX-XXXX
                            </p>
                        </div>
                        
                        <p style='margin-top: 30px; text-align: center; color: #666; font-size: 14px;'>
                            Thank you for choosing <strong style='color: #DC2626;'>Zaf's Kitchen</strong>!<br>
                            We look forward to making your event special. 🎉
                        </p>
                        
                    </div>
                    
                    <!-- Footer -->
                    <div style='background: #1f2937; color: #9ca3af; padding: 20px; text-align: center; border-radius: 0 0 12px 12px; font-size: 12px;'>
                        <p style='margin: 0 0 10px 0;'>© $current_year Zaf's Kitchen Catering Services</p>
                        <p style='margin: 0;'>This is an automated message. Please do not reply directly to this email.</p>
                    </div>
                    
                </div>
            </body>
            </html>
        "
    ];

    $result = sendBrevoEmail($data);
    
    if ($result) {
        error_log("✅ Booking approval email sent to $email for booking #$booking_id");
    } else {
        error_log("❌ Failed to send booking approval email to $email");
    }
    
    return $result;
}

// ✅ Helper function to send emails via Brevo API
function sendBrevoEmail($data) {
    $apiKey = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    
    if (!$apiKey) {
        error_log("❌ BREVO_API_KEY not set");
        return false;
    }

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
        return true;
    } else {
        error_log("❌ Brevo API Error - HTTP Code: $httpCode");
        error_log("❌ Response: " . $result);
        if ($error) {
            error_log("❌ cURL Error: " . $error);
        }
        return false;
    }
}

function generateOTP($length = 6) {
    return str_pad(random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}

function generateResetToken($length = 32) {
    return bin2hex(random_bytes($length));
}
?>