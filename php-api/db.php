<?php
$host = getenv("DB_HOST") ?: "mysql";
$db = getenv("DB_NAME") ?: "miniscrum";
$user = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASSWORD") ?: "root";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Error de conexion a la base de datos",
        "details" => $e->getMessage()
    ]);
    exit;
}
