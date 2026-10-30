<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/checkout_pricing.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}
try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('Некорректный запрос.');
    if (trim((string)($input['promoCode'] ?? '')) === '') throw new InvalidArgumentException('Введите промокод.');
    $pricing = priceCheckout($pdo, $input);
    echo json_encode(['success' => true] + $pricing, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(503);
    error_log('[Promocode] Validation failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Не удалось проверить промокод. Повторите попытку.'], JSON_UNESCAPED_UNICODE);
}
