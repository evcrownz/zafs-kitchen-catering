<?php
$DATABASE_URL = getenv('DATABASE_URL');

if (!$DATABASE_URL) {
    die("DATABASE_URL environment variable not set");
}

try {
    $conn = new PDO($DATABASE_URL);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    echo "✅ Successfully connected to database";
} catch(PDOException $e) {

    echo "<pre>";
    echo "❌ Connection failed: " . $e->getMessage() . "\n\n";
    echo "🔍 DATABASE_URL Used:\n" . $DATABASE_URL . "\n";
    echo "📦 Raw ENV Value:\n" . getenv('DATABASE_URL') . "\n";
    echo "</pre>";
    exit;
}

?>