<?php
session_start();
require_once "connection.php";

$errors = [];
$token = isset($_GET['token']) ? $_GET['token'] : '';
$token_valid = false;
$user_email = '';
$user_name = '';

// Verify token
if (!empty($token)) {
    try {
        $check_token = "SELECT * FROM usertable WHERE reset_token = :token AND reset_expiry > NOW() AND status = 'verified'";
        $stmt = $conn->prepare($check_token);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $token_valid = true;
            $user_email = $user['email'];
            $user_name = $user['name'];
        } else {
            $errors['token'] = "Invalid or expired reset link. Please request a new one.";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $errors['token'] = "Database error occurred.";
    }
} else {
    $errors['token'] = "No reset token provided.";
}

// Process password reset
if (isset($_POST['reset-password']) && $token_valid) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 8) {
        $errors['password'] = "Password must be at least 8 characters long.";
    } else if ($new_password !== $confirm_password) {
        $errors['password'] = "Passwords do not match.";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            
            $update_password = "UPDATE usertable SET password = :password, reset_token = NULL, reset_expiry = NULL WHERE reset_token = :token";
            $update_stmt = $conn->prepare($update_password);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':token', $token);
            
            if ($update_stmt->execute()) {
                $_SESSION['reset_success'] = "Your password has been reset successfully. You can now sign in with your new password.";
                header('Location: auth.php');
                exit();
            } else {
                $errors['password'] = "Failed to reset password. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Database error during password reset: " . $e->getMessage());
            $errors['password'] = "Database error occurred.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Zaf's Kitchen</title>
    <link rel="icon" type="image/png" href="logo/logo.png">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: url("authbackground/authbackground.jpg");
            background-color: black;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
        }

        .reset-container {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-section img {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
        }

        .logo-section h1 {
            color: #DC2626;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .logo-section p {
            color: #666;
            font-size: 14px;
        }

        .error-box {
            background: linear-gradient(145deg, #f8d7da, #f5c6cb);
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
            font-size: 14px;
            animation: shake 0.3s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .success-box {
            background: linear-gradient(145deg, #d4edda, #c3e6cb);
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .input-group {
            margin-bottom: 25px;
            position: relative;
        }

        .input-group label {
            display: block;
            color: #333;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .input-group input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            background: #f5f5f5;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            color: #333;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: #DC2626;
            background: #fff;
            box-shadow: 0 0 10px rgba(220, 38, 38, 0.1);
        }

        .input-group i {
            position: absolute;
            right: 15px;
            top: 43px;
            transform: translateY(-50%);
            font-size: 20px;
            color: #888;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .input-group i:hover {
            color: #DC2626;
        }

        .password-strength {
            margin-top: 8px;
            font-size: 12px;
            color: #666;
        }

        .strength-bar {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0%;
            background: #dc3545;
            transition: all 0.3s ease;
        }

        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #28a745; width: 100%; }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(145deg, #DC2626, #B91C1C);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #DC2626;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #B91C1C;
            text-decoration: underline;
        }

        @media screen and (max-width: 480px) {
            .reset-container {
                padding: 30px 25px;
            }

            .logo-section h1 {
                font-size: 24px;
            }

            .logo-section img {
                width: 60px;
                height: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="logo-section">
            <img src="logo/logo.png" alt="Zaf's Kitchen Logo">
            <h1>Reset Password</h1>
            <p>Enter your new password below</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <i class='bx bx-error-circle'></i>
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($token_valid): ?>
            <form method="POST" action="" id="resetForm">
                <div class="input-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required minlength="8">
                    <i class='bx bx-show' id="togglePassword1" onclick="togglePassword('new_password', 'togglePassword1')"></i>
                </div>

                <div class="password-strength" id="strengthText" style="display: none;">
                    Password strength: <span id="strengthLabel">Weak</span>
                    <div class="strength-bar">
                        <div class="strength-bar-fill" id="strengthBar"></div>
                    </div>
                </div>

                <div class="input-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required minlength="8">
                    <i class='bx bx-show' id="togglePassword2" onclick="togglePassword('confirm_password', 'togglePassword2')"></i>
                </div>

                <button type="submit" name="reset-password" class="btn">Reset Password</button>
            </form>
        <?php else: ?>
            <div class="error-box">
                <p style="margin-bottom: 10px;">This reset link is invalid or has expired.</p>
                <p style="font-size: 13px;">Please request a new password reset link.</p>
            </div>
        <?php endif; ?>

        <div class="back-link">
            <a href="auth.php"><i class='bx bx-arrow-back'></i> Back to Login</a>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bx-show');
                icon.classList.add('bx-hide');
            } else {
                input.type = 'password';
                icon.classList.remove('bx-hide');
                icon.classList.add('bx-show');
            }
        }

        // Password strength checker
        const newPassword = document.getElementById('new_password');
        const strengthText = document.getElementById('strengthText');
        const strengthLabel = document.getElementById('strengthLabel');
        const strengthBar = document.getElementById('strengthBar');

        if (newPassword) {
            newPassword.addEventListener('input', function() {
                const password = this.value;
                
                if (password.length === 0) {
                    strengthText.style.display = 'none';
                    return;
                }
                
                strengthText.style.display = 'block';
                
                let strength = 0;
                if (password.length >= 8) strength++;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                if (password.match(/\d/)) strength++;
                if (password.match(/[^a-zA-Z\d]/)) strength++;
                
                strengthBar.className = 'strength-bar-fill';
                
                if (strength <= 2) {
                    strengthBar.classList.add('strength-weak');
                    strengthLabel.textContent = 'Weak';
                    strengthLabel.style.color = '#dc3545';
                } else if (strength === 3) {
                    strengthBar.classList.add('strength-medium');
                    strengthLabel.textContent = 'Medium';
                    strengthLabel.style.color = '#ffc107';
                } else {
                    strengthBar.classList.add('strength-strong');
                    strengthLabel.textContent = 'Strong';
                    strengthLabel.style.color = '#28a745';
                }
            });
        }

        // Form validation
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return;
                }
            });
        }
    </script>
</body>
</html>