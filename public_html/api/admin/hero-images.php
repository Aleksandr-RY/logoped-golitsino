<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/session.php';

corsHeaders();
requireAdmin();

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();

    if (!empty($_FILES['image'])) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;

        if (!in_array($file['type'], $allowedTypes)) {
            jsonError('Допустимые форматы: JPG, PNG, WebP');
        }
        if ($file['size'] > $maxSize) {
            jsonError('Максимальный размер файла: 5 МБ');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'hero_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $uploadDir = __DIR__ . '/../uploads/hero/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $dest = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            jsonError('Ошибка загрузки файла');
        }

        $imageUrl = '/uploads/hero/' . $filename;
    } else {
        $input = getJsonInput();
        $imageUrl = $input['imageUrl'] ?? '';
        if (empty($imageUrl)) jsonError('Image URL or file is required');
    }

    $sortOrder = (int)($_POST['sortOrder'] ?? $input['sortOrder'] ?? 0);

    $stmt = $pdo->prepare("INSERT INTO hero_images (image_url, sort_order) VALUES (?, ?)");
    $stmt->execute([$imageUrl, $sortOrder]);

    jsonResponse([
        'id' => (int)$pdo->lastInsertId(),
        'imageUrl' => $imageUrl,
        'sortOrder' => $sortOrder,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    validateCSRFToken();

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonError('Invalid ID', 400);

    $stmt = $pdo->prepare("SELECT image_url FROM hero_images WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row && strpos($row['image_url'], '/uploads/') === 0) {
        $filePath = __DIR__ . '/..' . $row['image_url'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM hero_images WHERE id = ?");
    $stmt->execute([$id]);

    jsonResponse(['ok' => true]);
}

jsonError('Method not allowed', 405);
