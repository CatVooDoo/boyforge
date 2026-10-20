<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/order_logic.php';
require_once __DIR__ . '/../../includes/order_logger.php';

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

$email = trim($input['email'] ?? '');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Valid email is required']);
    exit;
}

$productId = $input['productId'] ?? '';
if (!$productId) {
    echo json_encode(['success' => false, 'error' => 'Product ID is missing']);
    exit;
}

global $pdo;
$stmt = $pdo->prepare("SELECT name, price FROM products WHERE id = :id OR cat_id = :cat_id LIMIT 1");
if (is_numeric($productId)) {
    $stmt->execute([':id' => $productId, ':cat_id' => '']);
} else {
    $stmt->execute([':id' => 0, ':cat_id' => $productId]);
}
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['success' => false, 'error' => 'Product not found']);
    exit;
}

$priceStr = $product['price'] ?? '3200';
$priceValue = (float) preg_replace('/[^\d.]/', '', str_replace(',', '.', $priceStr));
if ($priceValue <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product price in DB']);
    exit;
}

$input['price'] = (string)$priceValue;
$input['productName'] = $product['name'];

$newOrderId = (string)($input['orderId'] ?? '');
if (!preg_match('/^BF-[A-Z0-9]{6,32}$/', $newOrderId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некорректный номер заказа']);
    exit;
}
$existingOrder = $pdo->prepare('SELECT 1 FROM orders WHERE order_id = ?');
$existingOrder->execute([$newOrderId]);
if ($existingOrder->fetchColumn()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Заказ уже существует. Повторите оформление.']);
    exit;
}

// Resolve the office and tariff on the server; never trust browser prices or addresses.
$provider = $input['deliveryProvider'] ?? 'fivepost';
if (!in_array($provider, ['fivepost', 'cdek'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неизвестная служба доставки']);
    exit;
}
$input['deliveryProvider'] = $provider;
$input['deliveryCost'] = 0;
$input['deliveryMode'] = 'production';
$deliveryCost = 0.0;
if ($provider === 'cdek') {
    try {
        $cdek = new CdekClient();
        if (count(preg_split('/\s+/u', trim((string)($input['fio'] ?? '')), -1, PREG_SPLIT_NO_EMPTY)) < 2
            || !preg_match('/^\+7\d{10}$/', (string)($input['phone'] ?? ''))) {
            throw new RuntimeException('Укажите имя, фамилию и полный номер телефона получателя');
        }
        $code = (string)($input['fivepostPointId'] ?? '');
        if (!preg_match('/^[a-z0-9_-]{1,32}$/i', $code)) throw new RuntimeException('Выберите пункт СДЭК');
        $points = $cdek->points(['code' => $code]);
        $point = array_values(array_filter($points, static fn($p) => $p['id'] === $code))[0] ?? null;
        if (!$point) throw new RuntimeException('Пункт СДЭК недоступен');
        $quote = $cdek->quote($point, $priceValue);
        $deliveryCost = round($quote['cost'], 2);
        if (($input['deliveryQuoteMode'] ?? '') !== $cdek->mode()
            || abs((float)($input['deliveryQuoteCost'] ?? -1) - $deliveryCost) > 0.009) {
            throw new RuntimeException('Условия доставки изменились. Выберите пункт ещё раз.');
        }
        // Sandbox shipments must not be coupled to a real payment.
        if ($cdek->mode() === 'sandbox' && !str_starts_with((string)env_get('YOOKASSA_SECRET_KEY'), 'test_')) {
            throw new RuntimeException('Для тестового СДЭК включите тестовый магазин ЮKassa. Реальная оплата отключена.');
        }
        $input['deliveryMode'] = $cdek->mode();
        $input['deliveryCost'] = $deliveryCost;
        $input['fivepostPointName'] = $point['name'];
        $input['fivepostPointAddress'] = $point['full_address'];
        $input['fivepostPointType'] = $point['type'];
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
$totalValue = $priceValue + $deliveryCost;

$orderId = $input['orderId'] ?? 'new';
$productName = $input['productName'] ?? 'Товар BOYFORGE';
$gender = $input['gender'] ?? '';
$size = $input['size'] ?? '';
$description = "Оплата заказа $orderId: $productName ($gender, $size)";

$shopId = env_get('YOOKASSA_SHOP_ID');
$secretKey = env_get('YOOKASSA_SECRET_KEY');

if (empty($shopId) || empty($secretKey)) {
    echo json_encode(['success' => false, 'error' => 'YooKassa credentials not configured']);
    exit;
}

global $pdo;
saveOrderToDb($pdo, $input, 'unpaid', '');

logOrderEvent($orderId, 'order_created', [
    'fio' => $input['fio'] ?? '',
    'email' => $email,
    'status' => 'unpaid',
    'product_id' => $productId,
    'price' => $priceValue
]);

$taxSystemCode = (int) (env_get('YOOKASSA_TAX_SYSTEM') ?: '1');
$vatCode = (int) (env_get('YOOKASSA_VAT_CODE') ?: '1');

$paymentData = [
    'amount' => [
        'value' => number_format($totalValue, 2, '.', ''),
        'currency' => 'RUB'
    ],
    'capture' => true,
    'confirmation' => [
        'type' => 'embedded'
    ],
    'description' => mb_substr($description, 0, 128),
    'metadata' => [
        'order_id' => $orderId
    ],
    'receipt' => [
        'customer' => [
            'email' => $email
        ],
        'items' => [
            [
                'description' => mb_substr("Заказ $orderId: $productName", 0, 128),
                'quantity' => 1.000,
                'amount' => [
                    'value' => number_format($priceValue, 2, '.', ''),
                    'currency' => 'RUB'
                ],
                'vat_code' => $vatCode,
                'payment_mode' => 'full_prepayment',
                'payment_subject' => 'commodity',
                'measure' => 'piece'
            ]
        ],
        'tax_system_code' => $taxSystemCode
    ]
];

if ($deliveryCost > 0) {
    $paymentData['receipt']['items'][] = [
        'description' => 'Доставка СДЭК до пункта выдачи', 'quantity' => 1.000,
        'amount' => ['value' => number_format($deliveryCost, 2, '.', ''), 'currency' => 'RUB'],
        'vat_code' => $vatCode, 'payment_mode' => 'full_prepayment', 'payment_subject' => 'service',
    ];
}

$idempotenceKey = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$ch = curl_init('https://api.yookassa.ru/v3/payments');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Idempotence-Key: ' . $idempotenceKey
]);
curl_setopt($ch, CURLOPT_USERPWD, $shopId . ':' . $secretKey);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'cURL error: ' . $error]);
    exit;
}

