<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

corsHeaders();
requireMethod('GET');

$pdo = getDB();

$stmt = $pdo->query("SELECT * FROM work_schedule ORDER BY day_of_week");
$rows = $stmt->fetchAll();

$result = [];
foreach ($rows as $row) {
    $result[] = [
        'id' => (int)$row['id'],
        'dayOfWeek' => (int)$row['day_of_week'],
        'startTime' => $row['start_time'],
        'endTime' => $row['end_time'],
        'isWorkingDay' => (bool)$row['is_working_day'],
        'slotDurationMinutes' => (int)$row['slot_duration_minutes'],
    ];
}

jsonResponse($result);
