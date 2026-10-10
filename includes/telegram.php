<?php
declare(strict_types=1);

function sendTelegramNotification(array $order): void {
    $settingsFile = __DIR__ . '/settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
        if (isset($settings['tg_notifications_enabled']) && !$settings['tg_notifications_enabled']) {
            return;
        }
    }

    $token = !empty($settings['tg_bot_token']) ? $settings['tg_bot_token'] : env_get('TG_BOT_TOKEN');
    $chatId = !empty($settings['tg_chat_id']) ? $settings['tg_chat_id'] : env_get('TG_CHAT_ID');
    $apiUrl = env_get('TG_API_URL');
    
    if (!$token || !$chatId) {
        return;
    }
    
    if (!$apiUrl) {
        $apiUrl = 'https://api.telegram.org/';
    }
    
    $apiUrl = rtrim($apiUrl, '/') . '/';

    $text = "<b>Новый заказ #" . htmlspecialchars((string)($order['order_id'] ?? '')) . "</b>\n\n";
    $text .= "<b>Покупатель:</b> " . htmlspecialchars((string)($order['fio'] ?? 'Не указано')) . "\n";
    $text .= "<b>Телефон:</b> " . htmlspecialchars((string)($order['phone'] ?? 'Не указано')) . "\n";
    $text .= "<b>Email:</b> " . htmlspecialchars((string)($order['email'] ?? 'Не указано')) . "\n";
    $text .= "<b>Товар:</b> " . htmlspecialchars((string)($order['product_name'] ?? 'Не указано')) . "\n";
    $text .= "<b>Размер:</b> " . htmlspecialchars((string)($order['size'] ?? 'Не указано')) . "\n";
    $text .= "<b>Сумма:</b> " . number_format((float)($order['price'] ?? 0), 0, '', ' ') . " руб.\n\n";
    $text .= "<b>Доставка (5Post):</b>\n" . htmlspecialchars((string)($order['fivepost_point_address'] ?? 'Не указано'));

    $url = $apiUrl . "bot{$token}/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Telegram API error: HTTP $httpCode. Response: $response. URL: $url, ChatID: $chatId");
    }
}
