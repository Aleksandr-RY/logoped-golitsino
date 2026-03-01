<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/session.php';

corsHeaders();
requireMethod('GET');

startSecureSession();
if (empty($_SESSION['admin_id'])) {
    jsonError('Not authenticated', 401);
}

$csrfToken = generateCSRFToken();

jsonResponse([
    'email' => $_SESSION['admin_email'],
    'csrfToken' => $csrfToken,
]);
