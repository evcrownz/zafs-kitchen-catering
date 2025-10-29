<?php
header('Content-Type: application/json');
http_response_code(200);

echo json_encode([
    'status' => 'healthy',
    'service' => 'zafs-kitchen',
    'php_version' => phpversion(),
    'timestamp' => date('c'),
    'server_port' => $_SERVER['SERVER_PORT'] ?? 'unknown',
    'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'unknown'
]);
