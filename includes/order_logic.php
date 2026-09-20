<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fivepost.php';

/**
 * Сохраняет или обновляет заказ в БД
 */
function saveOrderToDb(PDO $pdo, array $data, string $paymentStatus, string $transactionId = ''): void {
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_id, client_order_id, product_id, product_name, price, gender, size,
            fio, phone, tg_username, email, fivepost_point_id, fivepost_point_name, fivepost_point_address,
            fivepost_point_type, payment_status, payment_transaction_id, created_at
        ) VALUES (
            :order_id, :client_order_id, :product_id, :product_name, :price, :gender, :size,
            :fio, :phone, :tg_username, :email, :fivepost_point_id, :fivepost_point_name, :fivepost_point_address,
            :fivepost_point_type, :payment_status, :payment_transaction_id, NOW()
        )
        ON DUPLICATE KEY UPDATE
            payment_status = VALUES(payment_status),
            payment_transaction_id = COALESCE(NULLIF(VALUES(payment_transaction_id), ''), payment_transaction_id),
            email = COALESCE(NULLIF(VALUES(email), ''), email),
            updated_at = NOW()
    ");

    $numPrice = (float)preg_replace('/[^\d.]/', '', (string)($data['price'] ?? '3200')) ?: 3200.0;

    $stmt->execute([
        ':order_id'               => $data['orderId'],
        ':client_order_id'        => $data['orderId'],
        ':product_id'             => $data['productId'] ?? 'BF-1',
        ':product_name'           => $data['productName'] ?? 'Товар BOYFORGE',
        ':price'                  => $numPrice,
        ':gender'                 => $data['gender'] ?? '',
        ':size'                   => $data['size'] ?? '',
        ':fio'                    => $data['fio'] ?? '',
        ':phone'                  => $data['phone'] ?? '',
        ':tg_username'            => $data['tgUsername'] ?? '',
        ':email'                  => $data['email'] ?? '',
        ':fivepost_point_id'      => $data['fivepostPointId'] ?? '',
        ':fivepost_point_name'    => $data['fivepostPointName'] ?? '',
        ':fivepost_point_address' => $data['fivepostPointAddress'] ?? '',
        ':fivepost_point_type'    => $data['fivepostPointType'] ?? '',
        ':payment_status'         => $paymentStatus,
        ':payment_transaction_id' => $transactionId
    ]);
}

/**
 * Обрабатывает оплаченный заказ: отправляет в 5Post и Google Sheets
 */
