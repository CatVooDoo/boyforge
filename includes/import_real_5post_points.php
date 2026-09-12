<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

set_time_limit(300);
ini_set('memory_limit', '512M');

echo "Загрузка реального реестра точек 5Post...\n";
$url = 'https://fivepost.ru/api/public/geo/markers/';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0
]);
$jsonRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($jsonRaw)) {
    die("Ошибка загрузки данных 5Post: HTTP $httpCode\n");
}

$data = json_decode($jsonRaw, true);
$features = $data['features'] ?? [];
$total = count($features);
echo "Получено точек из официального реестра 5Post: $total\n";

if ($total === 0) {
    die("Нет точек в ответе.\n");
}

// Очищаем таблицу от старых/тестовых точек
$pdo->exec("TRUNCATE TABLE fivepost_points;");
$pdo->exec("TRUNCATE TABLE fivepost_cache;");

$stmt = $pdo->prepare("
    INSERT INTO fivepost_points 
    (id, mdm_code, name, partner_name, type, city, street, house, full_address, lat, lng, work_hours, additional, phone, cash_allowed, card_allowed, is_active)
    VALUES 
    (:id, :mdm, :name, :partner, :type, :city, :street, :house, :full_address, :lat, :lng, :work_hours, :additional, :phone, 1, 1, 1)
");

$cityCounts = [];
$batchSize = 500;
$batch = [];
$inserted = 0;

$pdo->beginTransaction();

foreach ($features as $f) {
    $opts = $f['options'] ?? [];
    $id = $opts['id'] ?? null;
    $address = trim((string)($opts['address'] ?? ''));
    $coords = $f['geometry']['coordinates'] ?? [];

    if (!$id || empty($address) || count($coords) < 2) continue;

    $lat = (float)$coords[0];
    $lng = (float)$coords[1];
    if ($lat == 0.0 && $lng == 0.0) continue;

    $preset = (string)($opts['preset'] ?? '');
    $type = (strpos($preset, 'POSTAMAT') !== false) ? 'POSTAMAT' : ((strpos($preset, 'TOBACCO') !== false) ? 'TOBACCO' : 'ISSUE_POINT');
    $name = ($type === 'POSTAMAT') ? 'Постамат 5Post' : (($type === 'TOBACCO') ? 'Касса «Пятёрочка»' : 'Пункт выдачи 5Post');
    $partnerName = '5Post';
    $additional = ($type === 'POSTAMAT') ? 'Постамат 5Post в магазине' : 'Касса в магазине';

    // Парсим город из адреса ("Пенза г, Победы пр-кт, 2" или "Москва г, ...")
    $city = '';
    $street = '';
    $house = '';
    $parts = array_map('trim', explode(',', $address));

    if (!empty($parts[0])) {
        // Убираем маркеры "г", "рп", "п", "д", "с"
        $rawCity = $parts[0];
        $city = trim(preg_replace('/\b(г|город|пгт|рп|п|пос|с|село|д|деревня|ст|станица|аул|мкр)\.?\b/ui', '', $rawCity));
        if (empty($city)) $city = $rawCity;
    }
    if (count($parts) > 1) {
        $street = $parts[1];
    }
    if (count($parts) > 2) {
        $house = $parts[2];
    }

    $stmt->execute([
        ':id'           => $id,
        ':mdm'          => null,
        ':name'         => $name,
        ':partner'      => $partnerName,
        ':type'         => $type,
        ':city'         => $city,
        ':street'       => $street,
        ':house'        => $house,
        ':full_address' => $address,
        ':lat'          => $lat,
        ':lng'          => $lng,
        ':work_hours'   => '08:00 - 22:00',
        ':additional'   => $additional,
        ':phone'        => '8 (800) 511-88-00'
    ]);

    $inserted++;
    $cityCounts[$city] = ($cityCounts[$city] ?? 0) + 1;

    if ($inserted % 1000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        echo "Импортировано $inserted точек...\n";
    }
}

$pdo->commit();

echo "Успешно записано $inserted реальных боевых точек 5Post!\n";

// Обновляем fivepost_cache
$cacheStmt = $pdo->prepare("
    INSERT INTO fivepost_cache (city, points_count, last_synced_at, ttl_seconds, status, error_msg)
    VALUES (:city, :count, NOW(), 432000, 'fresh', NULL)
    ON DUPLICATE KEY UPDATE points_count = VALUES(points_count), last_synced_at = NOW()
");
$pdo->beginTransaction();
foreach ($cityCounts as $cName => $cnt) {
    if (!empty($cName)) {
        $cacheStmt->execute([
            ':city'  => $cName,
            ':count' => $cnt
        ]);
    }
}
$pdo->commit();
echo "Кэш городов успешно обновлен (" . count($cityCounts) . " городов).\n";
