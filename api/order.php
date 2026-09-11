<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Метод не поддерживается. Используйте POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fivepost.php';

$envVars = FivePostClient::loadEnv();
$googleScriptUrl = $envVars['GOOGLE_SCRIPT_URL'] ?? 'https://script.google.com/macros/s/AKfycbwEX5yOenoxiIpkFlt0BGHbV4SPmJiWIrzIFU-0t8R-4lN59vMuTnhMhlAP6ImemV59Fw/exec';

$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

if (!is_array($inputData)) {
    $inputData = $_POST;
}

// Получаем и нормализуем данные заказа
$orderId       = !empty($inputData['orderId']) ? trim((string)$inputData['orderId']) : ('BF-' . strtoupper(substr(md5(uniqid()), 0, 8)));
$productId     = isset($inputData['productId']) ? trim((string)$inputData['productId']) : 'BF-1';
$productName   = isset($inputData['productName']) ? trim((string)$inputData['productName']) : 'Товар BOYFORGE';
$rawPrice      = isset($inputData['price']) ? (string)$inputData['price'] : '3200';
$numPrice      = (float)preg_replace('/[^\d.]/', '', $rawPrice) ?: 3200.0;
$gender        = isset($inputData['gender']) ? trim((string)$inputData['gender']) : 'Мужской';
$size          = isset($inputData['size']) ? trim((string)$inputData['size']) : 'M';
$fio           = isset($inputData['fio']) ? trim((string)$inputData['fio']) : '';
$phone         = isset($inputData['phone']) ? trim((string)$inputData['phone']) : '';
$tgUsername    = isset($inputData['tgUsername']) ? trim((string)$inputData['tgUsername']) : '';
$transactionId = isset($inputData['transactionId']) ? trim((string)$inputData['transactionId']) : '';
$paymentStatus = (!empty($transactionId) || ($inputData['paymentStatus'] ?? '') === 'paid') ? 'paid' : 'unpaid';

// Данные точки 5Post (UUID из раздела 18.5)
$fivepostPointId   = isset($inputData['fivepostPointId']) ? trim((string)$inputData['fivepostPointId']) : '';
$fivepostPointName = isset($inputData['fivepostPointName']) ? trim((string)$inputData['fivepostPointName']) : '5Post';
$fivepostAddress   = isset($inputData['fivepostPointAddress']) ? trim((string)$inputData['fivepostPointAddress']) : '';
$fivepostType      = isset($inputData['fivepostPointType']) ? trim((string)$inputData['fivepostPointType']) : 'POSTAMAT';

