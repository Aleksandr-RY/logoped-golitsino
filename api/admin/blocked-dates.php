<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/session.php';

corsHeaders();
requireAdmin();

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM blocked_dates ORDER BY blocked_date ASC");
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'id' => (int)$row['id'],
            'blockedDate' => $row['blocked_date'],
            'reason' => $row['reason'],
            'createdAt' => $row['created_at'],
        ];
    }
    jsonResponse($result);
} elseif ($method === 'POST') {
    validateCSRFToken();
    $input = getJsonInput();
    $blockedDate = $input['blockedDate'] ?? null;
    $reason = sanitizeString($input['reason'] ?? null);

    if (!$blockedDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $blockedDate)) {
        jsonError('Неверный формат даты');
    }

    $dateObj = new DateTime($blockedDate);
    $today = new DateTime('today');
    if ($dateObj < $today) {
        jsonError('Нельзя блокировать прошедшую дату');
    }

    $stmt = $pdo->prepare("SELECT id FROM blocked_dates WHERE blocked_date = ?");
    $stmt->execute([$blockedDate]);
    if ($stmt->fetch()) {
        jsonError('Эта дата уже заблокирована');
    }

    $stmt = $pdo->prepare("INSERT INTO blocked_dates (blocked_date, reason) VALUES (?, ?)");
    $stmt->execute([$blockedDate, $reason]);
    jsonResponse(['id' => (int)$pdo->lastInsertId(), 'blockedDate' => $blockedDate, 'reason' => $reason]);
} elseif ($method === 'DELETE') {
    validateCSRFToken();
    $id = $_GET['id'] ?? null;
    if (!$id) jsonError('ID обязателен');
    $stmt = $pdo->prepare("DELETE FROM blocked_dates WHERE id = ?");
    $stmt->execute([(int)$id]);
    jsonResponse(['success' => true]);
} else {
    jsonError('Метод не поддерживается', 405);
}
