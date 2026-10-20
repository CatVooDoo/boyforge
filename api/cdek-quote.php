<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/cdek.php';
env_load();
header('Content-Type: application/json; charset=utf-8');
try {
    $code = trim((string)($_GET['point'] ?? ''));
    if (!preg_match('/^[a-z0-9_-]{1,32}$/i', $code)) throw new InvalidArgumentException('Выберите пункт СДЭК');
    $client = new CdekClient();
    $value = filter_var($_GET['value'] ?? 0, FILTER_VALIDATE_FLOAT);
    if ($value === false || $value < 0 || $value > 1000000) throw new InvalidArgumentException('Некорректная стоимость товара');
    $points = $client->points(['code' => $code]);
    $point = array_values(array_filter($points, static fn($p) => $p['id'] === $code))[0] ?? null;
    if (!$point) throw new InvalidArgumentException('Пункт СДЭК недоступен. Выберите другой.');
    echo json_encode(['success' => true, 'mode' => $client->mode(), 'point' => $point,
        'quote' => $client->quote($point, (float)$value)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 503);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
