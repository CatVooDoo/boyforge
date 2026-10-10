<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

function isYookassaIp(string $ip, array $whitelist): bool {
    $ip = trim($ip);
    
    foreach ($whitelist as $allowed) {
        $allowed = trim($allowed);
        
        if (strpos($allowed, '/') !== false) {
            list($subnet, $mask) = explode('/', $allowed, 2);
            $subnet = trim($subnet);
            $mask = (int)trim($mask);
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    continue;
                }
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);
                $maskLong = -1 << (32 - $mask);
                
                if (($ipLong & $maskLong) === ($subnetLong & $maskLong)) {
                    return true;
                }
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    continue;
                }
                $ipBin = inet_pton($ip);
                $subnetBin = inet_pton($subnet);
                
                if ($ipBin === false || $subnetBin === false) {
                    continue;
                }
                
                $bytesToCheck = intdiv($mask, 8);
                $match = true;
                for ($i = 0; $i < $bytesToCheck; $i++) {
                    if ($ipBin[$i] !== $subnetBin[$i]) {
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    return true;
                }
            }
        } else {
            if ($ip === $allowed) {
                return true;
            }
        }
    }
    
    return false;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$paymentId = trim($input['payment_id'] ?? '');
$orderId = trim($input['order_id'] ?? '');
$amount = isset($input['amount']) ? (float)$input['amount'] : null;
$description = trim($input['description'] ?? 'Возврат средств');

if (empty($paymentId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Отсутствует обязательный параметр: payment_id']);
    exit;
}

if (empty($orderId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Отсутствует обязательный параметр: order_id']);
    exit;
}

try {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :oid LIMIT 1");
    $stmt->execute([':oid' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Заказ не найден в базе данных.');
    }

    if ($order['payment_status'] !== 'paid' && $order['payment_status'] !== 'refunded') {
        throw new Exception('Возврат возможен только для оплаченных заказов. Текущий статус: ' . $order['payment_status']);
    }

    if ($order['payment_status'] === 'refunded') {
        error_log('[Refund] Order ' . $orderId . ' already has refund status');
    }
    
    if ($amount === null || $amount <= 0) {
        $amount = (float)$order['price'];
    }
    
    $orderAmount = (float)$order['price'];
    if ($amount > $orderAmount) {
        throw new Exception('Сумма возврата (' . $amount . ') превышает сумму заказа (' . $orderAmount . ')');
    }

    $shopId = env_get('YOOKASSA_SHOP_ID');
    $secretKey = env_get('YOOKASSA_SECRET_KEY');
    
    if (empty($shopId) || empty($secretKey)) {
        throw new Exception('Ключи доступа ЮKassa не настроены в конфигурации.');
    }

    $taxSystemCode = (int)(env_get('YOOKASSA_TAX_SYSTEM') ?: '1');
    
    $vatCode = (int)(env_get('YOOKASSA_VAT_CODE') ?: '1');
    
    $customerEmail = !empty($order['email']) ? $order['email'] : 'no-reply@boyforge.com';
    
    $isMarked = !empty($order['mark_code']);

    $paymentSubject = $isMarked ? 'marked' : 'commodity';
    
    $amountValue = number_format($amount, 2, '.', '');

    $receiptPayload = [
        
        'internet' => true,

        'timezone' => 2,
        
        'customer' => [
            'email' => $customerEmail
        ],

        'type' => 'refund',
        
        'send' => true,
        
        'payment_id' => $paymentId,
        
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
                'description' => mb_substr('Возврат: ' . $order['product_name'], 0, 128),
                'quantity' => '1.000',
                'amount' => [
                    'value' => $amountValue,
                    'currency' => 'RUB'
                ],
                'vat_code' => $vatCode,

                'payment_subject' => $paymentSubject,

                'payment_mode' => 'full_payment',
                
                'measure' => 'piece'
            ]
        ]
    ];
    
    if ($isMarked) {
        $receiptPayload['items'][0]['mark_code_info'] = [
            'gs_1m' => $order['mark_code']
        ];
    }

    $refundPayload = [
        'amount' => [
            'value' => $amountValue,
            'currency' => 'RUB'
        ],
        'payment_id' => $paymentId,
        'description' => $description,

        'receipt' => $receiptPayload
    ];

    $idempotenceKey = 'refund_' . $orderId . '_' . time();
    
    $url = 'https://api.yookassa.ru/v3/refunds';
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic " . base64_encode("{$shopId}:{$secretKey}"),
            "Idempotence-Key: {$idempotenceKey}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($refundPayload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 15
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    
    if ($curlErr) {
        throw new Exception('Ошибка сети при отправке запроса на возврат: ' . $curlErr);
    }
    
    $resData = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($resData['id'])) {
        $refundId = $resData['id'];
        $refundStatus = $resData['status'] ?? 'unknown';
        $createdAt = $resData['created_at'] ?? date('c');

        $stmt = $pdo->prepare("
            UPDATE orders 
            SET 
                payment_status = 'refunded',
                refund_amount = :refund_amount,
                refund_id = :refund_id,
                updated_at = NOW()
            WHERE order_id = :oid
        ");
        $stmt->execute([
            ':refund_amount' => $amountValue,
            ':refund_id' => $refundId,
            ':oid' => $orderId
        ]);

        error_log(sprintf(
            '[Refund] Successfully created refund for order %s. Refund ID: %s, Amount: %s RUB, Status: %s',
            $orderId,
            $refundId,
            $amountValue,
            $refundStatus
        ));
        
        echo json_encode([
            'success' => true,
            'refund_id' => $refundId,
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'amount' => $amountValue,
            'status' => $refundStatus,
            'created_at' => $createdAt,
            'receipt_sent' => true,
            'marked_item' => $isMarked
        ]);
        
    } else {
        
        $errorMsg = $resData['description'] ?? ($resData['message'] ?? $response);
        throw new Exception('Ошибка ЮKassa при создании возврата (' . $httpCode . '): ' . $errorMsg);
    }
    
} catch (Throwable $e) {
    
    error_log('[Refund] Error processing refund: ' . $e->getMessage());
    error_log('[Refund] Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
