<?php
header('Content-Type: application/json; charset=utf-8');

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

$apiSecret       = $env['CLOUDPAYMENTS_API_SECRET'] ?? 'c0a9234eb56f475d09c62f83484f768c';
$googleScriptUrl = $env['GOOGLE_SCRIPT_URL'] ?? '';

$rawBody = file_get_contents('php://input');

$hmacHeader = $_SERVER['HTTP_CONTENT_HMAC'] ?? $_SERVER['HTTP_X_CONTENT_HMAC'] ?? '';
if (!empty($hmacHeader) && !empty($apiSecret)) {
    $expectedHmac = base64_encode(hash_hmac('sha256', $rawBody, $apiSecret, true));
    if (!hash_equals($expectedHmac, $hmacHeader)) {
        http_response_code(403);
        echo json_encode(['code' => 13, 'error' => 'Invalid HMAC signature'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$data = [];
if (!empty($_POST)) {
    $data = $_POST;
} else {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

$transactionId = isset($data['TransactionId']) ? (string)$data['TransactionId'] : '';
$amount        = isset($data['Amount']) ? (string)$data['Amount'] : '';
$currency      = isset($data['Currency']) ? (string)$data['Currency'] : 'RUB';
$invoiceId     = isset($data['InvoiceId']) ? (string)$data['InvoiceId'] : ('BF-PAY-' . substr(md5(uniqid()), 0, 6));
$status        = isset($data['Status']) ? (string)$data['Status'] : 'Completed';
$accountId     = isset($data['AccountId']) ? (string)$data['AccountId'] : '';
$cardType      = isset($data['CardType']) ? (string)$data['CardType'] : '';
$cardLastFour  = isset($data['CardLastFour']) ? (string)$data['CardLastFour'] : '';

$customData = [];
if (!empty($data['Data'])) {
    if (is_string($data['Data'])) {
        $customData = json_decode($data['Data'], true) ?: [];
    } elseif (is_array($data['Data'])) {
        $customData = $data['Data'];
    }
}

$productName   = $customData['productName'] ?? 'Товар BOYFORGE';
$gender        = $customData['gender'] ?? '';
$size          = $customData['size'] ?? '';
$tgUsername    = $customData['tgUsername'] ?? $accountId;
$phone         = $customData['phone'] ?? '';
$pvzAddress    = $customData['pvzAddress'] ?? 'Не выбран';
$pvzId         = $customData['pvzId'] ?? '';
$city          = $customData['city'] ?? 'Москва';

$orderDate = date('d.m.Y H:i:s');

if (!empty($googleScriptUrl) && $googleScriptUrl !== 'YOUR_GOOGLE_APPS_SCRIPT_URL_HERE') {
    $gPayload = [
        'date'        => $orderDate,
        'productId'   => $invoiceId,
        'productName' => $productName,
        'price'       => $amount . ' ' . $currency,
        'gender'      => $gender,
        'size'        => $size,
        'contact'     => $phone . ' / ' . $tgUsername . ' (ПВЗ: ' . $pvzAddress . ') [ОПЛАЧЕНО онлайн: ' . $transactionId . ']',
        'source'      => 'CloudPayments',
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

// CloudPayments webhook response
echo json_encode(['code' => 0]);
