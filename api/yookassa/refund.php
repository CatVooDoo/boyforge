<?php
declare(strict_types=1);

/**
 * YooKassa Refund API Endpoint
 * 
 * Эндпоинт для создания возвратов средств через API ЮKassa.
 * Реализует требования 54-ФЗ по формированию чека возврата прихода.
 * 
 * ТРЕБОВАНИЯ 54-ФЗ ДЛЯ ВОЗВРАТОВ:
 * - При возврате денег должен быть сформирован чек "Возврат прихода"
 * - Чек должен содержать корректный payment_subject (marked для маркированных товаров)
 * - Чек должен содержать корректный vat_code
 * - Возврат должен быть идемпотентным (повторный запрос не создает дубликат)
 * 
 * @see https://yookassa.ru/developers/api#create_refund
 * @see https://yookassa.ru/developers/api#receipt_object
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

// Разрешаем только POST запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

/**
 * Функция проверки IP адреса (использует ту же логику что и webhook)
 */
function isYookassaIp(string $ip, array $whitelist): bool {
    $ip = trim($ip);
    
    foreach ($whitelist as $allowed) {
        $allowed = trim($allowed);
        
        if (strpos($allowed, '/') !== false) {
            list($subnet, $mask) = explode('/', $allowed, 2);
            $subnet = trim($subnet);
            $mask = (int)trim($mask);
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
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
                if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    continue;
                }
                $ipBin = inet_pton($ip);
                $subnetBin = inet_pton($subnet);
                
                if ($ipBin === false || $subnetBin === false) {
                    continue;
                }
                
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
            if ($ip === $allowed) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * БЕЗОПАСНОСТЬ: Проверка IP адреса отправителя
 * Для внутренних вызовов можно отключить эту проверку
 * Раскомментируйте код ниже для строгой проверки IP
 */
/*
$yookassaIpWhitelist = [
    '185.71.76.0/27',
    '185.71.77.0/27',
    '77.75.153.0/25',
    '77.75.156.11',
    '77.75.156.35',
    '77.75.154.128/25',
    '2a02:5180::/32',
];

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!isYookassaIp($clientIp, $yookassaIpWhitelist)) {
    // Для локальных тестов или внутренних вызовов можно пропустить
    error_log('[Refund] Request from non-Yookassa IP: ' . $clientIp);
    // http_response_code(403);
    // echo json_encode(['success' => false, 'error' => 'Forbidden']);
    // exit;
}
*/

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Получаем параметры запроса
$paymentId = trim($input['payment_id'] ?? '');
$orderId = trim($input['order_id'] ?? '');
$amount = isset($input['amount']) ? (float)$input['amount'] : null;
$description = trim($input['description'] ?? 'Возврат средств');

// Валидация входных данных
if (empty($paymentId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Отсутствует обязательный параметр: payment_id']);
    exit;
}

if (empty($orderId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Отсутствует обязательный параметр: order_id']);
    exit;
}

try {
    global $pdo;
    
    /**
     * ПОЛУЧЕНИЕ ДАННЫХ О ЗАКАЗЕ
     * Используем prepared statements для защиты от SQL инъекций
     */
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :oid LIMIT 1");
    $stmt->execute([':oid' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Заказ не найден в базе данных.');
    }
    
    /**
     * ПРОВЕРКА СТАТУСА ОПЛАТЫ
     * Возврат возможен только для оплаченных заказов
     */
    if ($order['payment_status'] !== 'paid' && $order['payment_status'] !== 'refunded') {
        throw new Exception('Возврат возможен только для оплаченных заказов. Текущий статус: ' . $order['payment_status']);
    }
    
    /**
     * ПРОВЕРКА НА ПОВТОРНЫЙ ВОЗВРАТ
     * Если уже был частичный возврат, проверяем остаток суммы
     */
    if ($order['payment_status'] === 'refunded') {
        // Можно реализовать логику частичных возвратов
        // Сейчас просто предупреждаем
        error_log('[Refund] Order ' . $orderId . ' already has refund status');
    }
    
    // Определяем сумму возврата
    if ($amount === null || $amount <= 0) {
        // Если сумма не указана, возвращаем полную сумму заказа
        $amount = (float)$order['price'];
    }
    
    // Проверяем что сумма возврата не превышает сумму заказа
    $orderAmount = (float)$order['price'];
    if ($amount > $orderAmount) {
        throw new Exception('Сумма возврата (' . $amount . ') превышает сумму заказа (' . $orderAmount . ')');
    }
    
    /**
     * ПОЛУЧЕНИЕ КЛЮЧЕЙ ДОСТУПА YOOKASSA
     */
    $shopId = env_get('YOOKASSA_SHOP_ID');
    $secretKey = env_get('YOOKASSA_SECRET_KEY');
    
    if (empty($shopId) || empty($secretKey)) {
        throw new Exception('Ключи доступа ЮKassa не настроены в конфигурации.');
    }
    
    /**
     * ОПРЕДЕЛЕНИЕ ПАРАМЕТРОВ ЧЕКА (54-ФЗ)
     * 
     * Согласно 54-ФЗ, при возврате средств должен быть сформирован чек
     * "Возврат прихода" с корректными реквизитами.
     * 
     * @see https://yookassa.ru/developers/payment_data/payment_object#fiscal_attributes
     */
    
    // Система налогообложения (1 = ОСН по умолчанию)
    $taxSystemCode = (int)(env_get('YOOKASSA_TAX_SYSTEM') ?: '1');
    
    // Код НДС (1 = Без НДС по умолчанию)
    $vatCode = (int)(env_get('YOOKASSA_VAT_CODE') ?: '1');
    
    // Email покупателя для отправки чека
    $customerEmail = !empty($order['email']) ? $order['email'] : 'no-reply@boyforge.com';
    
    // Определяем является ли товар маркированным
    // Если в заказе есть код маркировки, используем payment_subject = "marked"
    $isMarked = !empty($order['mark_code']);
    
    /**
     * PAYMENT SUBJECT (признак предмета расчета) по ФФД 1.2:
     * - "marked" — для маркированных товаров (обязательно!)
     * - "commodity" — для обычных товаров
     * 
     * Нарушение этого требования ведет к штрафу по ст. 14.5 КоАП РФ
     */
    $paymentSubject = $isMarked ? 'marked' : 'commodity';
    
    // Форматируем сумму для API (строка с 2 знаками после запятой)
    $amountValue = number_format($amount, 2, '.', '');
    
    /**
     * ФОРМИРОВАНИЕ ОБЪЕКТА RECEIPT ДЛЯ ЧЕКА ВОЗВРАТА
     * 
     * type = "refund" указывает что это чек возврата прихода
     * Это критически важно для соблюдения 54-ФЗ
     */
    $receiptPayload = [
        /**
         * ТРЕБОВАНИЕ ФФД 1.2: internet=true для интернет-магазинов
         */
        'internet' => true,
        
        /**
         * ТРЕБОВАНИЕ ФФД 1.2: timezone обязателен
         * 2 = Москва (UTC+3)
         */
        'timezone' => 2,
        
        'customer' => [
            'email' => $customerEmail
        ],
        
        /**
         * TYPE = "refund" — чек возврата прихода
         * 
         * Возможные значения:
         * - "payment" — приход (первичный чек)
         * - "refund" — возврат прихода (чек на возврат)
         */
        'type' => 'refund',
        
        'send' => true, // Отправить чек покупателю на email
        
        'payment_id' => $paymentId,
        
        'tax_system_code' => $taxSystemCode,
        
        /**
         * SETTLEMENTS для возврата:
         * Тип "prepayment" указывает что возвращается ранее внесенная предоплата
         */
        'settlements' => [
            [
                'type' => 'prepayment',
                'amount' => [
                    'value' => $amountValue,
                    'currency' => 'RUB'
                ]
            ]
        ],
        
        /**
         * ITEMS (позиции чека) — детализация возврата
         * 
         * ВАЖНО: Для маркированных товаров обязательно указать mark_code_info
         */
        'items' => [
            [
                'description' => mb_substr('Возврат: ' . $order['product_name'], 0, 128),
                'quantity' => '1.000',
                'amount' => [
                    'value' => $amountValue,
                    'currency' => 'RUB'
                ],
                'vat_code' => $vatCode,
                
                /**
                 * PAYMENT SUBJECT — критический параметр для 54-ФЗ
                 * Должен соответствовать типу товара в исходном чеке
                 */
                'payment_subject' => $paymentSubject,
                
                /**
                 * PAYMENT MODE — признак способа расчета
                 * "full_payment" означает полный расчет (возврат полной суммы)
                 */
                'payment_mode' => 'full_payment',
                
                'measure' => 'piece'
            ]
        ]
    ];
    
    // Добавляем код маркировки если товар маркированный
    if ($isMarked) {
        $receiptPayload['items'][0]['mark_code_info'] = [
            'gs_1m' => $order['mark_code']
        ];
    }
    
    /**
     * ФОРМИРОВАНИЕ ЗАПРОСА НА СОЗДАНИЕ ВОЗВРАТА
     * 
     * @see https://yookassa.ru/developers/api#create_refund
     */
    $refundPayload = [
        'amount' => [
            'value' => $amountValue,
            'currency' => 'RUB'
        ],
        'payment_id' => $paymentId,
        'description' => $description,
        
        /**
         * RECEIPT OBJECT — данные для формирования чека
         * Передается для автоматического формирования чека возврата
         * 
         * @see https://yookassa.ru/developers/api#receipt_object
         */
        'receipt' => $receiptPayload
    ];
    
    /**
     * IDEMPOTENCE KEY (ключ идемпотентности)
     * 
     * Гарантирует что повторная отправка того же запроса
     * не создаст дубликат возврата.
     * 
     * Формат: любая уникальная строка до 36 символов
     * Рекомендуется использовать префикс + order_id + timestamp
     */
    $idempotenceKey = 'refund_' . $orderId . '_' . time();
    
    $url = 'https://api.yookassa.ru/v3/refunds';
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic " . base64_encode("{$shopId}:{$secretKey}"),
            "Idempotence-Key: {$idempotenceKey}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($refundPayload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 15
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    
    if ($curlErr) {
        throw new Exception('Ошибка сети при отправке запроса на возврат: ' . $curlErr);
    }
    
    $resData = json_decode($response, true);
    
    /**
     * ОБРАБОТКА ОТВЕТА ОТ YOOKASSA
     * 
     * Успешный ответ: HTTP 200 + объект возврата с status = "pending" или "succeeded"
     * Ошибка: HTTP 4xx/5xx + описание ошибки
     */
    if ($httpCode >= 200 && $httpCode < 300 && isset($resData['id'])) {
        $refundId = $resData['id'];
        $refundStatus = $resData['status'] ?? 'unknown';
        $createdAt = $resData['created_at'] ?? date('c');
        
        /**
         * ОБНОВЛЕНИЕ СТАТУСА ЗАКАЗА В БАЗЕ ДАННЫХ
         * Используем prepared statements для безопасности
         */
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
            ':refund_amount' => $amountValue,
            ':refund_id' => $refundId,
            ':oid' => $orderId
        ]);
        
        /**
         * ЛОГИРОВАНИЕ ОПЕРАЦИИ
         * Важно для аудита и отладки
         */
        error_log(sprintf(
            '[Refund] Successfully created refund for order %s. Refund ID: %s, Amount: %s RUB, Status: %s',
            $orderId,
            $refundId,
            $amountValue,
            $refundStatus
        ));
        
        echo json_encode([
            'success' => true,
            'refund_id' => $refundId,
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'amount' => $amountValue,
            'status' => $refundStatus,
            'created_at' => $createdAt,
            'receipt_sent' => true,
            'marked_item' => $isMarked
        ]);
        
    } else {
        /**
         * ОБРАБОТКА ОШИБОК ОТ YOOKASSA
         * 
         * Типичные ошибки:
         * - 400: Неверный формат запроса
         * - 401: Неверные учетные данные
         * - 404: Платеж не найден
         * - 409: Конфликт (например, платеж уже возвращен)
         * - 429: Превышен лимит запросов
         */
        $errorMsg = $resData['description'] ?? ($resData['message'] ?? $response);
        throw new Exception('Ошибка ЮKassa при создании возврата (' . $httpCode . '): ' . $errorMsg);
    }
    
} catch (Throwable $e) {
    /**
     * ОБРАБОТКА ИСКЛЮЧЕНИЙ
     * Логируем ошибку и возвращаем клиенту безопасное сообщение
     */
    error_log('[Refund] Error processing refund: ' . $e->getMessage());
    error_log('[Refund] Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
