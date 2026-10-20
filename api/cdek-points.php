<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/cdek.php';
env_load();
header('Content-Type: application/json; charset=utf-8');
try {
    $client = new CdekClient();
    $city = mb_substr(trim((string)($_GET['city'] ?? '')), 0, 100);
    $search = mb_substr(trim((string)($_GET['search'] ?? '')), 0, 150);
    if ($search !== '') $city = trim(explode(',', $search, 2)[0]);
    $city = $city ?: 'Пенза';
    $bounds = null;
    if (!empty($_GET['bounds']) && !$search) {
        $bounds = array_map('floatval', explode(',', (string)$_GET['bounds']));
        if (count($bounds) !== 4 || $bounds[0] >= $bounds[2] || $bounds[1] >= $bounds[3]
            || $bounds[0] < -90 || $bounds[2] > 90 || $bounds[1] < -180 || $bounds[3] > 180) {
            throw new InvalidArgumentException('Некорректные границы карты');
        }
        $city = '';
    }
    // Cache the country office directory for one hour, shared by city and viewport searches.
    $cache = sys_get_temp_dir() . '/bf_cdek_points_' . $client->mode() . '.json';
    $points = is_file($cache) && filemtime($cache) > time() - 3600 ? json_decode((string)file_get_contents($cache), true) : null;
    if (!is_array($points)) {
        $points = $client->points([]);
        file_put_contents($cache, json_encode($points, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    $points = array_values(array_filter($points, static function ($p) use ($bounds, $city, $search) {
        if ($bounds !== null) return $p['lat'] >= $bounds[0] && $p['lat'] <= $bounds[2] && $p['lng'] >= $bounds[1] && $p['lng'] <= $bounds[3];
        if (mb_stripos($p['city'], $city) === false) return false;
        $street = trim(explode(',', $search, 2)[1] ?? '');
        return $street === '' || mb_stripos($p['full_address'], $street) !== false;
    }));
    echo json_encode(['success' => true, 'mode' => $client->mode(), 'city' => $city,
        'points' => array_slice($points, 0, 1500)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 503);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
