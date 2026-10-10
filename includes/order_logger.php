<?php
declare(strict_types=1);

function logOrderEvent(string $orderId, string $eventName, array $data = []): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0777, true)) {
            error_log("Failed to create log directory: {$logDir}");
            return;
        }
    }
    
    $safeOrderId = preg_replace('/[^a-zA-Z0-9_-]/', '', $orderId);
    if (empty($safeOrderId)) {
        return;
    }
    
    $logFile = $logDir . '/order_' . $safeOrderId . '.json';
    
    $logData = [];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        if ($content) {
            $logData = json_decode($content, true) ?: [];
        }
    }
    
    if (empty($logData['order_id'])) {
        $logData['order_id'] = $orderId;
        $logData['created_at'] = date('Y-m-d H:i:s');
    }
    
    if (isset($data['fio']) && !empty($data['fio'])) {
        $logData['customer_fio'] = $data['fio'];
    }
    if (isset($data['email']) && !empty($data['email'])) {
        $logData['customer_email'] = $data['email'];
    }
    
    if (isset($data['status'])) {
        $logData['current_status'] = $data['status'];
    }
    
    if (!isset($logData['events']) || !is_array($logData['events'])) {
        $logData['events'] = [];
    }
    
    $logData['events'][] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_name' => $eventName,
        'details' => $data
    ];
    
    $orderedData = [
        'order_id'       => $logData['order_id'],
        'customer_fio'   => $logData['customer_fio'] ?? 'Не указано',
        'customer_email' => $logData['customer_email'] ?? 'Не указано',
        'created_at'     => $logData['created_at'],
        'current_status' => $logData['current_status'] ?? 'unpaid',
        'events'         => $logData['events']
    ];
    
    $jsonContent = json_encode($orderedData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($jsonContent !== false) {
        file_put_contents($logFile, $jsonContent, LOCK_EX);
    }
}
