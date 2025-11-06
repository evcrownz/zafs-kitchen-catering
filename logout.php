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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signing out...</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #DC2626, #991B1B);
            color: white;
            margin: 0;
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
        h2 {
            margin: 10px 0;
            font-size: 24px;
        }
        p {
            margin: 5px 0;
            opacity: 0.9;
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
        // CRITICAL: Clear ALL browser storage
        try {
            localStorage.clear();
            sessionStorage.clear();
            
            // Specifically remove known keys as backup
            localStorage.removeItem('currentSection');
            localStorage.removeItem('bookingFormData');
            
            console.log('All storage cleared successfully');
        } catch (e) {
            console.error('Storage clear error:', e);
        }
        
        // Redirect after ensuring storage is cleared
        setTimeout(function() {
            // Force reload to prevent cache
            window.location.replace('auth.php');
        }, 500);
        
        // Prevent back button from returning to dashboard
        window.history.pushState(null, '', window.location.href);
        window.addEventListener('popstate', function() {
            window.history.pushState(null, '', window.location.href);
        });
    </script>
</body>
</html>