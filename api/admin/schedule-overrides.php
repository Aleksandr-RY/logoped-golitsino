<?php
date_default_timezone_set('Europe/Moscow');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/session.php';

corsHeaders();
requireAdmin();

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM schedule_overrides ORDER BY start_date ASC");
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'id' => (int)$row['id'],
            'startDate' => $row['start_date'],
            'endDate' => $row['end_date'],
            'startTime' => $row['start_time'],
            'endTime' => $row['end_time'],
            'slotDurationMinutes' => (int)$row['slot_duration_minutes'],
            'isWorkingDay' => (bool)$row['is_working_day'],
            'createdAt' => $row['created_at'],
        ];
    }
    jsonResponse($result);
} elseif ($method === 'POST') {
    validateCSRFToken();
    $input = getJsonInput();
    $startDate = $input['startDate'] ?? null;
    $endDate = $input['endDate'] ?? null;
    $startTime = $input['startTime'] ?? '09:00';
    $endTime = $input['endTime'] ?? '18:00';
    $slotDuration = (int)($input['slotDurationMinutes'] ?? 45);
    $isWorkingDay = isset($input['isWorkingDay']) ? (bool)$input['isWorkingDay'] : true;

    $startDateObj = $startDate ? DateTime::createFromFormat('Y-m-d', $startDate) : null;
    if (!$startDateObj || $startDateObj->format('Y-m-d') !== $startDate) {
        jsonError('Неверный формат даты начала');
    }

    $endDateObj = $endDate ? DateTime::createFromFormat('Y-m-d', $endDate) : null;
    if (!$endDateObj || $endDateObj->format('Y-m-d') !== $endDate) {
        jsonError('Неверный формат даты конца');
    }

    if ($startDateObj > $endDateObj) {
        jsonError('Дата начала не может быть позже даты конца');
    }

    $diff = $startDateObj->diff($endDateObj)->days;
    if ($diff > 60) {
        jsonError('Максимальный диапазон периода — 60 дней');
    }

    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
        jsonError('Неверный формат времени начала (HH:MM)');
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $endTime)) {
        jsonError('Неверный формат времени конца (HH:MM)');
    }
    if ($startTime >= $endTime) {
        jsonError('Время начала должно быть раньше времени конца');
    }

    if ($slotDuration < 10 || $slotDuration > 240) {
        jsonError('Длительность слота от 10 до 240 минут');
    }

    $stmt = $pdo->prepare("
        SELECT id FROM schedule_overrides
        WHERE NOT (end_date < ? OR start_date > ?)
        LIMIT 1
    ");
    $stmt->execute([$startDate, $endDate]);
    if ($stmt->fetch()) {
        jsonError('Период пересекается с существующим переопределением');
    }

    $stmt = $pdo->prepare("
        INSERT INTO schedule_overrides (start_date, end_date, start_time, end_time, slot_duration_minutes, is_working_day)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$startDate, $endDate, $startTime, $endTime, $slotDuration, $isWorkingDay ? 1 : 0]);
    jsonResponse([
        'id' => (int)$pdo->lastInsertId(),
        'startDate' => $startDate,
        'endDate' => $endDate,
        'startTime' => $startTime,
        'endTime' => $endTime,
        'slotDurationMinutes' => $slotDuration,
        'isWorkingDay' => $isWorkingDay,
    ]);
} elseif ($method === 'DELETE') {
    validateCSRFToken();
    $id = $_GET['id'] ?? null;
    if (!$id || !ctype_digit((string)$id)) {
        jsonError('ID обязателен и должен быть числом');
    }
    $stmt = $pdo->prepare("DELETE FROM schedule_overrides WHERE id = ?");
    $stmt->execute([(int)$id]);
    jsonResponse(['success' => true]);
} else {
    jsonError('Метод не поддерживается', 405);
}
