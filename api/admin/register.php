<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/session.php';

corsHeaders();

$pdo = getDB();
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM admin_users");
$adminCount = (int)$stmt->fetch()['cnt'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse(['registrationOpen' => $adminCount === 0]);
}

requireMethod('POST');

if ($adminCount > 0) {
    jsonError('Регистрация закрыта. Администратор уже существует.', 403);
}

$input = getJsonInput();
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password) || strlen($password) < 6) {
    jsonError('Email и пароль (минимум 6 символов) обязательны');
}

$stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    jsonError('Пользователь уже существует', 409);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT INTO admin_users (email, password_hash) VALUES (?, ?)");
$stmt->execute([$email, $hash]);

startSecureSession();
$_SESSION['admin_id'] = $pdo->lastInsertId();
$_SESSION['admin_email'] = $email;
$_SESSION['last_activity'] = time();

$csrfToken = generateCSRFToken();

jsonResponse([
    'email' => $email,
    'csrfToken' => $csrfToken,
]);
