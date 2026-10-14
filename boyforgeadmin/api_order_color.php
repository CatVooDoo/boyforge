<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireAdminAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true) ?? [];
$orderId = trim((string)($input['order_id'] ?? ''));
$stageColor = trim((string)($input['stage_color'] ?? ''));

if (empty($orderId)) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует ID заказа']);
    exit;
}

// Допустимые варианты: orange (сделан), teal (упакован), пустая строка (сброс)
$allowed = ['orange', 'teal', ''];
if (!in_array($stageColor, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый цвет']);
    exit;
}

$valToSave = ($stageColor === '') ? null : $stageColor;

try {
    // Проверяем / создаем колонку при первом обращении
    try {
        $hasCol = false;
        foreach ($pdo->query("SHOW COLUMNS FROM orders LIKE 'stage_color'")->fetchAll() as $col) {
            $hasCol = true;
            break;
        }
        if (!$hasCol) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN stage_color VARCHAR(32) NULL DEFAULT NULL AFTER payment_status");
        }
    } catch (Throwable $e) {
    }

    $stmt = $pdo->prepare("UPDATE orders SET stage_color = :color WHERE order_id = :oid");
    $stmt->execute([
        ':color' => $valToSave,
        ':oid' => $orderId
    ]);

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'stage_color' => $stageColor
    ]);
} catch (Throwable $e) {
    error_log('[Order Color API] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных при сохранении цвета'
    ]);
}
