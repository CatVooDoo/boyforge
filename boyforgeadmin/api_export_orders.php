<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/telegram.php';
require_once dirname(__DIR__) . '/vendor/simplexlsxgen/SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

requireAdminAuth();

// Прием параметров (GET или POST)
$action = (string)($_REQUEST['action'] ?? 'download');
$dateFromRaw = trim((string)($_REQUEST['date_from'] ?? ''));
$dateToRaw = trim((string)($_REQUEST['date_to'] ?? ''));
$status = trim((string)($_REQUEST['status'] ?? 'paid'));

// Валидация дат
$dateFromObj = DateTime::createFromFormat('Y-m-d', $dateFromRaw);
if (!$dateFromObj) {
    $dateFromObj = new DateTime('first day of this month');
}
$dateFrom = $dateFromObj->format('Y-m-d');

$dateToObj = DateTime::createFromFormat('Y-m-d', $dateToRaw);
if (!$dateToObj) {
    $dateToObj = new DateTime('now');
}
$dateTo = $dateToObj->format('Y-m-d');

// Защита от перевернутого диапазона
if ($dateFrom > $dateTo) {
    $temp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $temp;
}

// Формирование SQL-запроса
$where = [
    "created_at >= :date_from",
    "created_at <= :date_to"
];
$params = [
    ':date_from' => $dateFrom . ' 00:00:00',
    ':date_to'   => $dateTo . ' 23:59:59',
];

if ($status === 'paid') {
    $where[] = "payment_status = 'paid' AND (mark_code != '__CANCELLED__' OR mark_code IS NULL)";
} elseif ($status === 'cancelled') {
    $where[] = "(mark_code = '__CANCELLED__' OR payment_status = 'canceled')";
}

$sql = "SELECT * FROM orders WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Формирование строк для Excel
$rows = [];

// Информационная строка
$statusLabel = 'Все';
if ($status === 'paid') {
    $statusLabel = 'Только оплаченные';
} elseif ($status === 'cancelled') {
    $statusLabel = 'Только отмененные';
}

$rows[] = [
    '<style bgcolor="#1E293B" color="#FFFFFF"><b><center>ОТЧЕТ ПО ЗАКАЗАМ BOYFORGE</center></b></style>',
    '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''
];
$rows[] = [
    'Период: ' . $dateFrom . ' — ' . $dateTo . ' | Фильтр: ' . $statusLabel . ' | Всего записей: ' . count($orders) . ' | Сформирован: ' . date('d.m.Y H:i:s'),
    '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''
];
$rows[] = []; // Пустая строка-разделитель

// Заголовки столбцов таблицы
$headers = [
    '№ Заказа',
    'Дата и время',
    'Статус оплаты',
    'ФИО клиента',
    'Телефон',
    'Email',
    'Telegram клиента',
    'Наименование товара',
    'Размер',
    'Пол',
    'Цена (₽)',
    'Трек 5Post (Баркод)',
    'Статус 5Post',
    'Адрес ПВЗ 5Post',
    'Название ПВЗ',
    'Чек полный расчет',
    'Код маркировки',
    'ID транзакции ЮKassa'
];

$styledHeaders = [];
foreach ($headers as $h) {
    $styledHeaders[] = '<style bgcolor="#0F172A" color="#FFFFFF" border="thin"><b><center>' . $h . '</center></b></style>';
}
$rows[] = $styledHeaders;

$totalSum = 0.0;

