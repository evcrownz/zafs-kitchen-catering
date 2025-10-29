<?php
$DATABASE_URL = getenv('DATABASE_URL');

if (!$DATABASE_URL) {
    die("DATABASE_URL environment variable not set");
}

try {
    // Parse the DATABASE_URL if it's in postgres:// format
    if (strpos($DATABASE_URL, 'postgres://') === 0) {
        // Railway provides postgres:// URLs, convert to PDO format
        $DATABASE_URL = str_replace('postgres://', 'pgsql://', $DATABASE_URL);
    }
    
    $conn = new PDO($DATABASE_URL);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Show more detailed error for debugging
    die("Connection failed: " . $e->getMessage() . "\n\nDATABASE_URL format: " . 
        (strpos($DATABASE_URL, '://') ? substr($DATABASE_URL, 0, strpos($DATABASE_URL, '://')) . '://...' : 'invalid'));
}
?>