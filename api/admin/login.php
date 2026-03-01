<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/session.php';

corsHeaders();
requireMethod('POST');

$input = getJsonInput();
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    jsonError('Email и пароль обязательны');
}

$pdo = getDB();
global $BRUTE_FORCE_MAX_ATTEMPTS, $BRUTE_FORCE_LOCKOUT_MINUTES;

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$stmt = $pdo->prepare("
    SELECT COUNT(*) as cnt FROM login_attempts
    WHERE ip_address = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
");
$stmt->execute([$ip]);
$attempts = (int)$stmt->fetch()['cnt'];

if ($attempts >= $BRUTE_FORCE_MAX_ATTEMPTS) {
    jsonError("Слишком много попыток. Попробуйте через {$BRUTE_FORCE_LOCKOUT_MINUTES} минут.", 429);
}

$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 0)");
    $stmt->execute([$email, $ip]);
    jsonError('Неверный email или пароль', 401);
}

$stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 1)");
$stmt->execute([$email, $ip]);

startSecureSession();
$_SESSION['admin_id'] = $user['id'];
$_SESSION['admin_email'] = $user['email'];
$_SESSION['last_activity'] = time();

$csrfToken = generateCSRFToken();

jsonResponse([
    'email' => $user['email'],
    'csrfToken' => $csrfToken,
]);
