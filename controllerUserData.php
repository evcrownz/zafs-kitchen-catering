<?php 
date_default_timezone_set('Asia/Manila');

session_start();
require "connection.php";

// ✅ Load autoloader only once at the top
if (!class_exists('Resend\Resend')) {
    $vendorPath = __DIR__ . '/vendor/autoload.php';
    if (file_exists($vendorPath)) {
        require_once $vendorPath;
    } else {
        error_log("❌ Vendor autoloader not found at: $vendorPath");
    }
}

require_once __DIR__ . "/sendmail.php";
require_once "google-oauth-config.php";

$email = "";
$name = "";
$errors = [];

function generateResetToken($length = 32) {
    return bin2hex(random_bytes($length));
}

if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {
    header('Location: dashboard.php');
    exit();
}

// Google OAuth Callback
if(isset($_GET['code'])) {
    if(isset($_SESSION['user_id'])) {
        session_unset();
    }
    
    $result = handleGoogleCallback($_GET['code']);
    
    if($result['success']) {
        $email = $result['email'];
        $name = $result['name'];
        $google_id = $result['google_id'];
        $avatar_url = $result['avatar'];
        
        try {
            $check_user = "SELECT * FROM usertable WHERE email = :email OR google_id = :google_id";
            $stmt = $conn->prepare($check_user);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':google_id', $google_id);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(empty($user['google_id'])) {
                    $update = "UPDATE usertable SET google_id = :google_id, avatar_url = :avatar_url, oauth_provider = 'google' WHERE email = :email";
                    $update_stmt = $conn->prepare($update);
                    $update_stmt->bindParam(':google_id', $google_id);
                    $update_stmt->bindParam(':avatar_url', $avatar_url);
                    $update_stmt->bindParam(':email', $email);
                    $update_stmt->execute();
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['name'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $email;
                $_SESSION['avatar_url'] = $avatar_url;
                
                header('Location: dashboard.php');
                exit();
                
            } else {
                $status = 'verified';
                $oauth_provider = 'google';
                $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
                
                $insert = "INSERT INTO usertable (name, email, password, status, google_id, avatar_url, oauth_provider) 
                          VALUES (:name, :email, :password, :status, :google_id, :avatar_url, :oauth_provider)";
                $insert_stmt = $conn->prepare($insert);
                $insert_stmt->bindParam(':name', $name);
                $insert_stmt->bindParam(':email', $email);
                $insert_stmt->bindParam(':password', $random_password);
                $insert_stmt->bindParam(':status', $status);
                $insert_stmt->bindParam(':google_id', $google_id);
                $insert_stmt->bindParam(':avatar_url', $avatar_url);
                $insert_stmt->bindParam(':oauth_provider', $oauth_provider);
                
                if($insert_stmt->execute()) {
                    $user_id = $conn->lastInsertId();
                    
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $name;
                    $_SESSION['name'] = $name;
                    $_SESSION['email'] = $email;
                    $_SESSION['avatar_url'] = $avatar_url;
                    
                    header('Location: dashboard.php');
                    exit();
                }
            }
        } catch(PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $errors['google-error'] = 'Database error occurred.';
        }
    }
}

// Verify OTP
if(isset($_POST['check'])) {
    $email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
    
    if(empty($email)) {
        $errors['otp-error'] = 'Session expired. Please sign up again.';
    } else {
        $entered_otp = '';
        for ($i = 1; $i <= 6; $i++) {
            $entered_otp .= isset($_POST["otp$i"]) ? trim($_POST["otp$i"]) : '';
        }

        error_log("🔍 OTP Verification Attempt: Email=$email, Entered OTP=$entered_otp");

        try {
            $check_otp = "SELECT * FROM usertable WHERE email = :email AND code = :otp AND otp_expiry > NOW()";
            $stmt = $conn->prepare($check_otp);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':otp', $entered_otp);
            $stmt->execute();

            if($stmt->rowCount() > 0){
                error_log("✅ OTP Verified Successfully for $email");
                
                $update_status = "UPDATE usertable SET status = 'verified', code = NULL, otp_expiry = NULL WHERE email = :email";
                $update_stmt = $conn->prepare($update_status);
                $update_stmt->bindParam(':email', $email);
                
                if($update_stmt->execute()){
                    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    $_SESSION['name'] = $user_data['name'];
                    $_SESSION['email'] = $email;
                    unset($_SESSION['show_otp_modal']);

                    $_SESSION['verification_success'] = 'Email verified successfully! You can now sign in.';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    error_log("❌ Failed to update user status for $email");
                    $errors['otp-error'] = 'Failed to update account. Please try again.';
                    $_SESSION['show_otp_modal'] = true;
                }
            } else {
                error_log("❌ OTP Verification Failed: Invalid or expired OTP for $email");
                
                $check_expired = "SELECT * FROM usertable WHERE email = :email AND code = :otp";
                $expired_stmt = $conn->prepare($check_expired);
                $expired_stmt->bindParam(':email', $email);
                $expired_stmt->bindParam(':otp', $entered_otp);
                $expired_stmt->execute();

                if($expired_stmt->rowCount() > 0){
                    $errors['otp-error'] = 'OTP has expired. Please resend a new one.';
                } else {
                    $errors['otp-error'] = 'Invalid OTP. Please check and try again.';
                }
                $_SESSION['show_otp_modal'] = true;
            }
        } catch(PDOException $e) {
            error_log("❌ Database error during OTP verification: " . $e->getMessage());
            $errors['otp-error'] = 'Database error occurred.';
            $_SESSION['show_otp_modal'] = true;
        }
    }
}

