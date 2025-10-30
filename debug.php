<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Info</h1>";

echo "<h2>1. PHP Version</h2>";
echo phpversion() . "<br><br>";

echo "<h2>2. Environment Variables</h2>";
echo "DATABASE_URL: " . (getenv('DATABASE_URL') ? "✅ Set" : "❌ Missing") . "<br>";
echo "SENDGRID_API_KEY: " . (getenv('SENDGRID_API_KEY') ? "✅ Set" : "❌ Missing") . "<br>";
echo "SENDER_EMAIL: " . (getenv('SENDER_EMAIL') ?: "❌ Missing") . "<br>";
echo "SENDER_NAME: " . (getenv('SENDER_NAME') ?: "❌ Missing") . "<br><br>";

echo "<h2>3. Files Check</h2>";
$files = ['connection.php', 'sendmail.php', 'controllerUserData.php', 'google-oauth-config.php'];
foreach ($files as $file) {
    echo "$file: " . (file_exists($file) ? "✅ Exists" : "❌ Missing") . "<br>";
}

echo "<br><h2>4. Composer Autoload</h2>";
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
    echo "✅ Autoload loaded<br>";
    echo "PHPMailer: " . (class_exists('PHPMailer\PHPMailer\PHPMailer') ? "✅" : "❌") . "<br>";
    echo "Dotenv: " . (class_exists('Dotenv\Dotenv') ? "✅" : "❌") . "<br>";
    echo "Google_Client: " . (class_exists('Google_Client') ? "✅" : "❌") . "<br>";
} else {
    echo "❌ vendor/autoload.php missing!<br>";
}

echo "<br><h2>5. Test Database Connection</h2>";
try {
    require_once 'connection.php';
    echo "✅ Database connected!<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><h2>6. Test SendMail</h2>";
try {
    require_once 'sendmail.php';
    echo "✅ sendmail.php loaded!<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><h2>7. Test controllerUserData.php</h2>";
try {
    require_once 'controllerUserData.php';
    echo "✅ controllerUserData.php loaded!<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><h2>8. Test auth.php</h2>";
echo "<a href='auth.php'>Click here to test auth.php</a>";
?>