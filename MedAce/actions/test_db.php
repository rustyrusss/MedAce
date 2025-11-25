<?php
$host = 'localhost';
$db   = 'medacedb';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $conn = new PDO($dsn, $user, $pass);
    echo "✅ Connected to database<br>";

    $stmt = $conn->query("SELECT * FROM users");
    $rows = $stmt->fetchAll();

    if ($rows) {
        foreach ($rows as $row) {
            echo $row['id'] . " - " . $row['email'] . "<br>";
        }
    } else {
        echo "⚠️ No users found in table.";
    }
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}
