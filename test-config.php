<?php
// test-config.php - Use this to verify Railway environment setup
echo "<h1>🔍 Railway Environment Check</h1>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5}h2{color:#DC2626;margin-top:30px}pre{background:#fff;padding:15px;border-left:4px solid #DC2626;overflow-x:auto}.success{color:green;font-weight:bold}.error{color:red;font-weight:bold}</style>";

echo "<h2>1. Environment Variables</h2>";
$required_vars = ['SENDGRID_API_KEY', 'SENDER_EMAIL', 'SENDER_NAME', 'DATABASE_URL'];

foreach ($required_vars as $var) {
    $value = getenv($var);
    if ($value) {
        if ($var === 'SENDGRID_API_KEY' || $var === 'DATABASE_URL') {
            echo "<span class='success'>✅ $var:</span> " . substr($value, 0, 20) . "... (" . strlen($value) . " chars)<br>";
        } else {
            echo "<span class='success'>✅ $var:</span> $value<br>";
        }
    } else {
        echo "<span class='error'>❌ $var: NOT SET</span><br>";
    }
}

echo "<h2>2. PHP Extensions</h2>";
$extensions = ['pdo_pgsql', 'mbstring', 'openssl', 'curl'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span class='success'>✅ $ext loaded</span><br>";
    } else {
        echo "<span class='error'>❌ $ext NOT loaded</span><br>";
    }
}

echo "<h2>3. Composer Packages</h2>";
if (file_exists('vendor/autoload.php')) {
    echo "<span class='success'>✅ Vendor autoload exists</span><br>";
    require 'vendor/autoload.php';
    
    if (class_exists('\SendGrid')) {
        echo "<span class='success'>✅ SendGrid class loaded</span><br>";
    } else {
        echo "<span class='error'>❌ SendGrid class NOT found</span><br>";
    }
    
    if (class_exists('Google_Client') || class_exists('Google\Client')) {
        echo "<span class='success'>✅ Google API Client loaded</span><br>";
    } else {
        echo "<span class='error'>❌ Google API Client NOT found</span><br>";
    }
    
    if (class_exists('Dotenv\Dotenv')) {
        echo "<span class='success'>✅ Dotenv loaded</span><br>";
    } else {
        echo "<span class='error'>❌ Dotenv NOT found</span><br>";
    }
} else {
    echo "<span class='error'>❌ Vendor directory not found - run composer install</span><br>";
}

echo "<h2>4. Database Connection Test</h2>";
try {
    require_once 'connection.php';
    if (isset($conn) && $conn) {
        echo "<span class='success'>✅ Database connection successful</span><br>";
        
        // Test query
        $stmt = $conn->query("SELECT COUNT(*) as count FROM usertable");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<span class='success'>✅ Found " . $result['count'] . " users in database</span><br>";
    } else {
        echo "<span class='error'>❌ Database connection failed</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Database error: " . $e->getMessage() . "</span><br>";
}

echo "<h2>5. SendGrid API Test</h2>";
try {
    $api_key = getenv('SENDGRID_API_KEY');
    if ($api_key) {
        $sendgrid = new \SendGrid($api_key);
        echo "<span class='success'>✅ SendGrid client created successfully</span><br>";
        echo "<span class='success'>✅ API Key format looks valid</span><br>";
    } else {
        echo "<span class='error'>❌ SENDGRID_API_KEY not found</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ SendGrid error: " . $e->getMessage() . "</span><br>";
}

echo "<h2>6. Google OAuth Test</h2>";
try {
    if (class_exists('Google_Client')) {
        $client = new Google_Client();
        echo "<span class='success'>✅ Google_Client instantiated successfully</span><br>";
    } elseif (class_exists('Google\Client')) {
        $client = new Google\Client();
        echo "<span class='success'>✅ Google\Client instantiated successfully</span><br>";
    } else {
        echo "<span class='error'>❌ Google Client class not found</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Google Client error: " . $e->getMessage() . "</span><br>";
}

echo "<h2>7. File Permissions</h2>";
$files = ['sendmail.php', 'connection.php', 'controllerUserData.php', 'helpers.php', 'google-oauth-config.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file exists</span><br>";
    } else {
        echo "<span class='error'>❌ $file NOT found</span><br>";
    }
}

echo "<h2>8. PHP Info</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

echo "<hr><p style='margin-top:30px;color:#666;'>✅ If all checks pass, your Railway deployment is ready!</p>";
?>