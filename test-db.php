<?php
// Display PHP info to check loaded extensions
echo "Loaded PDO drivers: ";
print_r(PDO::getAvailableDrivers());
echo "\n\n";

echo "DATABASE_URL: " . (getenv('DATABASE_URL') ? 'Set (hidden for security)' : 'NOT SET');
?>git add Dockerfile connection.php