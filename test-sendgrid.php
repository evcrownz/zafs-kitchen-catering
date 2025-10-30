<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "<h2>Testing SendGrid Configuration</h2>";

// Check if variables are loaded
echo "SENDGRID_API_KEY: " . (isset($_ENV['SENDGRID_API_KEY']) ? '✅ Loaded' : '❌ Missing') . "<br>";
echo "SENDER_EMAIL: " . (isset($_ENV['SENDER_EMAIL']) ? $_ENV['SENDER_EMAIL'] : '❌ Missing') . "<br>";
echo "SENDER_NAME: " . (isset($_ENV['SENDER_NAME']) ? $_ENV['SENDER_NAME'] : '❌ Missing') . "<br><br>";

if (!isset($_ENV['SENDGRID_API_KEY']) || !isset($_ENV['SENDER_EMAIL'])) {
    die("❌ Environment variables not loaded properly!");
}

// Try sending test email
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.sendgrid.net';
    $mail->SMTPAuth = true;
    $mail->Username = 'apikey';
    $mail->Password = $_ENV['SENDGRID_API_KEY'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->SMTPDebug = 2; // Enable verbose debug output

    $mail->setFrom($_ENV['SENDER_EMAIL'], $_ENV['SENDER_NAME']);
    $mail->addAddress('YOUR_TEST_EMAIL@gmail.com'); // Change this to your email
    
    $mail->isHTML(true);
    $mail->Subject = 'SendGrid Test';
    $mail->Body = 'This is a test email from SendGrid!';

    $mail->send();
    echo "<br><br>✅ Email sent successfully!";
} catch (Exception $e) {
    echo "<br><br>❌ Error: {$mail->ErrorInfo}";
}
?>