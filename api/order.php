<?php
/**
 * api/order.php — Обработчик отправки данных заказа в Google Таблицу
 * BOYFORGE
 */

header('Content-Type: application/json; charset=utf-8');

// === НАСТРОЙКИ ===
// Вставьте сюда URL веб-приложения Google Apps Script после развертывания:
// Пример: 'https://script.google.com/macros/s/AKfycb.../exec'
define('GOOGLE_SCRIPT_URL', 'https://script.google.com/macros/s/AKfycbwEX5yOenoxiIpkFlt0BGHbV4SPmJiWIrzIFU-0t8R-4lN59vMuTnhMhlAP6ImemV59Fw/exec');

// Разрешаем только POST запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Метод не поддерживается. Используйте POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Получаем данные (JSON или стандартный POST)
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

if (!is_array($inputData)) {
    $inputData = $_POST;
}

// Извлекаем и санитизируем поля
$productId   = isset($inputData['productId']) ? trim((string)$inputData['productId']) : '';
$productName = isset($inputData['productName']) ? trim((string)$inputData['productName']) : '';
$price       = isset($inputData['price']) ? trim((string)$inputData['price']) : '';
$gender      = isset($inputData['gender']) ? trim((string)$inputData['gender']) : '';
$size        = isset($inputData['size']) ? trim((string)$inputData['size']) : '';
$contact     = isset($inputData['contact']) ? trim((string)$inputData['contact']) : '';
$source      = isset($inputData['source']) ? trim((string)$inputData['source']) : 'Сайт (Telegram)';

// Простая валидация
if (empty($productName)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Не указано наименование товара.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Формируем полезную нагрузку для Google Таблицы
$orderPayload = [
    'date'        => date('d.m.Y H:i:s'),
    'productId'   => $productId,
    'productName' => $productName,
    'price'       => $price,
    'gender'      => $gender,
    'size'        => $size,
    'contact'     => $contact,
    'source'      => $source,
    'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
    'userAgent'   => $_SERVER['HTTP_USER_AGENT'] ?? ''
];

// Если URL Google Скрипта ещё не указан (тестовый режим)
if (empty(GOOGLE_SCRIPT_URL) || GOOGLE_SCRIPT_URL === 'YOUR_GOOGLE_APPS_SCRIPT_URL_HERE') {
    echo json_encode([
        'success' => true,
        'mode'    => 'test_mode',
        'message' => 'Тестовый режим: данные успешно приняты и валидированы (URL Google Script пока не задан).',
        'order'   => $orderPayload
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Отправка в Google Apps Script через cURL
$ch = curl_init(GOOGLE_SCRIPT_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($orderPayload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_FOLLOWLOCATION => true, // Google Apps Script возвращает 302 редирект
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Ошибка связи с Google Sheets: ' . $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success'         => true,
    'message'         => 'Заказ успешно отправлен в Google Таблицу.',
    'google_status'   => $httpCode,
    'google_response' => $response,
    'order'           => $orderPayload
], JSON_UNESCAPED_UNICODE);
