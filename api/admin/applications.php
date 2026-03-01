<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/session.php';

corsHeaders();
requireAdmin();

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT * FROM bookings ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'id' => (int)$row['id'],
            'parentName' => $row['parent_name'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'childAge' => $row['child_age'],
            'problem' => $row['problem'],
            'preferredDate' => $row['preferred_date'],
            'preferredTime' => $row['preferred_time'],
            'comment' => $row['comment'],
            'adminComment' => $row['admin_comment'],
            'status' => $row['status'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    jsonResponse($result);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    validateCSRFToken();

    $input = getJsonInput();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonError('Invalid ID', 400);

    if (isset($input['status'])) {
        $validStatuses = ['new', 'in_progress', 'completed', 'rejected'];
        if (!in_array($input['status'], $validStatuses)) {
            jsonError('Invalid status', 400);
        }
        $stmt = $pdo->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$input['status'], $id]);
    }

    if (isset($input['adminComment'])) {
        $stmt = $pdo->prepare("UPDATE bookings SET admin_comment = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([sanitizeString($input['adminComment']), $id]);
    }

    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) jsonError('Not found', 404);

    jsonResponse([
        'id' => (int)$row['id'],
        'parentName' => $row['parent_name'],
        'phone' => $row['phone'],
        'email' => $row['email'],
        'childAge' => $row['child_age'],
        'problem' => $row['problem'],
        'preferredDate' => $row['preferred_date'],
        'preferredTime' => $row['preferred_time'],
        'comment' => $row['comment'],
        'adminComment' => $row['admin_comment'],
        'status' => $row['status'],
        'createdAt' => $row['created_at'],
        'updatedAt' => $row['updated_at'],
    ]);
}

jsonError('Method not allowed', 405);
