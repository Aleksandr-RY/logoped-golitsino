<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/session.php';

corsHeaders();
requireMethod('POST');

startSecureSession();
session_unset();
session_destroy();

jsonResponse(['ok' => true]);
