<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fivepost.php';

$envVars = FivePostClient::loadEnv();
$apiSecret = $envVars['CLOUDPAYMENTS_API_SECRET'] ?? 'c0a9234eb56f475d09c62f83484f768c';
$googleScriptUrl = $envVars['GOOGLE_SCRIPT_URL'] ?? 'https://script.google.com/macros/s/AKfycbwEX5yOenoxiIpkFlt0BGHbV4SPmJiWIrzIFU-0t8R-4lN59vMuTnhMhlAP6ImemV59Fw/exec';

$rawBody = file_get_contents('php://input');

// Проверка HMAC подписи CloudPayments
$hmacHeader = $_SERVER['HTTP_CONTENT_HMAC'] ?? $_SERVER['HTTP_X_CONTENT_HMAC'] ?? '';
if (!empty($hmacHeader) && !empty($apiSecret)) {
    $expectedHmac = base64_encode(hash_hmac('sha256', $rawBody, $apiSecret, true));
    if (!hash_equals($expectedHmac, $hmacHeader)) {
        http_response_code(403);
        echo json_encode(['code' => 13, 'error' => 'Invalid HMAC signature'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$data = [];
if (!empty($_POST)) {
    $data = $_POST;
} else {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

$transactionId = isset($data['TransactionId']) ? (string)$data['TransactionId'] : '';
$amount        = isset($data['Amount']) ? (float)$data['Amount'] : 3200.0;
$status        = isset($data['Status']) ? (string)$data['Status'] : 'Completed';
$orderId       = isset($data['InvoiceId']) ? (string)$data['InvoiceId'] : '';

$customData = [];
if (!empty($data['Data'])) {
    if (is_string($data['Data'])) {
        $customData = json_decode($data['Data'], true) ?: [];
    } elseif (is_array($data['Data'])) {
        $customData = $data['Data'];
    }
}

if (empty($orderId)) {
    $orderId = (string)($customData['orderId'] ?? ('BF-' . strtoupper(substr(md5(uniqid()), 0, 8))));
}

$fio             = (string)($customData['fio'] ?? ($data['Name'] ?? ''));
$phone           = (string)($customData['phone'] ?? ($data['Phone'] ?? ''));
$tgUsername      = (string)($customData['tgUsername'] ?? ($data['AccountId'] ?? ''));
$productName     = (string)($customData['productName'] ?? 'Товар BOYFORGE');
$gender          = (string)($customData['gender'] ?? 'Мужской');
$size            = (string)($customData['size'] ?? 'M');
$fivepostPointId = (string)($customData['fivepostPointId'] ?? '');
$fivepostAddress = (string)($customData['fivepostPointAddress'] ?? '');
$fivepostType    = (string)($customData['fivepostPointType'] ?? 'POSTAMAT');

// Логируем входящее уведомление от CloudPayments
$fpClient = new FivePostClient();
$fpClient->log('CLOUDPAYMENTS_WEBHOOK_RECEIVED', [
    'TransactionId' => $transactionId,
    'Status'        => $status,
    'OrderId'       => $orderId,
    'Amount'        => $amount,
    'CustomData'    => $customData
]);

// Обрабатываем только успешную оплату (Status: Completed)
if ($status === 'Completed') {
    $lockName = 'bf_order_' . md5($orderId);
    $lockAcquired = false;

    try {
        $lockStmt = $pdo->prepare("SELECT GET_LOCK(:lock_name, 15)");
        $lockStmt->execute([':lock_name' => $lockName]);
        $lockAcquired = ((int)$lockStmt->fetchColumn()) === 1;
    } catch (Throwable $e) {
        // В случае ошибки блокировки продолжаем
    }

    try {
        // 1. Проверяем существующий статус заказа в базе (защита от дублирования и обогащение данными)
        $checkStmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :oid");
        $checkStmt->execute([':oid' => $orderId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (empty($fivepostPointId) && !empty($existing['fivepost_point_id'])) {
                $fivepostPointId = (string)$existing['fivepost_point_id'];
            }
            if (empty($fivepostAddress) && !empty($existing['fivepost_point_address'])) {
                $fivepostAddress = (string)$existing['fivepost_point_address'];
            }
            if (empty($fivepostType) && !empty($existing['fivepost_point_type'])) {
                $fivepostType = (string)$existing['fivepost_point_type'];
            }
            if (empty($fio) && !empty($existing['fio'])) {
                $fio = (string)$existing['fio'];
            }
            if (empty($phone) && !empty($existing['phone'])) {
                $phone = (string)$existing['phone'];
            }
            if (empty($productName) && !empty($existing['product_name'])) {
                $productName = (string)$existing['product_name'];
            }
            if (empty($gender) && !empty($existing['gender'])) {
                $gender = (string)$existing['gender'];
            }
            if (empty($size) && !empty($existing['size'])) {
                $size = (string)$existing['size'];
            }
            if (empty($tgUsername) && !empty($existing['tg_username'])) {
                $tgUsername = (string)$existing['tg_username'];
            }
        }

        $fivepostBarcode = $existing['fivepost_barcode'] ?? null;
        $fivepostOrderId = $existing['fivepost_order_id'] ?? null;
        $alreadyIn5Post = !empty($existing['fivepost_status']) && in_array($existing['fivepost_status'], ['CREATED', 'PROCESSING']);
        $alreadyInSheets = !empty($existing['google_sheets_sent']);

        // 2. Обновляем статус оплаты в БД
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                order_id, client_order_id, product_id, product_name, price, gender, size,
                fio, phone, tg_username, fivepost_point_id, fivepost_point_address,
                fivepost_point_type, payment_status, payment_transaction_id, created_at
            ) VALUES (
                :order_id, :client_order_id, 'BF-1', :product_name, :price, :gender, :size,
                :fio, :phone, :tg_username, :fivepost_point_id, :fivepost_point_address,
                :fivepost_point_type, 'paid', :transaction_id, NOW()
            )
            ON DUPLICATE KEY UPDATE 
                payment_status = 'paid',
                payment_transaction_id = COALESCE(NULLIF(VALUES(payment_transaction_id), ''), payment_transaction_id),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':order_id'               => $orderId,
            ':client_order_id'        => $orderId,
            ':product_name'           => $productName,
            ':price'                  => $amount,
            ':gender'                 => $gender,
            ':size'                   => $size,
            ':fio'                    => $fio,
            ':phone'                  => $phone,
            ':tg_username'            => $tgUsername,
            ':fivepost_point_id'      => $fivepostPointId,
            ':fivepost_point_address' => $fivepostAddress,
            ':fivepost_point_type'    => $fivepostType,
            ':transaction_id'         => $transactionId
        ]);

        // 3. Создаем C2C-заказ в 5Post ТОЛЬКО если он еще не был создан
        if (!$alreadyIn5Post && !empty($fivepostPointId)) {
            $pdo->prepare("UPDATE orders SET fivepost_status = 'PROCESSING' WHERE order_id = :oid AND (fivepost_status IS NULL OR fivepost_status = 'PENDING')")->execute([':oid' => $orderId]);

            $c2cRes = $fpClient->createC2COrder([
                'order_id'          => $orderId,
                'client_order_id'   => $orderId,
                'sender_cargo_id'   => $orderId . '-1',
                'fivepost_point_id' => $fivepostPointId,
                'fio'               => $fio,
                'phone'             => $phone,
                'product_name'      => $productName,
                'price'             => $amount,
                'weight_g'          => 350
            ]);

            if (!empty($c2cRes['success'])) {
                $fivepostOrderId = $c2cRes['orderId'];
                $fivepostBarcode = $c2cRes['barcode'];

                $upd = $pdo->prepare("
                    UPDATE orders SET 
                        fivepost_order_id        = :f_oid,
                        fivepost_cargo_id        = :f_cid,
                        fivepost_barcode         = :f_bc,
                        fivepost_status          = 'CREATED',
                        fivepost_http_code       = :hcode,
                        fivepost_request_payload = :payload,
                        fivepost_raw_response    = :raw
                    WHERE order_id = :oid
                ");
                $upd->execute([
                    ':f_oid'   => $c2cRes['orderId'],
                    ':f_cid'   => $c2cRes['cargoId'],
                    ':f_bc'    => $c2cRes['barcode'],
                    ':hcode'   => (int)($c2cRes['http_code'] ?? 200),
                    ':payload' => json_encode($c2cRes['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    ':raw'     => is_string($c2cRes['raw']) ? $c2cRes['raw'] : json_encode($c2cRes['raw'], JSON_UNESCAPED_UNICODE),
                    ':oid'     => $orderId
                ]);
            } else {
                $fallbackBarcode = '5P-' . strtoupper(substr(md5($orderId), 0, 10));
                $fivepostBarcode = $fallbackBarcode;

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
                    ':hcode'   => (int)($c2cRes['http_code'] ?? 0),
                    ':payload' => json_encode($c2cRes['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    ':raw'     => is_string($c2cRes['raw'] ?? null) ? $c2cRes['raw'] : json_encode($c2cRes, JSON_UNESCAPED_UNICODE),
                    ':oid'     => $orderId
                ]);
            }
        }

        // 4. Отправка в Google Таблицу (только если еще не отправлялся)
        if (!$alreadyInSheets && !empty($googleScriptUrl) && strpos($googleScriptUrl, 'script.google.com') !== false) {
            $nowDate = date('d.m.Y H:i:s');
            $pointTypeRu = ($fivepostType === 'POSTAMAT') ? 'Постамат' : (($fivepostType === 'TOBACCO') ? 'Касса' : 'ПВЗ');
            $safePhoneForSheets = (strpos($phone, '+') === 0) ? ("'" . $phone) : $phone;

            $googlePayload = [
                'date'                 => $nowDate,
                'orderId'              => $orderId,
                'fivepostOrderId'      => $fivepostOrderId ?: '—',
                'fivepostBarcode'      => $fivepostBarcode ?: '—',
                'fio'                  => $fio,
                'phone'                => $safePhoneForSheets,
                'tgUsername'           => $tgUsername,
                'fivepostPointAddress' => $fivepostAddress . " ({$pointTypeRu})",
                'productName'          => $productName,
                'gender'               => $gender,
                'size'                 => $size,
                'price'                => number_format($amount, 0, '', ' ') . ' ₽',
                'transactionId'        => "#{$transactionId}",
                'status'               => 'Оплачен, сформирован 5Post C2C'
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
            curl_exec($ch);
            $gCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($gCode === 200 || $gCode === 302) {
                $pdo->prepare("UPDATE orders SET google_sheets_sent = 1 WHERE order_id = :oid")->execute([':oid' => $orderId]);
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

// Ответ CloudPayments
echo json_encode(['code' => 0]);
