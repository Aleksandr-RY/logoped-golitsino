<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

corsHeaders();
requireMethod('POST');

$input = getJsonInput();

$parentName = sanitizeString($input['parentName'] ?? '');
$phone = sanitizeString($input['phone'] ?? '');
$email = sanitizeString($input['email'] ?? null);
$childAge = sanitizeString($input['childAge'] ?? null);
$problem = sanitizeString($input['problem'] ?? '');
$preferredDate = $input['preferredDate'] ?? null;
$preferredTime = $input['preferredTime'] ?? null;
$comment = sanitizeString($input['comment'] ?? null);

if (empty($parentName)) jsonError('Введите имя родителя');
if (empty($phone)) jsonError('Введите номер телефона');
if (empty($problem)) jsonError('Выберите тип обращения');

if ($preferredDate !== null) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredDate)) {
        jsonError('Неверный формат даты');
    }
    $dateObj = DateTime::createFromFormat('Y-m-d', $preferredDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $preferredDate) {
        jsonError('Неверная дата');
    }
    $today = new DateTime('today');
    if ($dateObj < $today) {
        jsonError('Нельзя записаться на прошедшую дату');
    }
}

if ($preferredTime !== null) {
    if (!preg_match('/^\d{2}:\d{2}$/', $preferredTime)) {
        jsonError('Неверный формат времени');
    }

    if ($preferredDate !== null) {
        $now = new DateTime();
        $slotDateTime = new DateTime("{$preferredDate} {$preferredTime}");
        if ($slotDateTime <= $now) {
            jsonError('Это время уже прошло');
        }
    }
}

$pdo = getDB();

if ($preferredDate !== null && $preferredTime !== null) {
    $dateObj = $dateObj ?? DateTime::createFromFormat('Y-m-d', $preferredDate);
    $dow = (int)$dateObj->format('w');
    $stmt = $pdo->prepare("SELECT start_time, end_time, is_working_day, slot_duration_minutes FROM work_schedule WHERE day_of_week = ?");
    $stmt->execute([$dow]);
    $daySchedule = $stmt->fetch();

    if (!$daySchedule || !$daySchedule['is_working_day']) {
        jsonError('Выбранный день не является рабочим');
    }

    $slotDuration = (int)($daySchedule['slot_duration_minutes'] ?? 45);
    $startParts = explode(':', $daySchedule['start_time']);
    $endParts = explode(':', $daySchedule['end_time']);
    $startMin = (int)$startParts[0] * 60 + (int)$startParts[1];
    $endMin = (int)$endParts[0] * 60 + (int)$endParts[1];

    $timeParts = explode(':', $preferredTime);
    $slotMin = (int)$timeParts[0] * 60 + (int)$timeParts[1];

    $validSlot = false;
    for ($m = $startMin; $m + $slotDuration <= $endMin; $m += $slotDuration) {
        if ($m === $slotMin) {
            $validSlot = true;
            break;
        }
    }
    if (!$validSlot) {
        jsonError('Выбранное время не соответствует доступным слотам');
    }

    $stmt = $pdo->prepare("
        SELECT id FROM bookings
        WHERE preferred_date = ? AND preferred_time = ? AND status != 'rejected'
        LIMIT 1
    ");
    $stmt->execute([$preferredDate, $preferredTime]);
    if ($stmt->fetch()) {
        jsonError('Это время уже занято, выберите другое', 409);
    }
}

$stmt = $pdo->prepare("
    INSERT INTO bookings (parent_name, phone, email, child_age, problem, preferred_date, preferred_time, comment, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new')
");
$stmt->execute([$parentName, $phone, $email, $childAge, $problem, $preferredDate, $preferredTime, $comment]);
$bookingId = $pdo->lastInsertId();

$telegramMsg = "<b>Новая заявка #{$bookingId}</b>\n"
    . "Имя: {$parentName}\n"
    . "Тел: {$phone}\n"
    . "Проблема: {$problem}\n";
if ($preferredDate) $telegramMsg .= "Дата: {$preferredDate}\n";
if ($preferredTime) $telegramMsg .= "Время: {$preferredTime}\n";
if ($childAge) $telegramMsg .= "Возраст: {$childAge}\n";
if ($comment) $telegramMsg .= "Комментарий: {$comment}\n";

sendTelegramNotification($telegramMsg);

$emailBody = "<h2>Новая заявка #{$bookingId}</h2>"
    . "<p><b>Имя:</b> {$parentName}</p>"
    . "<p><b>Телефон:</b> {$phone}</p>"
    . "<p><b>Проблема:</b> {$problem}</p>";
if ($preferredDate) $emailBody .= "<p><b>Дата:</b> {$preferredDate}</p>";
if ($preferredTime) $emailBody .= "<p><b>Время:</b> {$preferredTime}</p>";
if ($email) $emailBody .= "<p><b>Email:</b> {$email}</p>";
if ($childAge) $emailBody .= "<p><b>Возраст:</b> {$childAge}</p>";
if ($comment) $emailBody .= "<p><b>Комментарий:</b> {$comment}</p>";

sendEmailNotification("Новая заявка #{$bookingId}", $emailBody);

jsonResponse([
    'id' => (int)$bookingId,
    'parentName' => $parentName,
    'phone' => $phone,
    'status' => 'new',
    'message' => 'Заявка успешно отправлена',
]);
