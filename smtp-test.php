<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Test - Zaf's Kitchen</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #DC2626;
            margin-bottom: 30px;
            text-align: center;
        }
        .section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #DC2626;
        }
        .section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        table td:first-child {
            font-weight: bold;
            width: 200px;
            color: #555;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
            margin: 20px 0;
        }
        .status-good {
            color: green;
            font-weight: bold;
        }
        .status-bad {
            color: red;
            font-weight: bold;
        }
        .btn {
            background: #DC2626;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin-top: 20px;
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
        }
        .btn:hover {
            background: #B91C1C;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f8;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.5;
        }
        .debug-output {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 SMTP Connection Test</h1>
        
        <!-- Environment Variables Check -->
        <div class="section">
            <h2>📋 Environment Variables</h2>
            <table>
                <?php
                $env_vars = [
                    'BREVO_HOST' => getenv('BREVO_HOST'),
                    'BREVO_PORT' => getenv('BREVO_PORT'),
                    'BREVO_USER' => getenv('BREVO_USER'),
                    'BREVO_PASS' => getenv('BREVO_PASS'),
                    'BREVO_FROM' => getenv('BREVO_FROM'),
                    'BREVO_NAME' => getenv('BREVO_NAME')
                ];
                
                $all_vars_set = true;
                foreach ($env_vars as $key => $value) {
                    $status = $value ? "✅ Set" : "❌ Missing";
                    $status_class = $value ? "status-good" : "status-bad";
                    $display_value = $value;
                    
                    if ($key == 'BREVO_PASS' && $value) {
                        $display_value = substr($value, 0, 20) . '...';
                    }
                    
                    if (!$value) $all_vars_set = false;
                    
                    echo "<tr>";
                    echo "<td>$key</td>";
                    echo "<td class='$status_class'>$status " . htmlspecialchars($display_value) . "</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </div>

        <?php if (!$all_vars_set): ?>
            <div class="error">
                <strong>⚠️ Error:</strong> Some environment variables are missing!<br>
                Please set all required variables in Railway and redeploy.
            </div>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn">🔄 Refresh Page</a>
        <?php else: ?>
            
            <!-- Test Email Form -->
            <?php if (!isset($_POST['send_test'])): ?>
                <div class="section">
                    <h2>📧 Send Test Email</h2>
                    <p style="margin-bottom: 15px;">Click the button below to send a test email to <strong><?php echo htmlspecialchars(getenv('BREVO_FROM')); ?></strong></p>
                    <form method="POST">
                        <button type="submit" name="send_test" class="btn">📤 Send Test Email</button>
                    </form>
                </div>
            <?php else: ?>
                
                <!-- Send Test Email -->
                <div class="section">
                    <h2>📤 Sending Test Email...</h2>
                    
                    <?php
                    $mail = new PHPMailer(true);
                    
                    // Capture debug output
                    ob_start();
                    
                    try {
                        // SMTP configuration
                        $mail->SMTPDebug = 2;
                        $mail->Debugoutput = 'html';
                        $mail->isSMTP();
                        $mail->Host = getenv('BREVO_HOST');
                        $mail->SMTPAuth = true;
                        $mail->Username = getenv('BREVO_USER');
                        $mail->Password = getenv('BREVO_PASS');
                        $mail->SMTPSecure = 'tls';
                        $mail->Port = getenv('BREVO_PORT');
                        $mail->Timeout = 30;

                        // Sender and recipient
                        $fromEmail = getenv('BREVO_FROM');
                        $fromName = getenv('BREVO_NAME');
                        
                        $mail->setFrom($fromEmail, $fromName);
                        $mail->addAddress($fromEmail, 'Test User');

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = '✅ SMTP Test Successful - Zaf\'s Kitchen';
                        $mail->Body = '
                            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                                <div style="background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); padding: 20px; text-align: center;">
                                    <h1 style="color: white; margin: 0;">🍽️ Zaf\'s Kitchen</h1>
                                </div>
                                <div style="padding: 30px; background: #f9f9f9;">
                                    <h2 style="color: #DC2626;">✅ SMTP Test Successful!</h2>
                                    <p>Congratulations! Your SMTP configuration is working correctly.</p>
                                    <p><strong>Test Details:</strong></p>
                                    <ul>
                                        <li>SMTP Host: ' . htmlspecialchars(getenv('BREVO_HOST')) . '</li>
                                        <li>SMTP Port: ' . htmlspecialchars(getenv('BREVO_PORT')) . '</li>
                                        <li>From Email: ' . htmlspecialchars($fromEmail) . '</li>
                                        <li>Sent At: ' . date('Y-m-d H:i:s') . ' (Manila Time)</li>
                                    </ul>
                                    <p style="color: #666; margin-top: 20px;">Your OTP emails should now work properly! 🎉</p>
                                </div>
                            </div>
                        ';
                        $mail->AltBody = 'SMTP Test Successful! Your email configuration is working.';

                        // Send
                        $mail->send();
                        
                        $debug_output = ob_get_clean();
                        
                        echo '<div class="success">';
                        echo '<strong>✅ SUCCESS!</strong> Test email sent successfully!<br><br>';
                        echo 'Check your inbox at: <strong>' . htmlspecialchars($fromEmail) . '</strong><br>';
                        echo '<small>Don\'t forget to check your spam folder!</small>';
                        echo '</div>';
                        
                        echo '<div class="section">';
                        echo '<h2>🔍 Debug Output</h2>';
                        echo '<div class="debug-output">';
                        echo '<pre>' . $debug_output . '</pre>';
                        echo '</div>';
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        $debug_output = ob_get_clean();
                        
                        echo '<div class="error">';
                        echo '<strong>❌ FAILED!</strong> Could not send test email.<br><br>';
                        echo '<strong>Error Message:</strong><br>';
                        echo htmlspecialchars($mail->ErrorInfo) . '<br><br>';
                        echo '<strong>Exception:</strong><br>';
                        echo htmlspecialchars($e->getMessage());
                        echo '</div>';
                        
                        echo '<div class="section">';
                        echo '<h2>🔍 Debug Output</h2>';
                        echo '<div class="debug-output">';
                        echo '<pre>' . $debug_output . '</pre>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div class="section">';
                        echo '<h2>💡 Troubleshooting Tips</h2>';
                        echo '<ul style="line-height: 2;">';
                        echo '<li>Verify BREVO_USER matches your Brevo SMTP login</li>';
                        echo '<li>Verify BREVO_PASS is your SMTP API key (not account password)</li>';
                        echo '<li>Ensure sender email is verified in Brevo</li>';
                        echo '<li>Check Railway has redeployed after setting variables</li>';
                        echo '<li>Wait 1-2 minutes and try again</li>';
                        echo '</ul>';
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