// SIGNUP
if(isset($_POST['signup'])){
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $cpassword = $_POST['cpassword'];

    error_log("📝 Signup Attempt: Name=$name, Email=$email");

    if($password !== $cpassword){
        $errors['password'] = "Confirm password not matched!";
    }

    if(strlen($password) < 8){
        $errors['password_length'] = "Password must be at least 8 characters long!";
    }

    try {
        $email_check = "SELECT * FROM usertable WHERE email = :email";
        $stmt = $conn->prepare($email_check);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0){
            error_log("❌ Signup Failed: Email $email already exists");
            $errors['email'] = "Email already exists!";
        }

        if(count($errors) === 0){
            $encpass = password_hash($password, PASSWORD_BCRYPT);
            $otp = generateOTP();
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $status = "unverified";

            error_log("🔑 Generated OTP for $email: $otp (Expires: $otp_expiry)");

            $insert_data = "INSERT INTO usertable (name, email, password, status, code, otp_expiry)
                            VALUES (:name, :email, :password, :status, :code, :otp_expiry)";
            $insert_stmt = $conn->prepare($insert_data);
            $insert_stmt->bindParam(':name', $name);
            $insert_stmt->bindParam(':email', $email);
            $insert_stmt->bindParam(':password', $encpass);
            $insert_stmt->bindParam(':status', $status);
            $insert_stmt->bindParam(':code', $otp);
            $insert_stmt->bindParam(':otp_expiry', $otp_expiry);

            if($insert_stmt->execute()){
                error_log("✅ User created successfully: $email");
                error_log("📧 Attempting to send OTP email to $email...");
                
                $email_sent = sendOTPEmail($email, $otp, $name);
                
                if($email_sent) {
                    error_log("✅ OTP Email sent successfully to $email");
                    
                    $_SESSION['email'] = $email;
                    $_SESSION['name'] = $name;
                    $_SESSION['show_otp_modal'] = true;
                    $_SESSION['info'] = "OTP has been sent to your email address.";
                    
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    error_log("❌ CRITICAL: OTP Email failed to send to $email");
                    $errors['email'] = "Account created but failed to send OTP. Please contact support.";
                }
            } else {
                error_log("❌ Failed to insert user into database: $email");
                $errors['db-error'] = "Failed to create account!";
            }
        }
    } catch(PDOException $e) {
        error_log("❌ Database error during signup: " . $e->getMessage());
        $errors['db-error'] = "Database error occurred.";
    }
}

// RESEND OTP
if(isset($_POST['resend-otp']) || (isset($_POST['action']) && $_POST['action'] == 'resend-otp')){
    if(!isset($_SESSION['email'])){
        error_log("❌ Resend OTP Failed: No email in session");
        if(isset($_POST['action'])){
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session expired.']);
            exit();
        } else {
            $errors['otp-error'] = 'Session expired.';
        }
    } else {
        $email = $_SESSION['email'];
        $name = $_SESSION['name'];
        
        $new_otp = generateOTP();
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        error_log("🔄 Resending OTP to $email: $new_otp (Expires: $otp_expiry)");
        
        try {
            $update_otp = "UPDATE usertable SET code = :code, otp_expiry = :otp_expiry WHERE email = :email";
            $stmt = $conn->prepare($update_otp);
            $stmt->bindParam(':code', $new_otp);
            $stmt->bindParam(':otp_expiry', $otp_expiry);
            $stmt->bindParam(':email', $email);
            
            if($stmt->execute()){
                error_log("📧 Attempting to resend OTP email to $email...");
                $email_sent = sendOTPEmail($email, $new_otp, $name);
                
                if($email_sent) {
                    error_log("✅ Resend OTP Email successful for $email");
                    if(isset($_POST['action'])){
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'New OTP sent successfully']);
                        exit();
                    } else {
                        $_SESSION['info'] = 'New OTP sent successfully';
                        $_SESSION['show_otp_modal'] = true;
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit();
                    }
                } else {
                    error_log("❌ CRITICAL: Resend OTP Email failed for $email");
                    if(isset($_POST['action'])){
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Failed to send OTP']);
                        exit();
                    } else {
                        $errors['otp-error'] = 'Failed to send OTP';
                        $_SESSION['show_otp_modal'] = true;
                    }
                }
            }
        } catch(PDOException $e) {
            error_log("❌ Database error during resend OTP: " . $e->getMessage());
            if(isset($_POST['action'])){
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Database error']);
                exit();
            } else {
                $errors['otp-error'] = 'Database error';
                $_SESSION['show_otp_modal'] = true;
            }
        }
    }
}

