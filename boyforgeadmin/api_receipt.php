<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
requireAdminAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId = trim($input['order_id'] ?? '');
$markCode = trim($input['mark_code'] ?? '');

if (strlen($markCode) === 83 && preg_match('/^(01\d{14}21.{13})(91.{4})(92.{44})$/', $markCode, $matches)) {
    $markCode = $matches[1] . "\x1D" . $matches[2] . "\x1D" . $matches[3];
}

if (empty($orderId)) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует order_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :oid LIMIT 1");
    $stmt->execute([':oid' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Заказ не найден.');
    }

    if ($order['payment_status'] !== 'paid' || empty($order['payment_transaction_id'])) {
        throw new Exception('Заказ не оплачен или нет ID транзакции.');
    }

    if ($order['receipt_sent'] == 1) {
        throw new Exception('Закрывающий чек для этого заказа уже был отправлен.');
    }

    $shopId = env_get('YOOKASSA_SHOP_ID');
    $secretKey = env_get('YOOKASSA_SECRET_KEY');

    if (empty($shopId) || empty($secretKey)) {
        throw new Exception('Ключи ЮKassa не настроены.');
    }

    $taxSystemCode = (int) (env_get('YOOKASSA_TAX_SYSTEM') ?: '1');
    $vatCode = (int) (env_get('YOOKASSA_VAT_CODE') ?: '1');
    
    $email = !empty($order['email']) ? $order['email'] : 'no-reply@boyforge.com';
    
    $amountValue = number_format((float)$order['price'], 2, '.', '');

    $isMarked = !empty($markCode);
    
    $paymentSubject = $isMarked ? 'marked' : 'commodity';

    $receiptPayload = [
        'customer' => [
            'email' => $email
        ],
        'type' => 'payment',
        'send' => true,
        'payment_id' => $order['payment_transaction_id'],

        'tax_system_code' => $taxSystemCode,

        'settlements' => [
            [
                
                'type' => 'prepayment',
                'amount' => [
                    'value' => $amountValue,
                    'currency' => 'RUB'
                ]
            ]
        ],
        'items' => [
            [
                'description' => mb_substr('Отгрузка: ' . $order['product_name'] . ' (' . $order['size'] . ')', 0, 128),
                'quantity' => '1.000',
                'amount' => [
                    'value' => $amountValue,
                    'currency' => 'RUB'
                ],
                'vat_code' => $vatCode,

                'payment_subject' => $paymentSubject,

                'payment_mode' => 'full_payment',

                'mark_code_info' => [
                    'gs_1m' => $markCode
                ],

                'measure' => 'piece'
            ]
        ]
    ];
    
    if (!$isMarked) {
        unset($receiptPayload['items'][0]['mark_code_info']);
    }
    
    $url = 'https://api.yookassa.ru/v3/receipts';
    $ch = curl_init($url);

    $idempotenceKey = 'receipt_' . $order['order_id'] . '_' . time();
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic " . base64_encode("{$shopId}:{$secretKey}"),
            "Idempotence-Key: {$idempotenceKey}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($receiptPayload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        throw new Exception('Ошибка сети при отправке в ЮKassa: ' . $curlErr);
    }

    $resData = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($resData['id'])) {
        $upd = $pdo->prepare("UPDATE orders SET receipt_sent = 1, mark_code = :mc WHERE id = :id");
        $upd->execute([
            ':mc' => $markCode,
            ':id' => $order['id']
        ]);
        
        error_log(sprintf(
            '[Receipt] Closing receipt sent for order %s. Receipt ID: %s, Marked: %s',
            $orderId,
            $resData['id'],
            $isMarked ? 'yes' : 'no'
        ));
        
        echo json_encode([
            'success' => true,
            'receipt_id' => $resData['id'],
            'marked' => $isMarked
        ]);
    } else {
        throw new Exception('Ошибка ЮKassa (' . $httpCode . '): ' . ($resData['description'] ?? $response));
    }

} catch (Throwable $e) {
    error_log('[Receipt] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
