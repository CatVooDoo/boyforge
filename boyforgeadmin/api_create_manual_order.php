<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/fivepost.php';

requireAdminAuth();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$fio = trim((string)($input['fio'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$email = trim((string)($input['email'] ?? ''));
$productName = trim((string)($input['product_name'] ?? 'Ручной заказ'));
$price = (float)($input['price'] ?? 0);
$size = trim((string)($input['size'] ?? ''));
$pointId = trim((string)($input['fivepost_point_id'] ?? ''));
$pointAddress = trim((string)($input['fivepost_point_address'] ?? 'Адрес не указан (введено вручную)'));

if (!$fio || !$phone || !$pointId) {
    echo json_encode(['success' => false, 'error' => 'Заполните ФИО, телефон и ID точки 5Post']);
    exit;
}

try {
    // Generate an order ID
    $orderId = 'BF-M-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

    $data = [
        'order_id' => $orderId,
        'client_order_id' => $orderId,
        'product_id' => 'MANUAL',
        'product_name' => $productName,
        'price' => $price,
        'gender' => '',
        'size' => $size,
        'fio' => $fio,
        'phone' => $phone,
        'tg_username' => '',
        'email' => $email,
        'fivepost_point_id' => $pointId,
        'fivepost_point_name' => 'Ручной заказ',
        'fivepost_point_address' => $pointAddress,
        'fivepost_point_type' => 'POSTAMAT',
        'payment_status' => 'manual', // Distinguish from paid/unpaid
        'payment_transaction_id' => 'MANUAL',
    ];

    // Insert into DB
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_id, client_order_id, product_id, product_name, price, gender, size,
            fio, phone, tg_username, email, fivepost_point_id, fivepost_point_name, fivepost_point_address,
            fivepost_point_type, payment_status, payment_transaction_id, created_at
        ) VALUES (
            :order_id, :client_order_id, :product_id, :product_name, :price, :gender, :size,
            :fio, :phone, :tg_username, :email, :fivepost_point_id, :fivepost_point_name, :fivepost_point_address,
            :fivepost_point_type, :payment_status, :payment_transaction_id, NOW()
        )
    ");
    $stmt->execute([
        ':order_id'               => $data['order_id'],
        ':client_order_id'        => $data['client_order_id'],
        ':product_id'             => $data['product_id'],
        ':product_name'           => $data['product_name'],
        ':price'                  => $data['price'],
        ':gender'                 => $data['gender'],
        ':size'                   => $data['size'],
        ':fio'                    => $data['fio'],
        ':phone'                  => $data['phone'],
        ':tg_username'            => $data['tg_username'],
        ':email'                  => $data['email'],
        ':fivepost_point_id'      => $data['fivepost_point_id'],
        ':fivepost_point_name'    => $data['fivepost_point_name'],
        ':fivepost_point_address' => $data['fivepost_point_address'],
        ':fivepost_point_type'    => $data['fivepost_point_type'],
        ':payment_status'         => $data['payment_status'],
        ':payment_transaction_id' => $data['payment_transaction_id']
    ]);

    // Send to 5Post
    try {
        $fivepostData = createFivepostOrder($data);
        
        $upd = $pdo->prepare("
            UPDATE orders 
            SET fivepost_order_id = :foid,
                fivepost_cargo_id = :fcid,
                fivepost_barcode = :fbc,
                fivepost_status = 'SENT'
            WHERE order_id = :oid
        ");
        $upd->execute([
            ':foid' => $fivepostData['orderId'] ?? null,
            ':fcid' => $fivepostData['cargoId'] ?? null,
            ':fbc'  => $fivepostData['barcode'] ?? null,
            ':oid'  => $orderId
        ]);

        echo json_encode([
            'success' => true, 
            'order_id' => $orderId,
            'barcode' => $fivepostData['barcode'] ?? null
        ]);
    } catch (Exception $e) {
        $upd = $pdo->prepare("UPDATE orders SET fivepost_status = 'ERROR' WHERE order_id = :oid");
        $upd->execute([':oid' => $orderId]);
        echo json_encode(['success' => false, 'error' => 'Заказ создан, но ошибка 5Post: ' . $e->getMessage()]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
