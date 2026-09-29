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
$orderId = trim((string)($input['order_id'] ?? ''));

if (empty($orderId)) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует order_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE orders SET mark_code = '__CANCELLED__' WHERE order_id = :oid AND (receipt_sent = 0 OR receipt_sent IS NULL)");
    $stmt->execute([':oid' => $orderId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось отменить заказ или он уже закрыт.']);
    }
} catch (Throwable $e) {
    error_log('[Cancel Order] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Внутренняя ошибка сервера'
    ]);
}
