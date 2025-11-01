<?php
// test_brevo_api.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brevo API Test - Zaf's Kitchen</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arial', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .container { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 800px; width: 100%; padding: 40px; }
        h1 { color: #DC2626; margin-bottom: 30px; text-align: center; }
        .section { background: #f9f9f9; padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #DC2626; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin: 20px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545; margin: 20px 0; }
        .btn { background: #DC2626; color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; margin-top: 20px; display: block; width: 100%; text-align: center; text-decoration: none; }
        .btn:hover { background: #B91C1C; }
        pre { background: #2d2d2d; color: #f8f8f8; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 12px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Brevo API Test</h1>
        
        <div class="section">
            <h2>📋 Environment Variables Check</h2>
            <?php
            $api_key = getenv('BREVO_API_KEY');
            $from_email = getenv('BREVO_FROM');
            $from_name = getenv('BREVO_NAME');
            
            echo "<p><strong>BREVO_API_KEY:</strong> " . ($api_key ? "✅ Set (" . substr($api_key, 0, 10) . "...)" : "❌ Missing") . "</p>";
            echo "<p><strong>BREVO_FROM:</strong> " . ($from_email ? "✅ " . htmlspecialchars($from_email) : "❌ Missing") . "</p>";
            echo "<p><strong>BREVO_NAME:</strong> " . ($from_name ? "✅ " . htmlspecialchars($from_name) : "❌ Missing") . "</p>";
            ?>
        </div>

        <?php if (!$api_key || !$from_email): ?>
            <div class="error">
                <strong>⚠️ Error:</strong> Required environment variables are missing!<br>
                Please set BREVO_API_KEY, BREVO_FROM, and BREVO_NAME in Railway.
            </div>
        <?php else: ?>
            
            <?php if (!isset($_POST['send_test'])): ?>
                <div class="section">
                    <h2>📧 Send Test Email via Brevo API</h2>
                    <p style="margin-bottom: 15px;">Click the button below to send a test email to <strong><?php echo htmlspecialchars($from_email); ?></strong></p>
                    <form method="POST">
                        <button type="submit" name="send_test" class="btn">🚀 Send Test Email via API</button>
                    </form>
                </div>
            <?php else: ?>
                
                <div class="section">
                    <h2>📤 Sending Test Email via Brevo API...</h2>
                    
                    <?php
                    $test_email = $from_email;
                    
                    $data = [
                        'sender' => [
                            'name' => $from_name,
                            'email' => $from_email
                        ],
                        'to' => [
                            [
                                'email' => $test_email,
                                'name' => 'Test User'
                            ]
                        ],
                        'subject' => '✅ Brevo API Test Successful - Zaf\'s Kitchen',
                        'htmlContent' => '
                            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                                <div style="background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); padding: 20px; text-align: center;">
                                    <h1 style="color: white; margin: 0;">🍽️ Zaf\'s Kitchen</h1>
                                </div>
                                <div style="padding: 30px; background: #f9f9f9;">
                                    <h2 style="color: #DC2626;">✅ Brevo API Test Successful!</h2>
                                    <p>Congratulations! Your Brevo API configuration is working correctly.</p>
                                    <p><strong>Test Details:</strong></p>
                                    <ul>
                                        <li>API Method: HTTP POST</li>
                                        <li>Endpoint: api.brevo.com/v3/smtp/email</li>
                                        <li>From Email: ' . htmlspecialchars($from_email) . '</li>
                                        <li>Sent At: ' . date('Y-m-d H:i:s') . ' (Manila Time)</li>
                                    </ul>
                                    <p style="color: #666; margin-top: 20px;">Your OTP emails should now work properly! 🎉</p>
                                </div>
                            </div>
                        '
                    ];

                    $ch = curl_init();
                    
                    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    
                    $headers = [
                        'accept: application/json',
                        'api-key: ' . $api_key,
                        'content-type: application/json'
                    ];
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    
                    $result = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    
                    curl_close($ch);

                    if ($httpCode === 201) {
                        echo '<div class="success">';
                        echo '<strong>✅ SUCCESS!</strong> Test email sent successfully via Brevo API!<br><br>';
                        echo 'Check your inbox at: <strong>' . htmlspecialchars($test_email) . '</strong><br>';
                        echo '<small>HTTP Status: ' . $httpCode . ' (Created)</small>';
                        echo '</div>';
                    } else {
                        echo '<div class="error">';
                        echo '<strong>❌ FAILED!</strong> Could not send test email.<br><br>';
                        echo '<strong>HTTP Status:</strong> ' . $httpCode . '<br>';
                        echo '<strong>API Response:</strong><br>';
                        echo '<pre>' . htmlspecialchars($result) . '</pre>';
                        if ($error) {
                            echo '<strong>cURL Error:</strong> ' . htmlspecialchars($error);
                        }
                        echo '</div>';
                    }
                    ?>
                    
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn">🔄 Test Again</a>
                </div>
                
            <?php endif; ?>
            
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px; color: #999; font-size: 12px;">
            Test run at: <?php echo date('Y-m-d H:i:s'); ?> (Manila Time)
        </div>
    </div>
</body>
</html>