foreach ($orders as $o) {
    $isCancelled = ($o['mark_code'] === '__CANCELLED__' || $o['payment_status'] === 'canceled');
    
    $paymentStatusRu = 'Не оплачен';
    if ($o['payment_status'] === 'paid') {
        $paymentStatusRu = $isCancelled ? 'Отменен' : 'Оплачен';
    } elseif ($o['payment_status'] === 'canceled') {
        $paymentStatusRu = 'Отменен';
    }

    $receiptStatusRu = 'Не пробит';
    if (!empty($o['receipt_sent']) && (int)$o['receipt_sent'] === 1) {
        $receiptStatusRu = 'Пробит';
    } elseif ($isCancelled) {
        $receiptStatusRu = 'Отменен';
    }

    $price = (float)($o['price'] ?? 0);
    if (!$isCancelled && $o['payment_status'] === 'paid') {
        $totalSum += $price;
    }

    $rows[] = [
        (string)($o['order_id'] ?? ''),
        (string)($o['created_at'] ?? ''),
        $paymentStatusRu,
        (string)($o['fio'] ?? ''),
        (string)($o['phone'] ?? ''),
        (string)($o['email'] ?? ''),
        (string)($o['tg_username'] ?? ''),
        (string)($o['product_name'] ?? ''),
        (string)($o['size'] ?? ''),
        (string)($o['gender'] ?? ''),
        $price,
        (string)($o['fivepost_barcode'] ?? ''),
        (string)($o['fivepost_status'] ?? ''),
        (string)($o['fivepost_point_address'] ?? ''),
        (string)($o['fivepost_point_name'] ?? ''),
        $receiptStatusRu,
        (string)($o['mark_code'] ?? ''),
        (string)($o['payment_transaction_id'] ?? '')
    ];
}

// Строка итогов
$rows[] = []; // Разделитель
$rows[] = [
    '<style bgcolor="#E2E8F0" border="thin"><b>ИТОГО ЗАКАЗОВ:</b></style>',
    '<style bgcolor="#E2E8F0" border="thin"><b>' . count($orders) . '</b></style>',
    '', '', '', '', '', '', '',
    '<style bgcolor="#E2E8F0" border="thin"><b>ИТОГО СУММА:</b></style>',
    '<style bgcolor="#E2E8F0" border="thin"><b>' . $totalSum . '</b></style>',
    '', '', '', '', '', '', ''
];

$xlsx = SimpleXLSXGen::fromArray($rows, 'Отчет по заказам');

// Действие: Скачивание в браузер
if ($action === 'download') {
    $downloadFilename = 'boyforge_orders_' . $dateFrom . '_' . $dateTo . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: public');
    $xlsx->saveAs('php://output');
    exit;
}

// Действие: Отправка в Telegram
if ($action === 'telegram') {
    header('Content-Type: application/json; charset=utf-8');
    
    $tempDir = sys_get_temp_dir();
    $exportFilename = 'boyforge_orders_' . $dateFrom . '_' . $dateTo . '.xlsx';
    $tempPath = $tempDir . DIRECTORY_SEPARATOR . 'bf_export_' . bin2hex(random_bytes(8)) . '.xlsx';

    if (!$xlsx->saveAs($tempPath)) {
        echo json_encode([
            'success' => false,
            'error' => 'Не удалось сгенерировать временный файл отчета на сервере.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ordersCount = count($orders);
    $caption = "📊 <b>Отчет по заказам BOYFORGE</b>\n\n";
    $caption .= "🗓 <b>Период:</b> {$dateFrom} — {$dateTo}\n";
    $caption .= "🎯 <b>Статус:</b> {$statusLabel}\n";
    $caption .= "📦 <b>Количество заказов:</b> {$ordersCount} шт.\n";
    $caption .= "💰 <b>Сумма оплаченных:</b> " . number_format($totalSum, 0, '', ' ') . " руб.\n";
    $caption .= "⏰ <b>Сформирован:</b> " . date('d.m.Y H:i:s');

    try {
        sendTelegramDocument($tempPath, $caption, $exportFilename);
        @unlink($tempPath);

        echo json_encode([
            'success' => true,
            'message' => "Отчет успешно отправлен в Telegram!\nЗаказов: {$ordersCount}, Сумма: " . number_format($totalSum, 0, '', ' ') . " руб."
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        @unlink($tempPath);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Неизвестное действие
http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Недопустимое действие']);
