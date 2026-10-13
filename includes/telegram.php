<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

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
        throw new Exception("TG_API_URL is not set in environment or settings. Requests must go strictly through Cloudflare worker.");
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

/**
 * Отправка документа (например, отчета Excel) в Telegram через бота.
 *
 * @param string $filePath Абсолютный путь к файлу
 * @param string $caption Подпись к документу (HTML)
 * @param string|null $customFilename Имя файла при отправке в Telegram
 * @return bool
 * @throws Exception
 */
function sendTelegramDocument(string $filePath, string $caption = '', ?string $customFilename = null): bool {
    if (!file_exists($filePath)) {
        throw new Exception("Файл для отправки в Telegram не найден: {$filePath}");
    }

    $settingsFile = __DIR__ . '/settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    }

    $token = !empty($settings['tg_bot_token']) ? $settings['tg_bot_token'] : env_get('TG_BOT_TOKEN');
    $chatId = !empty($settings['tg_chat_id']) ? $settings['tg_chat_id'] : env_get('TG_CHAT_ID');
    $apiUrl = env_get('TG_API_URL');

    if (!$token || !$chatId) {
        throw new Exception("Telegram Bot Token или Chat ID не настроены в панели или .env.");
    }

    if (!$apiUrl) {
        throw new Exception("TG_API_URL не настроен. Запросы должны идти строго через Cloudflare worker.");
    }

    $apiUrl = rtrim($apiUrl, '/') . '/';
    $url = $apiUrl . "bot{$token}/sendDocument";

    $filename = $customFilename ?: basename($filePath);
    $cfile = new CURLFile($filePath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $filename);

    $postData = [
        'chat_id' => $chatId,
        'document' => $cfile,
    ];

    if ($caption !== '') {
        $postData['caption'] = $caption;
        $postData['parse_mode'] = 'HTML';
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        throw new Exception("Ошибка cURL при отправке документа в Telegram: " . $curlErr);
    }

    if ($httpCode !== 200) {
        throw new Exception("Ошибка Telegram API (HTTP {$httpCode}): {$response}");
    }

    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded) || empty($decoded['ok'])) {
        $errDesc = $decoded['description'] ?? (string)$response;
        throw new Exception("Telegram API error: {$errDesc}");
    }

    return true;
}

