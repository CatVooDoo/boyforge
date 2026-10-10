<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/order_logic.php';
require_once __DIR__ . '/../../includes/order_logger.php';

$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (!$data) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Bad Request: Invalid JSON';
    exit;
}

error_log(sprintf(
    '[Yookassa Webhook] Received event: %s, Payment ID: %s',
    $data['event'] ?? 'unknown',
    $data['object']['id'] ?? 'none'
));

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
    
    case 'payment.succeeded':
        $status = $paymentObj['status'] ?? '';
        
        if ($orderId && $status === 'succeeded' && $paymentId) {
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

    case 'refund.succeeded':
        $refundObj = $paymentObj;
        $refundId = $refundObj['id'] ?? '';
        $refundAmount = $refundObj['amount']['value'] ?? '0';
        $refundStatus = $refundObj['status'] ?? '';
        
        if ($orderId && $refundStatus === 'succeeded') {
            try {
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

    case 'refund.waiting':
        if ($orderId) {
            error_log("[Yookassa Webhook] Refund waiting for order {$orderId}");
        }
        break;
    
    default:
        error_log(sprintf(
            '[Yookassa Webhook] Unhandled event type: %s',
            $event
        ));
        break;
}

http_response_code(200);
header('Content-Type: text/plain');
echo "OK";
