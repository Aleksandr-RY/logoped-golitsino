<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

corsHeaders();
requireMethod('GET');

$pdo = getDB();

$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT preferred_date, preferred_time
    FROM bookings
    WHERE preferred_date IS NOT NULL
      AND preferred_time IS NOT NULL
      AND status != 'rejected'
      AND preferred_date >= ?
");
$stmt->execute([$today]);
$rows = $stmt->fetchAll();

$result = [];
foreach ($rows as $row) {
    $date = $row['preferred_date'];
    if (strlen($date) > 10) {
        $date = substr($date, 0, 10);
    }
    $result[] = [
        'preferredDate' => $date,
        'preferredTime' => $row['preferred_time'],
    ];
}

jsonResponse($result);
