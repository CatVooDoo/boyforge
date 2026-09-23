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
$orderId = trim($input['order_id'] ?? '');
$markCode = trim($input['mark_code'] ?? '');

if (empty($orderId)) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует order_id']);
    exit;
}

// Код маркировки требуется только для маркированных товаров
// Если товар не маркирован, mark_code может быть пустым

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

    if ($order['receipt_sent'] == 1) {
        throw new Exception('Закрывающий чек для этого заказа уже был отправлен.');
    }

    $shopId = env_get('YOOKASSA_SHOP_ID');
    $secretKey = env_get('YOOKASSA_SECRET_KEY');

    if (empty($shopId) || empty($secretKey)) {
        throw new Exception('Ключи ЮKassa не настроены.');
    }

    /**
     * Подготовка чека "Полный расчет" (отгрузка с маркировкой)
     * 
     * ТРЕБОВАНИЯ ФФД 1.2 ДЛЯ МАРКИРОВАННЫХ ТОВАРОВ:
     * 1. parameter "internet" должен быть равен true для всех интернет-магазинов
     * 2. parameter "timezone" обязателен и должен соответствовать часовому поясу ККТ (Москва = 2)
     * 3. Для маркированных товаров payment_subject ОБЯЗАН быть "marked" (а не "commodity")
     * 4. В settlements указывается тип "prepayment" на сумму ранее принятого аванса
     */
    
    // 1 = ОСН по умолчанию (Общая система налогообложения)
    $taxSystemCode = (int) (env_get('YOOKASSA_TAX_SYSTEM') ?: '1');
    // 1 = Без НДС по умолчанию (согласно документации ЮKassa)
    $vatCode = (int) (env_get('YOOKASSA_VAT_CODE') ?: '1');
    
    $email = !empty($order['email']) ? $order['email'] : 'no-reply@boyforge.com';
    
    // Форматируем сумму для API ЮKassa (строка с 2 знаками после запятой)
    $amountValue = number_format((float)$order['price'], 2, '.', '');

    /**
     * ОПРЕДЕЛЕНИЕ ТИПА ТОВАРА ДЛЯ ФФД
     * Если передан код маркировки (gs_1m), товар считается маркированным
     * и требует special реквизитов в чеке
     */
    $isMarked = !empty($markCode);
    
    // Для маркированных товаров payment_subject ОБЯЗАН быть "marked"
    // Это требование ФФД 1.2 для товаров подлежащих обязательной маркировке
    $paymentSubject = $isMarked ? 'marked' : 'commodity';

    $receiptPayload = [
        /**
         * ТРЕБОВАНИЕ ФФД 1.2: internet=true обязательно для интернет-магазинов
         * Указывает, что чек сформирован при расчетах в интернете
         */
        'internet' => true,
        
        /**
         * ТРЕБОВАНИЕ ФФД 1.2: timezone обязателен для чеков с маркировкой
         * Часовой пояс ККТ (используется формат IANA или номер от -12 до +14)
         * 2 = Москва (UTC+3, но в формате ЮKassa это значение 2)
         * Согласно документации: https://yookassa.ru/developers/api#receipt_object
         */
        'timezone' => 2, // Москва
        
        'customer' => [
            'email' => $email
        ],
        'type' => 'payment', // Приход
        'send' => true,
        'payment_id' => $order['payment_transaction_id'],
        
        /**
         * TAX SYSTEM CODE (система налогообложения):
         * 1 = ОСН (общая)
         * 2 = УСН (упрощенная)
         * 3 = ЕНВД
         * 4 = ЕСН
         * 5 = Патент
         * 6 = АУСН
         */
        'tax_system_code' => $taxSystemCode,
        
        /**
         * SETTLEMENTS (взаиморасчеты):
         * Для сценария "Аванс + Зачет предоплаты":
         * - При оплате аванса: type = "prepayment", amount = сумма аванса
         * - При отгрузке (этот чек): type = "prepayment", amount = сумма зачета
         * 
         * Важно: sum всех settlements должна равняться общей сумме чека
         */
        'settlements' => [
            [
                /**
                 * Тип "prepayment" означает зачет ранее внесенного аванса
                 * Это соответствует сценарию двухстадийного платежа:
                 * 1. Клиент оплатил аванс (первый чек с payment_mode="advance")
                 * 2. Теперь происходит отгрузка товара (зачет предоплаты)
                 */
                'type' => 'prepayment',
                'amount' => [
                    'value' => $amountValue,
                    'currency' => 'RUB'
                ]
            ]
        ],
        'items' => [
            [
                'description' => mb_substr('Отгрузка: ' . $order['product_name'] . ' (' . $order['size'] . ')', 0, 128),
                'quantity' => '1.000',
                'amount' => [
                    'value' => $amountValue,
                    'currency' => 'RUB'
                ],
                'vat_code' => $vatCode,
                
                /**
                 * PAYMENT SUBJECT (признак предмета расчета) по ФФД 1.2:
                 * - "marked" — для маркированных товаров (обязательно при наличии кода маркировки)
                 * - "commodity" — для обычных товаров
                 * 
                 * Нарушение этого требования ведет к штрафу по ст. 14.5 КоАП РФ
                 */
                'payment_subject' => $paymentSubject,
                
                /**
                 * PAYMENT MODE (признак способа расчета):
                 * "full_payment" — полный расчет (означает зачет предоплаты при отгрузке)
                 * 
                 * Возможные значения:
                 * - "full_payment" — полный расчет
                 * - "advance" — аванс
                 * - "partial_payment" — частичный расчет и кредит
                 * - "credit" — кредит
                 * - "credit_payment" — оплата кредита
                 */
                'payment_mode' => 'full_payment',
                
                /**
                 * MARK CODE INFO (код маркировки):
                 * Передается только для маркированных товаров
                 * Формат: gs_1m — код в формате GS1 DataMatrix (13 символов)
                 * 
                 * Требование ФФД 1.2: наличие кода маркировки обязательно
                 * для товаров подлежащих обязательной маркировке (обувь, одежда, шины и т.д.)
                 */
                'mark_code_info' => [
                    'gs_1m' => $markCode // GS1M код (обязателен для маркированных товаров)
                ],
                
                /**
                 * MEASURE (единица измерения):
                 * "piece" — штука (наиболее распространенное для одежды)
                 * 
                 * Другие варианты: "kg", "g", "cm", "m", "ml", "l" и т.д.
                 */
                'measure' => 'piece'
            ]
        ]
    ];
    
    // Если товар не маркирован, удаляем mark_code_info из payload
    if (!$isMarked) {
        unset($receiptPayload['items'][0]['mark_code_info']);
    }
    
    $url = 'https://api.yookassa.ru/v3/receipts';
    $ch = curl_init($url);
    
    /**
     * IDEMPOTENCE KEY (ключ идемпотентности):
     * Гарантирует, что повторная отправка того же запроса не создаст дубликат чека
     * Формат: любая уникальная строка (рекомендуется использовать префикс + order_id)
     */
    $idempotenceKey = 'receipt_' . $order['order_id'];
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic " . base64_encode("{$shopId}:{$secretKey}"),
            "Idempotence-Key: {$idempotenceKey}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($receiptPayload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        throw new Exception('Ошибка сети при отправке в ЮKassa: ' . $curlErr);
    }

    $resData = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($resData['id'])) {
        // Успешно отправлено
        $upd = $pdo->prepare("UPDATE orders SET receipt_sent = 1, mark_code = :mc WHERE id = :id");
        $upd->execute([
            ':mc' => $markCode,
            ':id' => $order['id']
        ]);
        
        error_log(sprintf(
            '[Receipt] Closing receipt sent for order %s. Receipt ID: %s, Marked: %s',
            $orderId,
            $resData['id'],
            $isMarked ? 'yes' : 'no'
        ));
        
        echo json_encode([
            'success' => true,
            'receipt_id' => $resData['id'],
            'marked' => $isMarked
        ]);
    } else {
        throw new Exception('Ошибка ЮKassa (' . $httpCode . '): ' . ($resData['description'] ?? $response));
    }

} catch (Throwable $e) {
    error_log('[Receipt] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
