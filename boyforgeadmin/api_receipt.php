<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/order_items.php';
require_once dirname(__DIR__) . '/includes/order_logger.php';
requireAdminAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true) ?? [];
$orderId = trim((string)($input['order_id'] ?? ''));

$rawMarkCodes = [];
if (!empty($input['mark_codes']) && is_array($input['mark_codes'])) {
    foreach ($input['mark_codes'] as $mc) {
        $norm = normalizeChestnyZnakCode((string)$mc);
        if ($norm !== '') {
            $rawMarkCodes[] = $norm;
        }
    }
} elseif (!empty($input['mark_code'])) {
    $lines = preg_split('/\r\n|\r|\n/', (string)$input['mark_code']) ?: [];
    foreach ($lines as $line) {
        $norm = normalizeChestnyZnakCode($line);
        if ($norm !== '') {
            $rawMarkCodes[] = $norm;
        }
    }
}

if ($orderId === '') {
    echo json_encode(['success' => false, 'error' => 'Отсутствует order_id'], JSON_UNESCAPED_UNICODE);
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

    if ((int)$order['receipt_sent'] === 1) {
        throw new Exception('Закрывающий чек для этого заказа уже был отправлен.');
    }

    $shopId = env_get('YOOKASSA_SHOP_ID');
    $secretKey = env_get('YOOKASSA_SECRET_KEY');

    if (empty($shopId) || empty($secretKey)) {
        throw new Exception('Ключи ЮKassa не настроены.');
    }

    $units = expandOrderItemsToUnits($order);

    $taxSystemCode = (int)(env_get('YOOKASSA_TAX_SYSTEM') ?: '1');
    $vatCode = (int)(env_get('YOOKASSA_VAT_CODE') ?: '1');

    $receiptPayload = buildYookassaClosingReceiptPayload(
        $order,
        $rawMarkCodes,
        $taxSystemCode,
        $vatCode
    );

    $url = 'https://api.yookassa.ru/v3/receipts';
    $ch = curl_init($url);
    $idempotenceKey = 'receipt_' . $order['order_id'] . '_' . time();

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Basic " . base64_encode("{$shopId}:{$secretKey}"),
            "Idempotence-Key: {$idempotenceKey}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS     => json_encode($receiptPayload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        throw new Exception('Ошибка сети при отправке в ЮKassa: ' . $curlErr);
    }

    $resData = json_decode((string)$response, true);

    logOrderEvent($orderId, 'yookassa_closing_receipt', [
        'http_code'   => $httpCode,
        'units_count' => count($units),
        'response'    => $resData ?: $response
    ]);

    if ($httpCode >= 200 && $httpCode < 300 && isset($resData['id'])) {
        $isMarked = !empty($rawMarkCodes);
        $markSummary = $isMarked ? implode("\n", $rawMarkCodes) : '';
        $markJson = $isMarked ? json_encode($rawMarkCodes, JSON_UNESCAPED_UNICODE) : null;

        $upd = $pdo->prepare("UPDATE orders SET receipt_sent = 1, mark_code = :mc, mark_codes_json = :mcj WHERE id = :id");
        $upd->execute([
            ':mc'  => $markSummary,
            ':mcj' => $markJson,
            ':id'  => $order['id']
        ]);

        error_log(sprintf(
            '[Receipt] Closing receipt sent for order %s. Receipt ID: %s, Units: %d, Marked: %s',
            $orderId,
            $resData['id'],
            count($units),
            $isMarked ? 'yes' : 'no'
        ));

        echo json_encode([
            'success'    => true,
            'receipt_id' => $resData['id'],
            'marked'     => $isMarked,
            'mark_codes' => $rawMarkCodes,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('Ошибка ЮKassa (' . $httpCode . '): ' . ($resData['description'] ?? $response));
    }
} catch (Throwable $e) {
    error_log('[Receipt] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
