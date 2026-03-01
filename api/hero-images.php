<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

corsHeaders();
requireMethod('GET');

$pdo = getDB();

$stmt = $pdo->query("SELECT * FROM hero_images ORDER BY sort_order");
$rows = $stmt->fetchAll();

$result = [];
foreach ($rows as $row) {
    $result[] = [
        'id' => (int)$row['id'],
        'imageUrl' => $row['image_url'],
        'sortOrder' => (int)$row['sort_order'],
    ];
}

jsonResponse($result);
