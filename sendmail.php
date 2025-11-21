<?php
// ✅ LOAD ENVIRONMENT VARIABLES
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
}

function sendOTPEmail($email, $otp, $name) {
    $apiKey = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    
    if (!$apiKey) {
        error_log("❌ BREVO_API_KEY not set");
        return false;
    }

    $fromEmail = $_ENV['BREVO_FROM'] ?? getenv('BREVO_FROM') ?? 'crownicsjames@gmail.com';
    $fromName = $_ENV['BREVO_NAME'] ?? getenv('BREVO_NAME') ?? "Zaf's Kitchen";

    // Sanitize inputs
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
        error_log("❌ BREVO_API_KEY not set");
        return false;
    }

    $fromEmail = $_ENV['BREVO_FROM'] ?? getenv('BREVO_FROM') ?? 'crownicsjames@gmail.com';
    $fromName = $_ENV['BREVO_NAME'] ?? getenv('BREVO_NAME') ?? "Zaf's Kitchen";

    // Sanitize inputs
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
        'subject' => 'Password Reset - Zaf\'s Kitchen',
        'htmlContent' => "
            <!DOCTYPE html>
            <html lang='en'>
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
                    
                    <h2 style='color: #DC2626; margin-bottom: 20px;'>Password Reset Request</h2>
                    <p style='margin-bottom: 15px;'>Hello <strong>$safe_name</strong>,</p>
                    <p style='margin-bottom: 20px;'>We received a request to reset your password. Click the button below to create a new password:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$safe_link' style='background-color: #DC2626; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>Reset Password</a>
                    </div>
                    
                    <p style='margin-bottom: 20px;'>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #666; font-size: 14px; margin-bottom: 20px;'>$safe_link</p>
                    
                    <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                        <p style='margin: 0; color: #856404;'><strong>Important:</strong> This link will expire in <strong>30 minutes</strong> for security purposes.</p>
                    </div>
                    
                    <div style='background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                        <p style='margin: 0; color: #721c24;'><strong>Security Notice:</strong> If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
                    </div>
                    
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
        error_log("✅ Reset email sent successfully to $email via Brevo API");
        return true;
    } else {
        error_log("❌ Brevo API Error for reset email - HTTP Code: $httpCode");
        error_log("❌ Response: " . $result);
        if ($error) {
            error_log("❌ cURL Error: " . $error);
        }
        return false;
    }
}

// ✅ NEW FUNCTION: BOOKING APPROVAL EMAIL
function sendBookingApprovalEmail($booking) {
    error_log("=== 📧 STARTING BOOKING APPROVAL EMAIL PROCESS ===");
    
    $apiKey = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    
    if (!$apiKey) {
        error_log("❌ BREVO_API_KEY not set");
        return false;
    }

    $fromEmail = $_ENV['BREVO_FROM'] ?? getenv('BREVO_FROM') ?? 'crownicsjames@gmail.com';
    $fromName = $_ENV['BREVO_NAME'] ?? getenv('BREVO_NAME') ?? "Zaf's Kitchen";
    
    error_log("✅ API Key found: " . substr($apiKey, 0, 10) . "...");
    error_log("📤 From: $fromName <$fromEmail>");
    
    // EXTRACT BOOKING DETAILS
    $email = $booking['email'] ?? null;
    
    if (!$email) {
        error_log("❌ NO EMAIL FOUND IN BOOKING DATA!");
        return false;
    }
    
    error_log("📬 Recipient: $email");
    
    $name = htmlspecialchars($booking['name'] ?? $booking['full_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
    $booking_id = htmlspecialchars($booking['id'] ?? 'UNKNOWN', ENT_QUOTES, 'UTF-8');
    $celebrant = htmlspecialchars($booking['celebrant_name'] ?? 'Celebrant', ENT_QUOTES, 'UTF-8');
    $event_type = ucfirst(htmlspecialchars($booking['event_type'] ?? 'Event', ENT_QUOTES, 'UTF-8'));
    
    error_log("📋 Booking #$booking_id - $event_type for $celebrant");
    
    // FORMAT DATES AND PRICES
    $event_date = date('F d, Y (l)', strtotime($booking['event_date'] ?? '2025-01-01'));
    $start_time = date('g:i A', strtotime($booking['start_time'] ?? '14:00:00'));
    $end_time = date('g:i A', strtotime($booking['end_time'] ?? '18:00:00'));
    $guest_count = htmlspecialchars($booking['guest_count'] ?? '50', ENT_QUOTES, 'UTF-8');
    $package = ucfirst(str_replace('_', ' ', htmlspecialchars($booking['food_package'] ?? 'Standard', ENT_QUOTES, 'UTF-8')));
    $location = htmlspecialchars($booking['location'] ?? 'To be confirmed', ENT_QUOTES, 'UTF-8');
    $total_price = number_format($booking['total_price'] ?? 25000, 2);
    $downpayment = number_format(($booking['total_price'] ?? 25000) * 0.5, 2);
    
    $deadline = date('F d, Y - g:i A', strtotime('+20 hours'));
    $current_year = date('Y');
    
    // GCASH DETAILS - PALITAN MO ITO!
    $gcash_number = '0917-123-4567'; // ⚠️ PALITAN MO NG TOTOONG NUMBER!
    $gcash_name = 'Zaf\'s Kitchen'; // ⚠️ PALITAN MO NG TOTOONG NAME!

    error_log("💰 Total: ₱$total_price | Downpayment: ₱$downpayment");

    // CREATE EMAIL DATA
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
                                📱 Contact: $gcash_number
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

    // USE THE SAME BREVO API CALL AS OTP EMAILS
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
        error_log("✅ Booking approval email sent successfully to $email via Brevo API");
        return true;
    } else {
        error_log("❌ Brevo API Error for booking approval - HTTP Code: $httpCode");
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

// ✅ TERMINAL TEST
if (php_sapi_name() === 'cli') {
    echo "🎯 COMMAND LINE TEST MODE\n";
    echo "========================\n";
    
    $testBooking = [
        'id' => 'CLI_TEST_001',
        'email' => 'agbojames00@gmail.com',
        'name' => 'CLI Test User',
        'full_name' => 'CLI Test User',
        'celebrant_name' => 'CLI Celebrant',
        'event_type' => 'birthday', 
        'event_date' => '2025-01-20',
        'start_time' => '14:00:00',
        'end_time' => '18:00:00',
        'guest_count' => '50',
        'food_package' => 'silver',
        'location' => 'CLI Test Location',
        'total_price' => 25000.00
    ];
    
    echo "📧 Testing email to: " . $testBooking['email'] . "\n";
    
    $result = sendBookingApprovalEmail($testBooking);
    
    if ($result) {
        echo "✅ SUCCESS: Email sent successfully!\n";
        echo "📬 Check your Gmail inbox and spam folder.\n";
    } else {
        echo "❌ FAILED: Email not sent.\n";
        echo "🔍 Check error logs for details.\n";
    }
    
    echo "========================\n";
}
?>