<?php
// Get DATABASE_URL from environment
$DATABASE_URL = getenv('DATABASE_URL');

if (!$DATABASE_URL) {
    die("❌ DATABASE_URL environment variable not set");
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
    
    // Debug info
    echo "<h3>🔍 Connection Debug Info:</h3>";
    echo "<pre>";
    echo "Host: $host\n";
    echo "Port: $port\n";
    echo "Database: $dbname\n";
    echo "User: $user\n";
    echo "DSN: $dsn\n\n";
    
    // Check if pgsql driver is available
    $available_drivers = PDO::getAvailableDrivers();
    echo "Available PDO Drivers: " . implode(', ', $available_drivers) . "\n\n";
    
    if (!in_array('pgsql', $available_drivers)) {
        throw new Exception("PostgreSQL PDO driver (pdo_pgsql) is not installed!");
    }
    
    echo "</pre>";
    
    // Create connection
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "<h3>✅ Successfully connected to PostgreSQL database!</h3>";
    
    // Test query
    $stmt = $conn->query("SELECT version()");
    $version = $stmt->fetch();
    echo "<p><strong>PostgreSQL Version:</strong> " . $version['version'] . "</p>";
    
} catch(PDOException $e) {
    echo "<h3>❌ Database Connection Failed</h3>";
    echo "<pre>";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "DATABASE_URL: " . $DATABASE_URL . "\n";
    echo "</pre>";
    exit;
} catch(Exception $e) {
    echo "<h3>❌ Configuration Error</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    exit;
}
?>
