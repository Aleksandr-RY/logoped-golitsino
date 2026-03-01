<?php

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $status = 400): void {
    jsonResponse(['message' => $message], $status);
}

function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];
    return $data;
}

function requireMethod(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        jsonError('Method not allowed', 405);
    }
}

function corsHeaders(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function sanitizeString(?string $value): ?string {
    if ($value === null) return null;
    return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}

function sendTelegramNotification(string $message): bool {
    global $TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_ID;
    if (empty($TELEGRAM_BOT_TOKEN) || empty($TELEGRAM_CHAT_ID)) return false;

    $url = "https://api.telegram.org/bot{$TELEGRAM_BOT_TOKEN}/sendMessage";
    $data = [
        'chat_id' => $TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result !== false;
}

function sendEmailNotification(string $subject, string $body): bool {
    global $EMAIL_TO, $EMAIL_FROM;
    if (empty($EMAIL_TO)) return false;

    $headers = [
        "From: {$EMAIL_FROM}",
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
    ];

    return mail($EMAIL_TO, $subject, $body, implode("\r\n", $headers));
}