$responseData = json_decode($response, true);

logOrderEvent($orderId, 'yookassa_payment_created', [
    'http_code' => $httpCode,
    'response' => $responseData ?: $response
]);

if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['confirmation']['confirmation_token'])) {
    $stmt = $pdo->prepare("UPDATE orders SET payment_transaction_id = :tx WHERE order_id = :oid");
    $stmt->execute([
        ':tx' => $responseData['id'],
        ':oid' => $orderId
    ]);

    echo json_encode([
        'success' => true,
        'total_amount' => number_format($totalValue, 2, '.', ''),
        'delivery_cost' => number_format($deliveryCost, 2, '.', ''),
        'confirmation_token' => $responseData['confirmation']['confirmation_token'],
        'payment_id' => $responseData['id']
    ]);
} else {
    $errorMsg = 'YooKassa API Error';
    if (is_array($responseData) && isset($responseData['code'])) {
        $errorMsg = "ЮKassa [" . $responseData['code'] . "]: " . ($responseData['description'] ?? '');
        if (!empty($responseData['parameter'])) {
            $errorMsg .= " (Параметр: " . $responseData['parameter'] . ")";
        }
    } elseif ($httpCode) {
        $errorMsg .= " (HTTP $httpCode)";
    }

    echo json_encode([
        'success' => false,
        'error' => $errorMsg,
        'details' => $responseData
    ]);
}
