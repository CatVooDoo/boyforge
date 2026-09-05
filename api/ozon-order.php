<?php
/**
 * api/ozon-order.php — Обработчик заказов с OZON Доставкой
 * BOYFORGE
 */

header('Content-Type: application/json; charset=utf-8');

// Разрешаем только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Метод не поддерживается. Используйте POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Загрузка конфигурации из .env (внутри src)
$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $env[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
        }
    }
}

$ozonClientId     = $env['OZON_CLIENT_ID'] ?? 'b2685381-5aea-463e-9087-0f38bd729f68';
$ozonClientSecret = $env['OZON_CLIENT_SECRET'] ?? '5cc652ac46578c0af829d2aec1b1bf7ae4c3f594f2781ca72a4a5883ab5c7b28';
$googleScriptUrl  = $env['GOOGLE_SCRIPT_URL'] ?? 'https://script.google.com/macros/s/AKfycbwEX5yOenoxiIpkFlt0BGHbV4SPmJiWIrzIFU-0t8R-4lN59vMuTnhMhlAP6ImemV59Fw/exec';

// Получаем входные данные
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);
if (!is_array($inputData)) {
    $inputData = $_POST;
}

// Санитизация
$productId     = isset($inputData['productId']) ? trim((string)$inputData['productId']) : '';
$productName   = isset($inputData['productName']) ? trim((string)$inputData['productName']) : '';
$price         = isset($inputData['price']) ? trim((string)$inputData['price']) : '';
$gender        = isset($inputData['gender']) ? trim((string)$inputData['gender']) : '';
$size          = isset($inputData['size']) ? trim((string)$inputData['size']) : '';
$tgUsername    = isset($inputData['tgUsername']) ? trim((string)$inputData['tgUsername']) : (isset($inputData['recipientName']) ? trim((string)$inputData['recipientName']) : '');
$phone         = isset($inputData['phone']) ? trim((string)$inputData['phone']) : '';
$city          = isset($inputData['city']) ? trim((string)$inputData['city']) : 'Москва';
$pvzId         = isset($inputData['pvzId']) ? trim((string)$inputData['pvzId']) : '';
$pvzAddress    = isset($inputData['pvzAddress']) ? trim((string)$inputData['pvzAddress']) : '';
$paymentStatus = isset($inputData['paymentStatus']) ? trim((string)$inputData['paymentStatus']) : 'paid';
$transactionId = isset($inputData['transactionId']) ? trim((string)$inputData['transactionId']) : '';
$customOrderId = isset($inputData['orderId']) ? trim((string)$inputData['orderId']) : '';
$source        = 'OZON Доставка / CloudPayments';

// Форматирование Telegram ника (добавляем @ если не указан)
if (!empty($tgUsername) && $tgUsername[0] !== '@') {
    $tgUsername = '@' . $tgUsername;
}

// Валидация
if (empty($productName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан товар.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (empty($tgUsername)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Укажите ваш @username в Telegram.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Укажите номер телефона для связи.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ПВЗ теперь опционален — если не выбран, фиксируем статус согласования
if (empty($pvzAddress)) {
    $pvzAddress = 'Не выбран (согласовать в Telegram)';
}

// Уникальный номер заказа
$orderNum = !empty($customOrderId) ? $customOrderId : ('BF-OZON-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6)));
$orderDate = date('d.m.Y H:i:s');

// Формируем полезную нагрузку
$orderPayload = [
    'orderId'       => $orderNum,
    'date'          => $orderDate,
    'productId'     => $productId,
    'productName'   => $productName,
    'price'         => $price,
    'gender'        => $gender,
    'size'          => $size,
    'tgUsername'    => $tgUsername,
    'recipientName' => $tgUsername,
    'phone'         => $phone,
    'city'          => $city,
    'pvzId'         => $pvzId,
    'pvzAddress'    => $pvzAddress,
    'paymentStatus' => $paymentStatus,
    'transactionId' => $transactionId,
    'source'        => $source,
    'ip'            => $_SERVER['REMOTE_ADDR'] ?? '',
    'userAgent'     => $_SERVER['HTTP_USER_AGENT'] ?? ''
];

// 1. Дублирование в Google Таблицу (если настроена)
if (!empty($googleScriptUrl) && $googleScriptUrl !== 'YOUR_GOOGLE_APPS_SCRIPT_URL_HERE') {
    $contactInfo = $phone . ' / ' . $tgUsername . ' (ПВЗ: ' . $city . ', ' . $pvzAddress . ')';
    if (!empty($transactionId)) {
        $contactInfo .= ' [ОПЛАЧЕНО CloudPayments #' . $transactionId . ']';
    }

    $gPayload = [
        'date'        => $orderDate,
        'productId'   => $orderNum . ' (' . $productId . ')',
        'productName' => $productName,
        'price'       => $price,
        'gender'      => $gender,
        'size'        => $size,
        'contact'     => $contactInfo,
        'source'      => $source,
        'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
        'userAgent'   => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];

    $ch = curl_init($googleScriptUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($gPayload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// Успешный ответ
echo json_encode([
    'success'    => true,
    'orderId'    => $orderNum,
    'message'    => 'Заказ успешно оформлен и оплачен!',
    'order'      => $orderPayload
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

