<?php
require_once __DIR__ . '/../config.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $isProduction = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 1800,
        'path' => '/',
        'secure' => $isProduction,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

function isAuthenticated(): bool {
    startSecureSession();
    return !empty($_SESSION['admin_id']);
}

function requireAdmin(): void {
    if (!isAuthenticated()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Not authenticated']);
        exit;
    }
}

function generateCSRFToken(): string {
    startSecureSession();
    if (!empty($_SESSION['csrf_token'])) {
        return $_SESSION['csrf_token'];
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function validateCSRFToken(): void {
    startSecureSession();
    $headers = getallheaders();
    $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Invalid CSRF token']);
        exit;
    }
}
