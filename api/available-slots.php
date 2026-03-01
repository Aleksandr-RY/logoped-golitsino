<?php
date_default_timezone_set('Europe/Moscow');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

corsHeaders();
requireMethod('GET');

$date = $_GET['date'] ?? null;
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonError('Параметр date обязателен (YYYY-MM-DD)');
}

$dateObj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    jsonError('Неверный формат даты');
}

$today = new DateTime('today');
if ($dateObj < $today) {
    jsonResponse([]);
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM schedule_overrides WHERE start_date <= ? AND end_date >= ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$date, $date]);
$override = $stmt->fetch();

if ($override) {
    if (!$override['is_working_day']) {
        jsonResponse([]);
    }
    $startTime = $override['start_time'];
    $endTime = $override['end_time'];
    $slotDuration = (int)$override['slot_duration_minutes'];
} else {
    $stmt = $pdo->prepare("SELECT blocked_date FROM blocked_dates WHERE blocked_date = ?");
    $stmt->execute([$date]);
    if ($stmt->fetch()) {
        jsonResponse([]);
    }

    $dow = (int)$dateObj->format('w');
    $stmt = $pdo->prepare("SELECT * FROM work_schedule WHERE day_of_week = ?");
    $stmt->execute([$dow]);
    $schedule = $stmt->fetch();

    if (!$schedule || !$schedule['is_working_day']) {
        jsonResponse([]);
    }

    $startTime = $schedule['start_time'];
    $endTime = $schedule['end_time'];
    $slotDuration = (int)($schedule['slot_duration_minutes'] ?? 45);
}

$startParts = explode(':', $startTime);
$endParts = explode(':', $endTime);
$startMin = (int)$startParts[0] * 60 + (int)$startParts[1];
$endMin = (int)$endParts[0] * 60 + (int)$endParts[1];

$stmt = $pdo->prepare("
    SELECT preferred_time FROM bookings
    WHERE preferred_date = ? AND status != 'rejected'
");
$stmt->execute([$date]);
$bookedTimes = [];
while ($row = $stmt->fetch()) {
    $bookedTimes[$row['preferred_time']] = true;
}

$now = new DateTime();
$isToday = $dateObj->format('Y-m-d') === $now->format('Y-m-d');
$currentMin = $isToday ? ((int)$now->format('H') * 60 + (int)$now->format('i')) : 0;

$slots = [];
for ($m = $startMin; $m + $slotDuration <= $endMin; $m += $slotDuration) {
    if ($isToday && $m <= $currentMin) continue;
    $timeStr = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    if (isset($bookedTimes[$timeStr])) continue;
    $slots[] = $timeStr;
}

jsonResponse($slots);
