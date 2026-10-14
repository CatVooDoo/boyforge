<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireAdminAuth();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$settingsFile = dirname(__DIR__) . '/includes/settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $content = file_get_contents($settingsFile);
    if ($content) {
        $settings = json_decode($content, true) ?: [];
    }
}

if (isset($input['enabled'])) {
    $settings['tg_notifications_enabled'] = (bool)$input['enabled'];
}

if (isset($input['tg_bot_token'])) {
    $settings['tg_bot_token'] = trim($input['tg_bot_token']);
}

if (isset($input['tg_chat_id'])) {
    $settings['tg_chat_id'] = trim($input['tg_chat_id']);
}

if (file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to write settings.json']);
}