// SIGNIN
if(isset($_POST['signin'])){    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if(filter_var($email, FILTER_VALIDATE_EMAIL)){
        try {
            $check_email = "SELECT * FROM usertable WHERE email = :email";
            $stmt = $conn->prepare($check_email);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if($stmt->rowCount() > 0){
                $fetch = $stmt->fetch(PDO::FETCH_ASSOC);
                $fetch_pass = $fetch['password'];
                
                if(!password_verify($password, $fetch_pass)){
                    $errors['email'] = "Incorrect email or password.";
                } else if($fetch['status'] == 'unverified'){
                    $new_otp = generateOTP();
                    $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    $update_otp = "UPDATE usertable SET code = :code, otp_expiry = :otp_expiry WHERE email = :email";
                    $update_stmt = $conn->prepare($update_otp);
                    $update_stmt->bindParam(':code', $new_otp);
                    $update_stmt->bindParam(':otp_expiry', $otp_expiry);
                    $update_stmt->bindParam(':email', $email);
                    
                    if($update_stmt->execute()){
                        $email_sent = sendOTPEmail($email, $new_otp, $fetch['name']);
                        
                        if($email_sent) {
                            $_SESSION['email'] = $email;
                            $_SESSION['name'] = $fetch['name'];
                            $_SESSION['show_otp_modal'] = true;
                            $_SESSION['info'] = "Please verify your email first. New OTP has been sent.";
                            
                            header('Location: ' . $_SERVER['PHP_SELF']);
                            exit();
                        } else {
                            $errors['email'] = "Failed to send OTP. Please try again later.";
                        }
                    } else {
                        $errors['email'] = "Database error. Please try again.";
                    }
                    
                } else if($fetch['status'] == 'verified'){
                    $_SESSION['user_id'] = $fetch['id'];
                    $_SESSION['username'] = $fetch['name'];
                    $_SESSION['name'] = $fetch['name'];
                    $_SESSION['email'] = $email;
                    $_SESSION['avatar_url'] = $fetch['avatar_url'] ?? '';
                    
                    header('Location: dashboard.php');
                    exit();
                }
            } else {
                $errors['email'] = "Email not registered. Please sign up first.";
            }
        } catch(PDOException $e) {
            $errors['email'] = "Database error occurred.";
        }
    } else {
        $errors['email'] = "Enter a valid email address!";
    }
}

// FORGOT PASSWORD
if(isset($_POST['forgot-password'])){
    $email = trim($_POST['email']);
    
    if(filter_var($email, FILTER_VALIDATE_EMAIL)){
        try {
            $check_email = "SELECT * FROM usertable WHERE email = :email AND status = 'verified'";
            $stmt = $conn->prepare($check_email);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if($stmt->rowCount() > 0){
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $reset_token = generateResetToken();
                $reset_expiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                
                $update_token = "UPDATE usertable SET reset_token = :token, reset_expiry = :expiry WHERE email = :email";
                $token_stmt = $conn->prepare($update_token);
                $token_stmt->bindParam(':token', $reset_token);
                $token_stmt->bindParam(':expiry', $reset_expiry);
                $token_stmt->bindParam(':email', $email);
                
                if($token_stmt->execute()){
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $reset_token;
                    
                    if(sendPasswordResetEmail($email, $reset_link, $user['name'])){
                        $_SESSION['forgot_success'] = "Password reset link sent to your email.";
                        $_SESSION['show_forgot_success'] = true;
                    } else {
                        $errors['forgot-error'] = "Failed to send email. Please try again.";
                    }
                } else {
                    $errors['forgot-error'] = "Failed to process request.";
                }
            } else {
                $_SESSION['forgot_success'] = "If this email exists, a reset link will be sent.";
                $_SESSION['show_forgot_success'] = true;
            }
        } catch(PDOException $e) {
            $errors['forgot-error'] = "Database error occurred.";
        }
    } else {
        $errors['forgot-error'] = "Enter a valid email address.";
    }
}

if(isset($_POST['login-now'])){
    header('Location: auth.php');
    exit();
}
?>