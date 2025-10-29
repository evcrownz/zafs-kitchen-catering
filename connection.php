<?php
// Get DATABASE_URL from environment
$DATABASE_URL = getenv('DATABASE_URL');

if (!$DATABASE_URL) {
    error_log("❌ DATABASE_URL environment variable not set");
    die("Database configuration error. Please contact support.");
}

try {
    // Parse the DATABASE_URL
    $url = parse_url($DATABASE_URL);
    
    if (!$url) {
        throw new Exception("Invalid DATABASE_URL format");
    }
    
    // Extract components
    $host = $url['host'] ?? '';
    $port = $url['port'] ?? 5432;
    $dbname = ltrim($url['path'] ?? '', '/');
    $user = $url['user'] ?? '';
    $password = $url['pass'] ?? '';
    
    // Build proper PostgreSQL DSN
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    
    // Log connection attempt (to Railway logs, not browser)
    error_log("🔍 Connecting to PostgreSQL:");
    error_log("Host: $host");
    error_log("Port: $port");
    error_log("Database: $dbname");
    error_log("User: $user");
    
    // Check if pgsql driver is available
    $available_drivers = PDO::getAvailableDrivers();
    
    if (!in_array('pgsql', $available_drivers)) {
        error_log("❌ Available PDO Drivers: " . implode(', ', $available_drivers));
        throw new Exception("PostgreSQL PDO driver (pdo_pgsql) is not installed!");
    }
    
    // Create connection
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Log success (to Railway logs only)
    error_log("✅ Successfully connected to PostgreSQL database");
    
    // Optional: Test query (only log, don't output)
    $stmt = $conn->query("SELECT version()");
    $version = $stmt->fetch();
    error_log("PostgreSQL Version: " . $version['version']);
    
} catch(PDOException $e) {
    // Log error details
    error_log("❌ Database Connection Failed: " . $e->getMessage());
    error_log("DATABASE_URL: " . $DATABASE_URL);
    
    // Show user-friendly error
    die("Unable to connect to database. Please try again later.");
    
} catch(Exception $e) {
    error_log("❌ Configuration Error: " . $e->getMessage());
    die("Database configuration error. Please contact support.");
}
?>
