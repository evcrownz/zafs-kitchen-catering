<?php
// Start session
session_start();

// Destroy all session data
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
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
        // CRITICAL: Clear ALL browser storage IMMEDIATELY
        (function() {
            try {
                // Clear all storage
                localStorage.clear();
                sessionStorage.clear();
                
                // Clear specific keys as backup
                ['currentSection', 'bookingFormData', 'user_id', 'email', 'name'].forEach(key => {
                    localStorage.removeItem(key);
                    sessionStorage.removeItem(key);
                });
                
                console.log('All storage cleared');
            } catch (e) {
                console.error('Storage clear error:', e);
            }
        })();
        
        // Prevent back button immediately
        history.pushState(null, '', location.href);
        window.addEventListener('popstate', function() {
            history.pushState(null, '', location.href);
        });
        
        // Force redirect with location.replace (no history)
        setTimeout(function() {
            window.location.replace('auth.php?logout=1');
        }, 800);
    </script>
</body>
</html>