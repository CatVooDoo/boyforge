<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/fivepost.php';

requireAdminAuth();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId = $input['order_id'] ?? '';

if (!$orderId) {
    echo json_encode(['success' => false, 'error' => 'No order ID provided']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :oid LIMIT 1");
    $stmt->execute([':oid' => $orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    if (!empty($order['fivepost_order_id'])) {
        echo json_encode(['success' => false, 'error' => 'Order already sent to 5Post']);
        exit;
    }

    if (empty($order['fivepost_point_id'])) {
        echo json_encode(['success' => false, 'error' => 'Order has no 5Post delivery point']);
        exit;
    }

    // Attempt to send to 5Post
    try {
        $fivepostData = createFivepostOrder($order);
        
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

        echo json_encode(['success' => true, 'data' => $fivepostData]);
    } catch (Exception $e) {
        // Log error
        $upd = $pdo->prepare("UPDATE orders SET fivepost_status = 'ERROR' WHERE order_id = :oid");
        $upd->execute([':oid' => $orderId]);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
