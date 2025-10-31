<?php
// test_sendmail.php
echo "Testing sendmail.php...<br><br>";

// Step 1: Load vendor
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
    echo "✅ Autoload loaded<br>";
} else {
    die("❌ vendor/autoload.php NOT FOUND");
}

// Step 2: Load helpers
if (file_exists(__DIR__ . '/helpers.php')) {
    require __DIR__ . '/helpers.php';
    echo "✅ helpers.php loaded<br>";
} else {
    die("❌ helpers.php NOT FOUND");
}

// Step 3: Load sendmail
if (file_exists(__DIR__ . '/sendmail.php')) {
    echo "✅ sendmail.php EXISTS<br>";
    
    try {
        require __DIR__ . '/sendmail.php';
        echo "✅ sendmail.php LOADED<br>";
        
        if (function_exists('sendOTPEmail')) {
            echo "✅ sendOTPEmail() function AVAILABLE<br>";
        } else {
            echo "❌ sendOTPEmail() function NOT FOUND<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error loading sendmail.php: " . $e->getMessage() . "<br>";
        echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "❌ sendmail.php NOT FOUND<br>";
}
?>