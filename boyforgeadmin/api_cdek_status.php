<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/../includes/cdek_orders.php';
require_once __DIR__ . '/../includes/order_logger.php';
requireAdminAuth();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}
$input = json_decode((string)file_get_contents('php://input'), true) ?: [];
if (!verifyCsrfToken($input['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Обновите страницу и повторите попытку']);
    exit;
}
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND delivery_provider = 'cdek'");
    $stmt->execute([(string)($input['order_id'] ?? '')]);
    $order = $stmt->fetch();
    if (!$order || empty($order['cdek_uuid'])) throw new RuntimeException('Нет зарегистрированного отправления СДЭК');
    // Read an existing shipment only; this endpoint cannot create or duplicate one.
    processCdekOrder($pdo, $order);
    echo json_encode(['success' => true, 'status' => $order['cdek_status']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
