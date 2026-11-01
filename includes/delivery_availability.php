<?php
declare(strict_types=1);

// Временно отключено до переработки интеграции. Не влияет на существующие заказы.
const CDEK_CHECKOUT_ENABLED = false;

function requireCdekCheckoutEnabled(): void {
    if (CDEK_CHECKOUT_ENABLED) return;

    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Этот способ доставки временно недоступен. Выберите 5Post.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
