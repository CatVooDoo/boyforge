<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireAdminAuth();

header('Content-Type: application/json');

$settingsFile = dirname(__DIR__) . '/includes/settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}
if (isset($settings['tg_notifications_enabled']) && !$settings['tg_notifications_enabled']) {
    echo json_encode(['success' => false, 'error' => 'Уведомления отключены тумблером']);
    exit;
}

$token = !empty($settings['tg_bot_token']) ? $settings['tg_bot_token'] : env_get('TG_BOT_TOKEN');
$chatId = !empty($settings['tg_chat_id']) ? $settings['tg_chat_id'] : env_get('TG_CHAT_ID');
$apiUrl = env_get('TG_API_URL');

if (!$token || !$chatId) {
    echo json_encode(['success' => false, 'error' => 'Не настроен токен или Chat ID']);
    exit;
}

$message = "🔔 <b>Тестовое сообщение</b> из админ-панели BOYFORGE\n\nЕсли вы видите это сообщение, значит интеграция с Telegram настроена и работает корректно!";

$url = rtrim($apiUrl ?: 'https://api.telegram.org/', '/') . "/bot{$token}/sendMessage";
$data = [
    'chat_id' => $chatId,
    'text' => $message,
    'parse_mode' => 'HTML'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode(['success' => true]);
} else {
    $err = json_decode((string)$response, true);
    $desc = $err['description'] ?? 'Неизвестная ошибка Telegram';
    echo json_encode(['success' => false, 'error' => "Telegram API HTTP $httpCode: $desc"]);
}
