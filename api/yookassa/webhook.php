<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/order_logic.php';
require_once __DIR__ . '/../../includes/order_logger.php';

// Строгая проверка IP удалена. Вместо нее используется проверка статуса платежа через API ЮKassa.

// Получаем тело запроса
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (!$data) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Bad Request: Invalid JSON';
    exit;
}

// Логирование входящего события (для отладки и аудита)
error_log(sprintf(
    '[Yookassa Webhook] Received event: %s, Payment ID: %s',
    $data['event'] ?? 'unknown',
    $data['object']['id'] ?? 'none'
));

// Обработка событий от ЮKassa
$event = $data['event'] ?? '';
$paymentObj = $data['object'] ?? [];
$paymentId = $paymentObj['id'] ?? '';
$orderId = $paymentObj['metadata']['order_id'] ?? '';

if ($orderId) {
    logOrderEvent($orderId, 'webhook_received', [
        'event' => $event,
        'payment_id' => $paymentId,
        'payment_status' => $paymentObj['status'] ?? 'unknown'
    ]);
}

global $pdo;

switch ($event) {
    /**
     * payment.succeeded — платеж успешно завершен
     * Запускаем процесс отправки заказа в 5Post и Google Sheets
     */
    case 'payment.succeeded':
        $status = $paymentObj['status'] ?? '';
        
        if ($orderId && $status === 'succeeded' && $paymentId) {
            // Проверка подлинности платежа через API ЮKassa
            $shopId = env_get('YOOKASSA_SHOP_ID');
            $secretKey = env_get('YOOKASSA_SECRET_KEY');
            
            if (empty($shopId) || empty($secretKey)) {
                error_log("[Yookassa Webhook] Error: YooKassa credentials not configured");
                break;
            }

            $ch = curl_init("https://api.yookassa.ru/v3/payments/{$paymentId}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $shopId . ':' . $secretKey);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $apiPayment = json_decode($response, true);
                if (isset($apiPayment['status']) && $apiPayment['status'] === 'succeeded') {
                    error_log("[Yookassa Webhook] Payment {$paymentId} successfully verified via API");
                    logOrderEvent($orderId, 'webhook_verified', ['status' => 'paid', 'api_response' => $apiPayment]);
                    // Запускаем процесс отправки в 5Post и Google Sheets
                    processPaidOrder($pdo, $orderId, $paymentId);
                } else {
                    error_log("[Yookassa Webhook] Verification failed for payment {$paymentId}: API status is not succeeded");
                    logOrderEvent($orderId, 'webhook_verification_failed', ['api_response' => $apiPayment]);
                }
            } else {
                error_log("[Yookassa Webhook] Verification request failed for payment {$paymentId}. HTTP Code: {$httpCode}");
                logOrderEvent($orderId, 'webhook_verification_error', ['http_code' => $httpCode, 'response' => $response]);
            }
        }
        break;
    
    /**
     * payment.canceled — платеж был отменен
     * Обновляем статус заказа в базе данных
     */
    case 'payment.canceled':
        if ($orderId) {
            try {
                $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'canceled', updated_at = NOW() WHERE order_id = :oid");
                $stmt->execute([':oid' => $orderId]);
                error_log("[Yookassa Webhook] Order {$orderId} marked as canceled");
            } catch (PDOException $e) {
                error_log("[Yookassa Webhook] Error updating order {$orderId}: " . $e->getMessage());
            }
        }
        break;
    
    /**
     * refund.succeeded — возврат средств успешно выполнен
     * Обновляем статус заказа и логируем возврат
     * 
     * Согласно 54-ФЗ, при возврате должен быть сформирован чек возврата прихода.
     * ЮKassa автоматически формирует чек, если при создании возврата был передан объект receipt.
     */
    case 'refund.succeeded':
        $refundObj = $paymentObj; // В событии refund объект содержит данные о возврате
        $refundId = $refundObj['id'] ?? '';
        $refundAmount = $refundObj['amount']['value'] ?? '0';
        $refundStatus = $refundObj['status'] ?? '';
        
        if ($orderId && $refundStatus === 'succeeded') {
            try {
                // Обновляем статус заказа или создаем запись о возврате
                // В зависимости от бизнес-логики можно установить флаг возврата
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
                    ':refund_amount' => $refundAmount,
                    ':refund_id' => $refundId,
                    ':oid' => $orderId
                ]);
                
                error_log(sprintf(
                    "[Yookassa Webhook] Refund succeeded for order %s. Refund ID: %s, Amount: %s RUB",
                    $orderId,
                    $refundId,
                    $refundAmount
                ));
            } catch (PDOException $e) {
                error_log("[Yookassa Webhook] Error processing refund for order {$orderId}: " . $e->getMessage());
            }
        }
        break;
    
    /**
     * refund.waiting — возврат ожидает подтверждения
     * Можно использовать для информирования клиента
     */
    case 'refund.waiting':
        if ($orderId) {
            error_log("[Yookassa Webhook] Refund waiting for order {$orderId}");
            // При необходимости можно обновить статус заказа на 'refund_pending'
        }
        break;
    
    default:
        // Игнорируем неизвестные события, но логируем их
        error_log(sprintf(
            '[Yookassa Webhook] Unhandled event type: %s',
            $event
        ));
        break;
}

// Всегда возвращаем 200 OK для ЮKassa, чтобы они не отправляли уведомление повторно
// Согласно документации, любой 2xx код считается успешным подтверждением получения
http_response_code(200);
header('Content-Type: text/plain');
echo "OK";
