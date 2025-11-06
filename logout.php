<?php
session_start();

// Destroy all session data
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Add HTML with JavaScript to clear localStorage before redirect
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Signing out...</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #DC2626, #991B1B);
            color: white;
        }
        .logout-message {
            text-align: center;
        }
        .spinner {
            width: 50px;
            height: 50px;
            margin: 20px auto;
            border: 5px solid rgba(255,255,255,0.3);
            border-top: 5px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="logout-message">
        <div class="spinner"></div>
        <h2>Signing out...</h2>
        <p>Please wait</p>
    </div>
    
    <script>
        // Clear ALL localStorage items
        localStorage.clear();
        
        // Also specifically remove known keys as backup
        localStorage.removeItem('currentSection');
        localStorage.removeItem('bookingFormData');
        
        // Clear sessionStorage as well
        sessionStorage.clear();
        
        // Redirect after clearing
        setTimeout(function() {
            window.location.href = 'auth.php';
        }, 500);
    </script>
</body>
</html>