if (empty($fio) || empty($phone) || empty($fivepostPointId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Не все обязательные поля заполнены (ФИО, Телефон, Точка 5Post).'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Сохранение/обновление заказа в базе данных MariaDB
try {
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_id, client_order_id, product_id, product_name, price, gender, size,
            fio, phone, tg_username, fivepost_point_id, fivepost_point_name, fivepost_point_address,
            fivepost_point_type, payment_status, payment_transaction_id, created_at
        ) VALUES (
            :order_id, :client_order_id, :product_id, :product_name, :price, :gender, :size,
            :fio, :phone, :tg_username, :fivepost_point_id, :fivepost_point_name, :fivepost_point_address,
            :fivepost_point_type, :payment_status, :payment_transaction_id, NOW()
        )
        ON DUPLICATE KEY UPDATE
            payment_status = VALUES(payment_status),
            payment_transaction_id = VALUES(payment_transaction_id),
            updated_at = NOW()
    ");

    $stmt->execute([
        ':order_id'               => $orderId,
        ':client_order_id'        => $orderId,
        ':product_id'             => $productId,
        ':product_name'           => $productName,
        ':price'                  => $numPrice,
        ':gender'                 => $gender,
        ':size'                   => $size,
        ':fio'                    => $fio,
        ':phone'                  => $phone,
        ':tg_username'            => $tgUsername,
        ':fivepost_point_id'      => $fivepostPointId,
        ':fivepost_point_name'    => $fivepostPointName,
        ':fivepost_point_address' => $fivepostAddress,
        ':fivepost_point_type'    => $fivepostType,
        ':payment_status'         => $paymentStatus,
        ':payment_transaction_id' => $transactionId
    ]);
} catch (Throwable $e) {
    error_log("Order DB Insert Error: " . $e->getMessage());
}

$fivepostResult = [
    'success' => false,
    'orderId' => null,
    'barcode' => null,
    'status'  => 'PENDING'
];

// 2. Если заказ оплачен — формируем C2C-заказ в 5Post строго по Разделу 18.2
if ($paymentStatus === 'paid') {
    $fpClient = new FivePostClient();
    $c2cResponse = $fpClient->createC2COrder([
        'order_id'          => $orderId,
        'client_order_id'   => $orderId,
        'fivepost_point_id' => $fivepostPointId,
        'fio'               => $fio,
        'phone'             => $phone,
        'product_name'      => $productName,
        'product_id'        => $productId,
        'price'             => $numPrice,
        'weight_g'          => 350
    ]);

    if (!empty($c2cResponse['success'])) {
        $fivepostResult['success'] = true;
        $fivepostResult['orderId'] = $c2cResponse['orderId'];
        $fivepostResult['barcode'] = $c2cResponse['barcode'];
        $fivepostResult['status']  = 'CREATED';

        // Обновляем заказ в MariaDB
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
            ':payload' => json_encode($c2cResponse['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ':raw'     => is_string($c2cResponse['raw']) ? $c2cResponse['raw'] : json_encode($c2cResponse['raw'], JSON_UNESCAPED_UNICODE),
            ':oid'     => $orderId
        ]);
    } else {
        // Если API ключ 5Post еще на модерации / выдал ошибку:
        // фиксируем ошибку в БД, формируем системный трек-номер, чтобы не блокировать отправку в Google Таблицу
        $errText = $c2cResponse['error'] ?? '5Post API Error';
        $fallbackBarcode = '5P-' . strtoupper(substr(md5($orderId), 0, 10));
        $fivepostResult['status']    = 'PENDING_REGISTRATION';
        $fivepostResult['barcode']   = $fallbackBarcode;
        $fivepostResult['error']     = $errText;
        $fivepostResult['http_code'] = (int)($c2cResponse['http_code'] ?? 0);

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
            ':payload' => json_encode($c2cResponse['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ':raw'     => is_string($c2cResponse['raw'] ?? null) ? $c2cResponse['raw'] : json_encode($c2cResponse, JSON_UNESCAPED_UNICODE),
            ':oid'     => $orderId
        ]);
    }
}

// 3. Отправка полного пакета данных в Google Таблицу
$nowDate = date('d.m.Y H:i:s');
$pointTypeRu = ($fivepostType === 'POSTAMAT') ? 'Постамат' : (($fivepostType === 'TOBACCO') ? 'Касса' : 'ПВЗ');

$googlePayload = [
    'date'                 => $nowDate,
    'orderId'              => $orderId,
    'fivepostOrderId'      => $fivepostResult['orderId'] ?? '—',
    'fivepostBarcode'      => $fivepostResult['barcode'] ?? '—',
    'fio'                  => $fio,
    'phone'                => $phone,
    'tgUsername'           => $tgUsername,
    'fivepostPointAddress' => $fivepostAddress . " ({$pointTypeRu})",
    'productName'          => $productName,
    'gender'               => $gender,
    'size'                 => $size,
    'price'                => number_format($numPrice, 0, '', ' ') . ' ₽',
    'transactionId'        => $transactionId ? "#{$transactionId}" : '—',
    'status'               => ($paymentStatus === 'paid') ? 'Оплачен, сформирован 5Post C2C' : 'Ожидает оплаты'
];

$googleSent = false;
if (!empty($googleScriptUrl) && strpos($googleScriptUrl, 'script.google.com') !== false) {
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
        $googleSent = true;
        $pdo->prepare("UPDATE orders SET google_sheets_sent = 1 WHERE order_id = :oid")->execute([':oid' => $orderId]);
    }
}

// 4. Формирование финального ответа для фронтенда
echo json_encode([
    'success'         => true,
    'orderId'         => $orderId,
    'fivepost'        => $fivepostResult,
    'googleSheetSent' => $googleSent,
    'order'           => $googlePayload
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
