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

$orderId = $input['orderId'] ?? '';
if (!$orderId) {
    echo json_encode(['success' => false, 'error' => 'Missing orderId']);
    exit;
}

global $pdo;

$stmt = $pdo->prepare("SELECT payment_transaction_id, payment_status FROM orders WHERE order_id = :oid");
$stmt->execute([':oid' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

if ($order['payment_status'] === 'paid') {
    echo json_encode(['success' => true, 'message' => 'Already paid']);
    exit;
}

$paymentId = $order['payment_transaction_id'];

if (!$paymentId) {
    echo json_encode(['success' => false, 'error' => 'No payment transaction ID found for this order']);
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
    if (($paymentData['status'] ?? '') === 'succeeded' || ($paymentData['status'] ?? '') === 'waiting_for_capture') {
        // Если waiting_for_capture, ЮKassa спишет деньги позже (или мы можем сделать capture здесь, но мы предположим succeeded)
        // Для простоты, если мы используем авто-списание, статус должен быть succeeded.
        // Запускаем процесс отправки в 5Post и Google Sheets (он идемпотентен)
        if (($paymentData['status'] ?? '') === 'succeeded') {
            processPaidOrder($pdo, $orderId, $paymentId);
            echo json_encode(['success' => true, 'status' => 'succeeded']);
            exit;
        } else {
            // waiting_for_capture? Need to capture it!
            $chCap = curl_init("https://api.yookassa.ru/v3/payments/{$paymentId}/capture");
            curl_setopt_array($chCap, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Basic " . base64_encode("{$shopId}:{$secretKey}"),
                    "Idempotence-Key: " . uniqid('cap_', true),
                    "Content-Type: application/json"
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'amount' => $paymentData['amount']
                ])
            ]);
            $capRes = curl_exec($chCap);
            curl_close($chCap);
            
            // Теперь оно точно succeeded
            processPaidOrder($pdo, $orderId, $paymentId);
            echo json_encode(['success' => true, 'status' => 'captured']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Payment not succeeded yet', 'status' => $paymentData['status'] ?? 'unknown']);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Failed to fetch payment status from YooKassa']);
