<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/order_logic.php';

/**
 * Белый список IP-адресов ЮKassa для аутентификации webhook-уведомлений.
 * Согласно официальной документации: https://yookassa.ru/developers/notifications
 */
$yookassaIpWhitelist = [
    '185.71.76.0/27',
    '185.71.77.0/27',
    '77.75.153.0/25',
    '77.75.156.11',
    '77.75.156.35',
    '77.75.154.128/25',
    '2a02:5180::/32', // IPv6 диапазон
];

/**
 * Проверяет, входит ли IP-адрес отправителя в белый список ЮKassa.
 * Поддерживает как IPv4, так и IPv6 диапазоны (CIDR).
 *
 * @param string $ip IP-адрес отправителя
 * @param array $whitelist Список разрешенных IP/CIDR
 * @return bool true если IP разрешен
 */
function isYookassaIp(string $ip, array $whitelist): bool {
    // Нормализуем IP (убираем возможные пробелы)
    $ip = trim($ip);
    
    foreach ($whitelist as $allowed) {
        $allowed = trim($allowed);
        
        // Проверка на наличие CIDR нотации
        if (strpos($allowed, '/') !== false) {
            list($subnet, $mask) = explode('/', $allowed, 2);
            $subnet = trim($subnet);
            $mask = (int)trim($mask);
            
            // Определяем тип адреса (IPv4 или IPv6)
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // IPv4 проверка
                if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    continue;
                }
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);
                $maskLong = -1 << (32 - $mask);
                
                if (($ipLong & $maskLong) === ($subnetLong & $maskLong)) {
                    return true;
                }
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                // IPv6 проверка (упрощенная, для диапазона 2a02:5180::/32)
                if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    continue;
                }
                $ipBin = inet_pton($ip);
                $subnetBin = inet_pton($subnet);
                
                if ($ipBin === false || $subnetBin === false) {
                    continue;
                }
                
                // Сравниваем первые $mask бит (для /32 это первые 4 байта)
                $bytesToCheck = intdiv($mask, 8);
                $match = true;
                for ($i = 0; $i < $bytesToCheck; $i++) {
                    if ($ipBin[$i] !== $subnetBin[$i]) {
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    return true;
                }
            }
        } else {
            // Точное совпадение IP (без CIDR)
            if ($ip === $allowed) {
                return true;
            }
            // Также проверяем как IPv4 и IPv6 для совместимости
            if (filter_var($ip, FILTER_VALIDATE_IP) && filter_var($allowed, FILTER_VALIDATE_IP)) {
                if ($ip === $allowed) {
                    return true;
                }
            }
        }
    }
    
    return false;
}

// Получаем IP-адрес отправителя (учитываем возможные прокси)
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

// Строгая проверка IP перед обработкой webhook
if (!isYookassaIp($clientIp, $yookassaIpWhitelist)) {
    // Логируем попытку несанкционированного доступа
    error_log(sprintf(
        '[Yookassa Webhook] Blocked request from unauthorized IP: %s',
        $clientIp
    ));
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden: Unauthorized IP address';
    exit;
}

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

global $pdo;

switch ($event) {
    /**
     * payment.succeeded — платеж успешно завершен
     * Запускаем процесс отправки заказа в 5Post и Google Sheets
     */
    case 'payment.succeeded':
        $status = $paymentObj['status'] ?? '';
        
        if ($orderId && $status === 'succeeded') {
            // Запускаем процесс отправки в 5Post и Google Sheets
            processPaidOrder($pdo, $orderId, $paymentId);
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
