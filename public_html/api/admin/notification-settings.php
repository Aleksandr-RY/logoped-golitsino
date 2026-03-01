<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/session.php';

corsHeaders();
requireAdmin();

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT * FROM notification_settings");
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'id' => (int)$row['id'],
            'provider' => $row['provider'],
            'enabled' => (bool)$row['enabled'],
            'config' => $row['config'],
        ];
    }

    jsonResponse($result);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    validateCSRFToken();

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonError('Invalid ID', 400);

    $input = getJsonInput();
    $sets = [];
    $params = [];

    if (isset($input['enabled'])) {
        $sets[] = "enabled = ?";
        $params[] = $input['enabled'] ? 1 : 0;
    }
    if (isset($input['config'])) {
        $sets[] = "config = ?";
        $params[] = is_string($input['config']) ? $input['config'] : json_encode($input['config']);
    }

    if (empty($sets)) jsonError('No valid fields to update');

    $sql = "UPDATE notification_settings SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
    $params[] = $id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $stmt = $pdo->prepare("SELECT * FROM notification_settings WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonError('Not found', 404);

    jsonResponse([
        'id' => (int)$row['id'],
        'provider' => $row['provider'],
        'enabled' => (bool)$row['enabled'],
        'config' => $row['config'],
    ]);
}

jsonError('Method not allowed', 405);
