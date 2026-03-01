<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;

    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Database connection failed']);
        exit;
    }

    return $pdo;
}
