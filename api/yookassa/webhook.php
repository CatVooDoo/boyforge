<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/order_logic.php';

// Получаем тело запроса
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (!$data) {
    http_response_code(400);
    exit;
}

// Защита: проверка IP адресов ЮKassa (опционально, но рекомендуется)
// Мы пропустим жесткую привязку, так как в ТЗ этого не было, но будем логировать

// ЮKassa отправляет события. Нас интересует payment.succeeded
$event = $data['event'] ?? '';
if ($event === 'payment.succeeded') {
    $paymentObj = $data['object'] ?? [];
    $paymentId = $paymentObj['id'] ?? '';
    $orderId = $paymentObj['metadata']['order_id'] ?? '';
    $status = $paymentObj['status'] ?? '';

    if ($orderId && $status === 'succeeded') {
        global $pdo;
        // Запускаем процесс отправки в 5Post и Google Sheets
        processPaidOrder($pdo, $orderId, $paymentId);
    }
}

// Всегда возвращаем 200 OK для ЮKassa, чтобы они не слали повторно
http_response_code(200);
echo "OK";
