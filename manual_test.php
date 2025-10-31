<?php
$api_key = getenv('RESEND_API_KEY');

if (!$api_key) {
    die("❌ RESEND_API_KEY not found");
}

$email_data = [
    'from' => 'Zaf\'s Kitchen <onboarding@resend.dev>',
    'to' => ['agbojames00@gmail.com'],
    'subject' => 'MANUAL TEST - Zaf\'s Kitchen',
    'html' => '<h1>Test Email</h1><p>If you receive this, email is working!</p>'
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.resend.com/emails',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($email_data),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2>Manual API Test</h2>";
echo "Status: $http_code<br>";
echo "Response: $response";
?>