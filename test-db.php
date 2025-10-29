<?php
echo "<h3>🔍 Database Connection Test</h3>";

$DATABASE_URL = getenv("DATABASE_URL");

if (!$DATABASE_URL) {
    die("❌ DATABASE_URL is NOT set in environment");
}

// Convert postgres:// to pgsql:// for PDO
if (strpos($DATABASE_URL, "postgres://") === 0) {
    $DATABASE_URL = str_replace("postgres://", "pgsql://", $DATABASE_URL);
}

try {
    $conn = new PDO($DATABASE_URL);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ SUCCESS: Connected to PostgreSQL Database!<br>";

    // Optional test query
    $result = $conn->query("SELECT version()")->fetch();
    echo "📌 PostgreSQL Version: " . $result['version'];
    
} catch (PDOException $e) {
    echo "❌ Connection Failed: " . $e->getMessage();
    echo "<br>🔎 DSN Used: " . $DATABASE_URL;
}
