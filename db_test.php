<?php
require 'config.php';

try {
    $stmt = $pdo->query("SHOW DATABASES;");
    echo "✅ Connected successfully!<br>";
    echo "Available Databases:<br>";
    while ($row = $stmt->fetch()) {
        echo "- " . htmlspecialchars($row['Database']) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}
?>