function processPaidOrder(PDO $pdo, string $orderId, string $transactionId): void {
    $lockName = 'bf_order_' . md5($orderId);
    $lockAcquired = false;

    try {
        $lockStmt = $pdo->prepare("SELECT GET_LOCK(:lock_name, 15)");
        $lockStmt->execute([':lock_name' => $lockName]);
        $lockAcquired = ((int)$lockStmt->fetchColumn()) === 1;
    } catch (Throwable $e) {}

    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :oid");
        $stmt->execute([':oid' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return;
        }

        $alreadyIn5Post = !empty($order['fivepost_status']) && in_array($order['fivepost_status'], ['CREATED', 'PROCESSING']);
        $alreadyInSheets = !empty($order['google_sheets_sent']);

        // 1. Отправка в 5Post
        if (!$alreadyIn5Post) {
            $pdo->prepare("UPDATE orders SET fivepost_status = 'PROCESSING', payment_status = 'paid', payment_transaction_id = :tx WHERE order_id = :oid AND (fivepost_status IS NULL OR fivepost_status = 'PENDING')")
                ->execute([':oid' => $orderId, ':tx' => $transactionId]);

            $fpClient = new FivePostClient();
            $c2cResponse = $fpClient->createC2COrder([
                'order_id'          => $order['order_id'],
                'client_order_id'   => $order['client_order_id'],
                'sender_cargo_id'   => $order['order_id'] . '-1',
                'fivepost_point_id' => $order['fivepost_point_id'],
                'fio'               => $order['fio'],
                'phone'             => $order['phone'],
                'product_name'      => $order['product_name'],
                'product_id'        => $order['product_id'],
                'price'             => (float)$order['price'],
                'weight_g'          => 350
            ]);

            if (!empty($c2cResponse['success'])) {
                $upd = $pdo->prepare("
                    UPDATE orders SET 
                        fivepost_order_id = :f_oid,
                        fivepost_cargo_id = :f_cid,
                        fivepost_barcode  = :f_bc,
                        fivepost_status   = 'CREATED',
                        fivepost_http_code = :hcode,
                        fivepost_request_payload = :payload,
                        fivepost_raw_response = :raw
                    WHERE order_id = :oid
                ");
                $upd->execute([
                    ':f_oid'   => $c2cResponse['orderId'],
                    ':f_cid'   => $c2cResponse['cargoId'],
                    ':f_bc'    => $c2cResponse['barcode'],
                    ':hcode'   => (int)($c2cResponse['http_code'] ?? 200),
                    ':payload' => json_encode($c2cResponse['payload'] ?? [], JSON_UNESCAPED_UNICODE),
                    ':raw'     => is_string($c2cResponse['raw']) ? $c2cResponse['raw'] : json_encode($c2cResponse['raw'], JSON_UNESCAPED_UNICODE),
                    ':oid'     => $orderId
                ]);
                $order['fivepost_order_id'] = $c2cResponse['orderId'];
                $order['fivepost_barcode'] = $c2cResponse['barcode'];
            } else {
                $fallbackBarcode = '5P-' . strtoupper(substr(md5($orderId), 0, 10));
                $upd = $pdo->prepare("
                    UPDATE orders SET 
                        fivepost_barcode         = :f_bc,
                        fivepost_status          = 'PENDING_REGISTRATION',
                        fivepost_http_code       = :hcode,
                        fivepost_request_payload = :payload,
                        fivepost_raw_response    = :raw
                    WHERE order_id = :oid
                ");
                $upd->execute([
                    ':f_bc'    => $fallbackBarcode,
                    ':hcode'   => (int)($c2cResponse['http_code'] ?? 0),
                    ':payload' => json_encode($c2cResponse['payload'] ?? [], JSON_UNESCAPED_UNICODE),
                    ':raw'     => is_string($c2cResponse['raw'] ?? null) ? $c2cResponse['raw'] : json_encode($c2cResponse, JSON_UNESCAPED_UNICODE),
                    ':oid'     => $orderId
                ]);
                $order['fivepost_barcode'] = $fallbackBarcode;
            }
        }

        // 2. Отправка в Google Sheets
        if (!$alreadyInSheets) {
            $googleScriptUrl = env_get('GOOGLE_SCRIPT_URL');
            if (!empty($googleScriptUrl)) {
                $pointTypeRu = ($order['fivepost_point_type'] === 'POSTAMAT') ? 'Постамат' : (($order['fivepost_point_type'] === 'TOBACCO') ? 'Касса' : 'ПВЗ');
                $safePhone = (strpos($order['phone'], '+') === 0) ? ("'" . $order['phone']) : $order['phone'];
                
                $contactInfo = !empty($order['email']) ? $order['email'] : $order['tg_username'];

                $googlePayload = [
                    'date'                 => date('d.m.Y H:i:s'),
                    'orderId'              => $orderId,
                    'fivepostOrderId'      => $order['fivepost_order_id'] ?? '—',
                    'fivepostBarcode'      => $order['fivepost_barcode'] ?? '—',
                    'fio'                  => $order['fio'],
                    'phone'                => $safePhone,
                    'tgUsername'           => $contactInfo,
                    'fivepostPointAddress' => $order['fivepost_point_address'] . " ({$pointTypeRu})",
                    'productName'          => $order['product_name'],
                    'gender'               => $order['gender'],
                    'size'                 => $order['size'],
                    'price'                => number_format((float)$order['price'], 0, '', ' ') . ' ₽',
                    'transactionId'        => "#{$transactionId}",
                    'status'               => 'Оплачен (ЮKassa), сформирован 5Post'
                ];

                $ch = curl_init($googleScriptUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode($googlePayload, JSON_UNESCAPED_UNICODE),
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0
                ]);
                $gResponse = curl_exec($ch);
                $gHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($gHttpCode === 200 || $gHttpCode === 302) {
                    $pdo->prepare("UPDATE orders SET google_sheets_sent = 1 WHERE order_id = :oid")->execute([':oid' => $orderId]);
                }
            }
        }
    } finally {
        if ($lockAcquired) {
            try {
                $pdo->prepare("SELECT RELEASE_LOCK(:lock_name)")->execute([':lock_name' => $lockName]);
            } catch (Throwable $e) {}
        }
    }
}
