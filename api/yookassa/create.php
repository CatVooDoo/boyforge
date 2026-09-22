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
// Note: Frontend sends cat_id sometimes if id is not matching. Let's just query by id if it's numeric
if (is_numeric($productId)) {
    $stmt->execute([':id' => $productId, ':cat_id' => '']);
} else {
    // If they send cat_id as productId?
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

// Перезаписываем входные данные доверенными данными из БД
$input['price'] = (string)$priceValue;
$input['productName'] = $product['name'];

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

// 1 = ОСН по умолчанию
$taxSystemCode = (int) (env_get('YOOKASSA_TAX_SYSTEM') ?: '1');
// 1 = Без НДС по умолчанию
$vatCode = (int) (env_get('YOOKASSA_VAT_CODE') ?: '1');

$paymentData = [
    'amount' => [
        'value' => number_format($priceValue, 2, '.', ''),
        'currency' => 'RUB'
    ],
    'capture' => true,
    'confirmation' => [
        'type' => 'embedded'
    ],
    'description' => mb_substr($description, 0, 128),
    'metadata' => [
        'order_id' => $orderId,
        'payload' => json_encode($input, JSON_UNESCAPED_UNICODE)
    ],
    'receipt' => [
        'customer' => [
            'email' => $email
        ],
        'items' => [
            [
                'description' => mb_substr($productName, 0, 128),
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

if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['confirmation']['confirmation_token'])) {
    // Сохраняем payment_id в базу, чтобы потом проверять статус при возврате на return_url
    $stmt = $pdo->prepare("UPDATE orders SET payment_transaction_id = :tx WHERE order_id = :oid");
    $stmt->execute([
        ':tx' => $responseData['id'],
        ':oid' => $orderId
    ]);

    echo json_encode([
        'success' => true,
        'confirmation_token' => $responseData['confirmation']['confirmation_token'],
        'payment_id' => $responseData['id']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'YooKassa API Error',
        'details' => $responseData
    ]);
}
