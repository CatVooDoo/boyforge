<?php
declare(strict_types=1);

/**
 * Логгер жизненного цикла заказа
 * Сохраняет историю заказа в отдельный JSON-файл в папке /logs/
 */
function logOrderEvent(string $orderId, string $eventName, array $data = []): void {
    // Папка для логов в корне сайта (на уровень выше от папки includes)
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0777, true)) {
            error_log("Failed to create log directory: {$logDir}");
            return;
        }
    }
    
    // Безопасное имя файла (только буквы, цифры, дефис)
    $safeOrderId = preg_replace('/[^a-zA-Z0-9_-]/', '', $orderId);
    if (empty($safeOrderId)) {
        return;
    }
    
    $logFile = $logDir . '/order_' . $safeOrderId . '.json';
    
    // Загружаем текущие данные из файла, если он существует
    $logData = [];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        if ($content) {
            $logData = json_decode($content, true) ?: [];
        }
    }
    
    // Если это первое событие, инициализируем основные поля
    if (empty($logData['order_id'])) {
        $logData['order_id'] = $orderId;
        $logData['created_at'] = date('Y-m-d H:i:s');
    }
    
    // Обновляем FIO и Email, если они переданы, чтобы они всегда были на самом верху
    if (isset($data['fio']) && !empty($data['fio'])) {
        $logData['customer_fio'] = $data['fio'];
    }
    if (isset($data['email']) && !empty($data['email'])) {
        $logData['customer_email'] = $data['email'];
    }
    
    // Обновляем текущий статус, если он передан
    if (isset($data['status'])) {
        $logData['current_status'] = $data['status'];
    }
    
    // Инициализируем массив событий, если его нет
    if (!isset($logData['events']) || !is_array($logData['events'])) {
        $logData['events'] = [];
    }
    
    // Добавляем новое событие
    $logData['events'][] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_name' => $eventName,
        'details' => $data
    ];
    
    // Пересобираем массив, чтобы гарантировать порядок полей:
    // ФИО, Email, номер заказа, дата создания, текущий статус -> затем массив событий
    $orderedData = [
        'order_id'       => $logData['order_id'],
        'customer_fio'   => $logData['customer_fio'] ?? 'Не указано',
        'customer_email' => $logData['customer_email'] ?? 'Не указано',
        'created_at'     => $logData['created_at'],
        'current_status' => $logData['current_status'] ?? 'unpaid',
        'events'         => $logData['events']
    ];
    
    // Записываем в файл с красивым форматированием и поддержкой кириллицы
    $jsonContent = json_encode($orderedData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($jsonContent !== false) {
        file_put_contents($logFile, $jsonContent, LOCK_EX);
    }
}
