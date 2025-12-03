<?php

// Server configuration
$db_host = 'localhost';
$db_name = 'fitnesshub';
$db_user = 'root';
$db_pass = 'root';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Always return clean JSON
    header("Content-Type: application/json");
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "error" => "Database connection failed",
        "details" => $e->getMessage()
    ]);

    exit;
}
?>
