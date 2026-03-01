<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/session.php';

corsHeaders();
requireAdmin();

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    validateCSRFToken();

    $input = getJsonInput();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonError('Invalid ID', 400);

    $sets = [];
    $params = [];

    if (isset($input['startTime']) && preg_match('/^\d{2}:\d{2}$/', $input['startTime'])) {
        $sets[] = "start_time = ?";
        $params[] = $input['startTime'];
    }
    if (isset($input['endTime']) && preg_match('/^\d{2}:\d{2}$/', $input['endTime'])) {
        $sets[] = "end_time = ?";
        $params[] = $input['endTime'];
    }
    if (isset($input['isWorkingDay'])) {
        $sets[] = "is_working_day = ?";
        $params[] = $input['isWorkingDay'] ? 1 : 0;
    }
    if (isset($input['slotDurationMinutes'])) {
        $val = (int)$input['slotDurationMinutes'];
        if ($val >= 10 && $val <= 120) {
            $sets[] = "slot_duration_minutes = ?";
            $params[] = $val;
        }
    }

    if (empty($sets)) jsonError('No valid fields to update');

    $sql = "UPDATE work_schedule SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
    $params[] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $stmt = $pdo->prepare("SELECT * FROM work_schedule WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) jsonError('Not found', 404);

    jsonResponse([
        'id' => (int)$row['id'],
        'dayOfWeek' => (int)$row['day_of_week'],
        'startTime' => $row['start_time'],
        'endTime' => $row['end_time'],
        'isWorkingDay' => (bool)$row['is_working_day'],
        'slotDurationMinutes' => (int)$row['slot_duration_minutes'],
    ]);
}

jsonError('Method not allowed', 405);
