<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/order_logic.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$paymentId = $input['paymentId'] ?? '';
$orderId = $input['orderId'] ?? '';

if (!$paymentId || !$orderId) {
    echo json_encode(['success' => false, 'error' => 'Missing paymentId or orderId']);
    exit;
}

// Запрашиваем статус платежа у ЮKassa напрямую
$shopId = env_get('YOOKASSA_SHOP_ID');
$secretKey = env_get('YOOKASSA_SECRET_KEY');

$ch = curl_init("https://api.yookassa.ru/v3/payments/{$paymentId}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Basic " . base64_encode("{$shopId}:{$secretKey}")
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $paymentData = json_decode($response, true);
    
    // Проверяем статус
    if (($paymentData['status'] ?? '') === 'succeeded') {
        global $pdo;
        // Запускаем процесс отправки в 5Post и Google Sheets (он идемпотентен)
        processPaidOrder($pdo, $orderId, $paymentId);
        
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Payment not succeeded yet', 'status' => $paymentData['status'] ?? 'unknown']);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Failed to fetch payment status from YooKassa']);
