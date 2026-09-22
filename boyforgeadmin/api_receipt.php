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

if (empty($orderId) || empty($markCode)) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует order_id или код маркировки']);
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

    // Подготовка чека "Полный расчет" (отгрузка с маркировкой)
    // 1 = ОСН по умолчанию
    $taxSystemCode = (int) (env_get('YOOKASSA_TAX_SYSTEM') ?: '1');
    // 1 = Без НДС по умолчанию
    $vatCode = (int) (env_get('YOOKASSA_VAT_CODE') ?: '1');
    
    $email = !empty($order['email']) ? $order['email'] : 'no-reply@boyforge.com';

    $receiptPayload = [
        'customer' => [
            'email' => $email
        ],
        'type' => 'payment', // Приход
        'send' => true,
        'payment_id' => $order['payment_transaction_id'],
        'settlements' => [
            [
                'type' => 'prepayment',
                'amount' => [
                    'value' => number_format((float)$order['price'], 2, '.', ''),
                    'currency' => 'RUB'
                ]
            ]
        ],
        'items' => [
            [
                'description' => mb_substr('Отгрузка: ' . $order['product_name'] . ' (' . $order['size'] . ')', 0, 128),
                'quantity' => '1.000',
                'amount' => [
                    'value' => number_format((float)$order['price'], 2, '.', ''),
                    'currency' => 'RUB'
                ],
                'vat_code' => $vatCode,
                'payment_subject' => 'commodity',
                'payment_mode' => 'full_payment', // Полный расчет
                'mark_code_info' => [
                    'gs_1m' => $markCode // GS1M код
                ],
                'measure' => 'piece'
            ]
        ]
    ];
    
    $url = 'https://api.yookassa.ru/v3/receipts';
    $ch = curl_init($url);
    
    $idempotenceKey = 'receipt_' . $order['order_id'];
    
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
        // Успешно отправлено
        $upd = $pdo->prepare("UPDATE orders SET receipt_sent = 1, mark_code = :mc WHERE id = :id");
        $upd->execute([
            ':mc' => $markCode,
            ':id' => $order['id']
        ]);
        
        echo json_encode([
            'success' => true,
            'receipt_id' => $resData['id']
        ]);
    } else {
        throw new Exception('Ошибка ЮKassa (' . $httpCode . '): ' . ($resData['description'] ?? $response));
    }

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
