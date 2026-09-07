<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Метод не поддерживается. Используйте POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $env[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
        }
    }
}

$googleScriptUrl = $env['GOOGLE_SCRIPT_URL'] ?? 'https://script.google.com/macros/s/AKfycbwEX5yOenoxiIpkFlt0BGHbV4SPmJiWIrzIFU-0t8R-4lN59vMuTnhMhlAP6ImemV59Fw/exec';

$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

if (!is_array($inputData)) {
    $inputData = $_POST;
}

$productId     = isset($inputData['productId']) ? trim((string)$inputData['productId']) : '';
$orderId       = isset($inputData['orderId']) ? trim((string)$inputData['orderId']) : '';
$productName   = isset($inputData['productName']) ? trim((string)$inputData['productName']) : '';
$price         = isset($inputData['price']) ? trim((string)$inputData['price']) : '';
$gender        = isset($inputData['gender']) ? trim((string)$inputData['gender']) : '';
$size          = isset($inputData['size']) ? trim((string)$inputData['size']) : '';
$contact       = isset($inputData['contact']) ? trim((string)$inputData['contact']) : '';
$phone         = isset($inputData['phone']) ? trim((string)$inputData['phone']) : '';
$tgUsername    = isset($inputData['tgUsername']) ? trim((string)$inputData['tgUsername']) : '';
$transactionId = isset($inputData['transactionId']) ? trim((string)$inputData['transactionId']) : '';
$source        = isset($inputData['source']) ? trim((string)$inputData['source']) : 'Сайт (5Post + CloudPayments)';

$fivepostPointId     = isset($inputData['fivepostPointId']) ? trim((string)$inputData['fivepostPointId']) : '';
$fivepostPointName   = isset($inputData['fivepostPointName']) ? trim((string)$inputData['fivepostPointName']) : '';
$fivepostAddress     = isset($inputData['fivepostPointAddress']) ? trim((string)$inputData['fivepostPointAddress']) : '';
$fivepostType        = isset($inputData['fivepostPointType']) ? trim((string)$inputData['fivepostPointType']) : '';
$fivepostDetails     = isset($inputData['fivepostPointDetails']) ? trim((string)$inputData['fivepostPointDetails']) : '';

if (empty($contact)) {
    $parts = [];
    if (!empty($phone)) $parts[] = $phone;
    if (!empty($tgUsername)) $parts[] = $tgUsername;
    if (!empty($fivepostAddress)) {
        $typeRu = ($fivepostType === 'POSTAMAT') ? 'Постамат' : (($fivepostType === 'TOBACCO') ? 'Касса' : 'ПВЗ');
        $parts[] = "5Post: $fivepostAddress ($typeRu)" . (!empty($fivepostPointId) ? " [ID: $fivepostPointId]" : '');
    }
    if (!empty($transactionId)) $parts[] = '[ОПЛАЧЕНО CloudPayments #' . $transactionId . ']';
    $contact = implode(' / ', $parts);
}

if (!empty($orderId)) {
    $productId = $orderId . (!empty($productId) ? " ($productId)" : '');
}

if (empty($productName)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Не указано наименование товара.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

if (empty($googleScriptUrl) || $googleScriptUrl === 'YOUR_GOOGLE_APPS_SCRIPT_URL_HERE') {
    echo json_encode([
        'success' => true,
        'mode'    => 'test_mode',
        'message' => 'Тестовый режим: данные успешно приняты и валидированы.',
        'order'   => $orderPayload
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$ch = curl_init($googleScriptUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($orderPayload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

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

