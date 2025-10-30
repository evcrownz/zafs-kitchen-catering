<?php
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey = getenv('SENDGRID_API_KEY');
$senderEmail = getenv('SENDER_EMAIL');

echo "API Key: " . ($apiKey ? "EXISTS (length: " . strlen($apiKey) . ")" : "MISSING") . "\n";
echo "Sender: " . ($senderEmail ?: "MISSING") . "\n";

// Test SendGrid directly
$email = new \SendGrid\Mail\Mail();
$email->setFrom($senderEmail, "Zaf's Kitchen");
$email->setSubject("Test Email");
$email->addTo("your-test-email@gmail.com", "Test User");
$email->addContent("text/plain", "This is a test");

$sendgrid = new \SendGrid($apiKey);

try {
    $response = $sendgrid->send($email);
    echo "Status Code: " . $response->statusCode() . "\n";
    echo "Body: " . $response->body() . "\n";
    echo "Headers: " . print_r($response->headers(), true) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}