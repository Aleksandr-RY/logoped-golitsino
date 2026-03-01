<?php
require_once __DIR__ . '/db.php';

$pdo = getDB();

$sql = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($sql);

echo json_encode(['message' => 'Database initialized successfully']);
