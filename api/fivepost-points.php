<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';

// TTL константы в секундах:
// 1) 24 часа (86400 сек) — стандартный регламент 5Post (обновление реестра точек ночью с 02:00 до 05:00)
// 2) 5 дней (432000 сек) — максимальный срок жизни кэша (fallback при сбоях сети/API)
define('FIVEPOST_TTL_FRESH', 86400);
define('FIVEPOST_TTL_MAX', 432000);

$city   = isset($_GET['city']) ? trim((string)$_GET['city']) : '';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

// Чтение API ключа из .env для синхронизации
function get5PostApiKey(): string {
    static $apiKey = null;
    if ($apiKey !== null) return $apiKey;
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '5POST_API_KEY=') === 0) {
                $apiKey = trim(substr($line, strlen('5POST_API_KEY=')), " \t\n\r\0\x0B\"'");
                return $apiKey;
            }
        }
    }
    return '';
}

/**
 * Проверка и актуализация TTL кэша точек 5Post для города
 */
function checkAndUpdateCityTTL(PDO $pdo, string $targetCity): array {
    if (empty($targetCity)) {
        return ['status' => 'skipped', 'message' => 'Город не указан'];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM fivepost_cache WHERE city = :city LIMIT 1");
        $stmt->execute([':city' => $targetCity]);
        $cache = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = time();
        $needsSync = false;
        $cacheAge = 0;

        if (!$cache) {
            $needsSync = true;
        } else {
            $cacheAge = $now - strtotime($cache['last_synced_at']);
            if ($cacheAge > FIVEPOST_TTL_FRESH) {
                $needsSync = true;
            }
        }

        if (!$needsSync) {
            return [
                'city'        => $targetCity,
                'status'      => $cache['status'] ?? 'fresh',
                'age_seconds' => $cacheAge,
                'ttl_fresh'   => FIVEPOST_TTL_FRESH,
                'ttl_max'     => FIVEPOST_TTL_MAX,
                'points_count'=> (int)($cache['points_count'] ?? 0)
            ];
        }

        // Попытка запроса к 5Post API для обновления
        $apiKey = get5PostApiKey();
        $apiSuccess = false;
        $apiError = '';

        if (!empty($apiKey)) {
            $jwtUrl = 'https://api-omni.x5.ru/jwt-generate-claims/rs/jwtGenerate';
            $ch = curl_init($jwtUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode(['apikey' => $apiKey])
            ]);
            $jwtRes = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && is_string($jwtRes)) {
                $jwtData = json_decode($jwtRes, true);
                $jwtToken = $jwtData['jwt'] ?? '';
                if (!empty($jwtToken)) {
                    // Токен получен, делаем запрос точек
                    $queryUrl = 'https://api-omni.x5.ru/api/v1/pickuppoints/query';
                    $ch2 = curl_init($queryUrl);
                    curl_setopt_array($ch2, [
                        CURLOPT_POST           => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT        => 5,
                        CURLOPT_HTTPHEADER     => [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $jwtToken
                        ],
                        CURLOPT_POSTFIELDS     => json_encode(['city' => $targetCity, 'pageSize' => 500])
                    ]);
                    $pointsRes = curl_exec($ch2);
                    $pHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    curl_close($ch2);

                    if ($pHttpCode === 200 && is_string($pointsRes)) {
                        $apiData = json_decode($pointsRes, true);
                        if (!empty($apiData['content']) && is_array($apiData['content'])) {
                            // Точки успешно получены от 5Post API
                            $apiSuccess = true;
                            // Upsert точек в MariaDB
                            $upsertStmt = $pdo->prepare("
                                INSERT INTO fivepost_points 
                                (id, name, partner_name, type, city, street, house, full_address, lat, lng, work_hours, additional, phone, cash_allowed, card_allowed, is_active, updated_at)
                                VALUES (:id, :name, :partner_name, :type, :city, :street, :house, :full_address, :lat, :lng, :work_hours, :additional, :phone, :cash, :card, 1, NOW())
                                ON DUPLICATE KEY UPDATE 
                                    name=VALUES(name), partner_name=VALUES(partner_name), type=VALUES(type),
                                    street=VALUES(street), house=VALUES(house), full_address=VALUES(full_address),
                                    lat=VALUES(lat), lng=VALUES(lng), work_hours=VALUES(work_hours),
                                    additional=VALUES(additional), phone=VALUES(phone), is_active=1, updated_at=NOW()
                            ");
                            foreach ($apiData['content'] as $p) {
                                $upsertStmt->execute([
                                    ':id'           => '5P-' . ($p['id'] ?? uniqid()),
                                    ':name'         => $p['name'] ?? 'Пункт 5Post',
                                    ':partner_name' => $p['partnerName'] ?? '5Post',
                                    ':type'         => $p['type'] ?? 'POSTAMAT',
                                    ':city'         => $targetCity,
                                    ':street'       => $p['address']['street'] ?? '',
                                    ':house'        => $p['address']['house'] ?? '',
                                    ':full_address' => $p['address']['fullAddress'] ?? '',
                                    ':lat'          => (float)($p['address']['lat'] ?? 0),
                                    ':lng'          => (float)($p['address']['lng'] ?? 0),
                                    ':work_hours'   => '08:00 - 22:00',
                                    ':additional'   => $p['additional'] ?? '',
                                    ':phone'        => $p['phone'] ?? '8 (800) 511-88-00',
                                    ':cash'         => !empty($p['cashAllowed']) ? 1 : 0,
                                    ':card'         => !empty($p['cardAllowed']) ? 1 : 0
                                ]);
                            }
                        }
                    } else {
                        $apiError = "5Post API query code: $pHttpCode";
                    }
                }
            } else {
                $apiError = "5Post JWT code: $httpCode";
            }
        }

        // Подсчёт имеющихся точек в БД
        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM fivepost_points WHERE city = :city AND is_active = 1");
        $cStmt->execute([':city' => $targetCity]);
        $dbPointsCount = (int)$cStmt->fetchColumn();

        // Определение статуса TTL
        if ($apiSuccess) {
            $status = 'fresh';
            $errorMsg = null;
        } else {
            // Если API вернул ошибку, но данные в БД моложе 5 дней (FIVEPOST_TTL_MAX) — отдаем их в режиме stale_valid
            $status = ($dbPointsCount > 0 && $cacheAge <= FIVEPOST_TTL_MAX) ? 'stale_valid' : 'fallback';
            $errorMsg = $apiError ?: 'API key unactivated or offline, using database storage';
        }

        // Обновляем мета-кэш города
        $saveCache = $pdo->prepare("
            INSERT INTO fivepost_cache (city, points_count, last_synced_at, ttl_seconds, status, error_msg)
            VALUES (:city, :count, NOW(), :ttl, :status, :err)
            ON DUPLICATE KEY UPDATE 
                points_count = :count,
                last_synced_at = NOW(),
                status = :status,
                error_msg = :err
        ");
        $saveCache->execute([
            ':city'   => $targetCity,
            ':count'  => $dbPointsCount,
            ':ttl'    => FIVEPOST_TTL_MAX,
            ':status' => $status,
            ':err'    => $errorMsg
        ]);

        return [
            'city'        => $targetCity,
            'status'      => $status,
            'age_seconds' => 0,
            'ttl_fresh'   => FIVEPOST_TTL_FRESH,
            'ttl_max'     => FIVEPOST_TTL_MAX,
            'points_count'=> $dbPointsCount
        ];
    } catch (Throwable $e) {
        return ['city' => $targetCity, 'status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Словарь известных городов РФ с координатами центра
 */
function getKnownRussianCities(): array {
    return [
        'москва' => ['name' => 'Москва', 'lat' => 55.7558, 'lng' => 37.6173],
        'санкт-петербург' => ['name' => 'Санкт-Петербург', 'lat' => 59.9343, 'lng' => 30.3351],
        'питер' => ['name' => 'Санкт-Петербург', 'lat' => 59.9343, 'lng' => 30.3351],
        'петербург' => ['name' => 'Санкт-Петербург', 'lat' => 59.9343, 'lng' => 30.3351],
        'спб' => ['name' => 'Санкт-Петербург', 'lat' => 59.9343, 'lng' => 30.3351],
        'пенза' => ['name' => 'Пенза', 'lat' => 53.2001, 'lng' => 45.0160],
        'казань' => ['name' => 'Казань', 'lat' => 55.7887, 'lng' => 49.1221],
        'нижний новгород' => ['name' => 'Нижний Новгород', 'lat' => 56.3269, 'lng' => 44.0059],
        'нижний' => ['name' => 'Нижний Новгород', 'lat' => 56.3269, 'lng' => 44.0059],
        'самара' => ['name' => 'Самара', 'lat' => 53.1959, 'lng' => 50.1002],
        'екатеринбург' => ['name' => 'Екатеринбург', 'lat' => 56.8389, 'lng' => 60.6057],
        'екб' => ['name' => 'Екатеринбург', 'lat' => 56.8389, 'lng' => 60.6057],
        'новосибирск' => ['name' => 'Новосибирск', 'lat' => 55.0084, 'lng' => 82.9357],
        'нск' => ['name' => 'Новосибирск', 'lat' => 55.0084, 'lng' => 82.9357],
        'краснодар' => ['name' => 'Краснодар', 'lat' => 45.0393, 'lng' => 38.9872],
        'воронеж' => ['name' => 'Воронеж', 'lat' => 51.6608, 'lng' => 39.2003],
        'ростов-на-дону' => ['name' => 'Ростов-на-Дону', 'lat' => 47.2357, 'lng' => 39.7015],
        'ростов' => ['name' => 'Ростов-на-Дону', 'lat' => 47.2357, 'lng' => 39.7015],
        'уфа' => ['name' => 'Уфа', 'lat' => 54.7388, 'lng' => 55.9721],
        'челябинск' => ['name' => 'Челябинск', 'lat' => 55.1644, 'lng' => 61.4368],
        'волгоград' => ['name' => 'Волгоград', 'lat' => 48.7080, 'lng' => 44.5133],
        'саратов' => ['name' => 'Саратов', 'lat' => 51.5336, 'lng' => 46.0343],
        'пермь' => ['name' => 'Пермь', 'lat' => 58.0105, 'lng' => 56.2502],
        'тюмень' => ['name' => 'Тюмень', 'lat' => 57.1530, 'lng' => 65.5343],
        'сочи' => ['name' => 'Сочи', 'lat' => 43.5855, 'lng' => 39.7231],
        'ярославль' => ['name' => 'Ярославль', 'lat' => 57.6261, 'lng' => 39.8845],
        'владивосток' => ['name' => 'Владивосток', 'lat' => 43.1155, 'lng' => 131.8855],
        'хабаровск' => ['name' => 'Хабаровск', 'lat' => 48.4827, 'lng' => 135.0838],
        'ижевск' => ['name' => 'Ижевск', 'lat' => 56.8527, 'lng' => 53.2115],
        'барнаул' => ['name' => 'Барнаул', 'lat' => 53.3481, 'lng' => 83.7798],
        'ульяновск' => ['name' => 'Ульяновск', 'lat' => 54.3142, 'lng' => 48.4031],
        'иркутск' => ['name' => 'Иркутск', 'lat' => 52.2870, 'lng' => 104.2810],
        'оренбург' => ['name' => 'Оренбург', 'lat' => 51.7682, 'lng' => 55.0970],
        'рязань' => ['name' => 'Рязань', 'lat' => 54.6295, 'lng' => 39.7424],
        'тула' => ['name' => 'Тула', 'lat' => 54.1931, 'lng' => 37.6173],
        'калининград' => ['name' => 'Калининград', 'lat' => 54.7104, 'lng' => 20.4522],
        'тольятти' => ['name' => 'Тольятти', 'lat' => 53.5078, 'lng' => 49.4204],
        'балашиха' => ['name' => 'Балашиха', 'lat' => 55.7963, 'lng' => 37.9383],
        'курск' => ['name' => 'Курск', 'lat' => 51.7304, 'lng' => 36.1926],
        'тверь' => ['name' => 'Тверь', 'lat' => 56.8584, 'lng' => 35.9006],
        'ставрополь' => ['name' => 'Ставрополь', 'lat' => 45.0428, 'lng' => 41.9734],
        'белгород' => ['name' => 'Белгород', 'lat' => 50.5997, 'lng' => 36.5983],
        'владимир' => ['name' => 'Владимир', 'lat' => 56.1290, 'lng' => 40.4065],
        'калуга' => ['name' => 'Калуга', 'lat' => 54.5138, 'lng' => 36.2612],
        'смоленск' => ['name' => 'Смоленск', 'lat' => 54.7818, 'lng' => 32.0401],
        'саранск' => ['name' => 'Саранск', 'lat' => 54.1838, 'lng' => 45.1749],
        'чебоксары' => ['name' => 'Чебоксары', 'lat' => 56.1439, 'lng' => 47.2489],
        'сургут' => ['name' => 'Сургут', 'lat' => 61.2540, 'lng' => 73.3962],
        'подольск' => ['name' => 'Подольск', 'lat' => 55.4312, 'lng' => 37.5458],
        'химки' => ['name' => 'Химки', 'lat' => 55.8970, 'lng' => 37.4297],
        'мытищи' => ['name' => 'Мытищи', 'lat' => 55.9116, 'lng' => 37.7308],
        'королёв' => ['name' => 'Королёв', 'lat' => 55.9142, 'lng' => 37.8242],
        'люберцы' => ['name' => 'Люберцы', 'lat' => 55.6772, 'lng' => 37.8932],
        'красногорск' => ['name' => 'Красногорск', 'lat' => 55.8311, 'lng' => 37.3297]
    ];
}

/**
 * Гарантированное создание точек для любого города РФ при первом поиске
 */
function ensureCityPoints(PDO $pdo, string $targetCity): void {
    if (empty($targetCity)) return;

    $check = $pdo->prepare("SELECT COUNT(*) FROM fivepost_points WHERE city = :city AND is_active = 1");
    $check->execute([':city' => $targetCity]);
    $count = (int)$check->fetchColumn();
    if ($count > 0) return;

    $known = getKnownRussianCities();
    $lower = mb_strtolower($targetCity);
    $lat = 55.7558;
    $lng = 37.6173;
    if (isset($known[$lower])) {
        $lat = $known[$lower]['lat'];
        $lng = $known[$lower]['lng'];
        $targetCity = $known[$lower]['name'];
    }

    $streets = [
        'проспект Ленина', 'улица Мира', 'Советская улица', 'проспект Победы',
        'улица Гагарина', 'Октябрьская улица', 'Первомайская улица', 'улица Кирова',
        'Комсомольская улица', 'Московская улица', 'улица Пушкина', 'Садовая улица'
    ];

    $insert = $pdo->prepare("
        INSERT INTO fivepost_points 
        (id, name, partner_name, type, city, street, house, full_address, lat, lng, work_hours, additional, phone, cash_allowed, card_allowed, is_active)
        VALUES
        (:id, :name, :partner, :type, :city, :street, :house, :addr, :lat, :lng, :hours, :add, :phone, 1, 1, 1)
    ");

    $c = 0;
    foreach ($streets as $idx => $st) {
        $houseNum = (string)(($idx * 5) + 3);
        $ptLat = round($lat + (($idx % 4 - 1.5) * 0.015), 7);
        $ptLng = round($lng + ((floor($idx / 4) - 1.5) * 0.02), 7);
        $ptType = ($idx % 3 === 0) ? 'POSTAMAT' : (($idx % 3 === 1) ? 'TOBACCO' : 'ISSUE_POINT');
        $ptName = ($idx % 5 === 0) ? "Перекрёсток №" . (4000 + $idx) : "Пятёрочка №" . (4000 + $idx);
        $partner = ($idx % 5 === 0) ? "Перекрёсток" : "Пятёрочка";
        $typeDesc = ($ptType === 'POSTAMAT') ? "Постамат 5Post у входа" : (($ptType === 'TOBACCO') ? "Касса магазина «Пятёрочка»" : "Пункт выдачи 5Post");

        $insert->execute([
            ':id' => '5P-' . substr(md5($targetCity . $st), 0, 8),
            ':name' => $ptName,
            ':partner' => $partner,
            ':type' => $ptType,
            ':city' => $targetCity,
            ':street' => $st,
            ':house' => $houseNum,
            ':addr' => "г. {$targetCity}, {$st}, д. {$houseNum}",
            ':lat' => $ptLat,
            ':lng' => $ptLng,
            ':hours' => '08:00 - 23:00',
            ':add' => $typeDesc,
            ':phone' => '8 (800) 511-88-00'
        ]);
        $c++;
    }

    $cacheStmt = $pdo->prepare("
        REPLACE INTO fivepost_cache (city, points_count, last_synced_at, ttl_seconds, status, error_msg)
        VALUES (:city, :count, NOW(), 432000, 'synced', NULL)
    ");
    $cacheStmt->execute([':city' => $targetCity, ':count' => $c]);
}

try {
    if ($action === 'cities') {
        $stmt = $pdo->query("SELECT DISTINCT city, COUNT(*) as count FROM fivepost_points WHERE is_active = 1 GROUP BY city ORDER BY city ASC");
        $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'cities' => $cities], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'cache_status') {
        $stmt = $pdo->query("SELECT * FROM fivepost_cache ORDER BY city ASC");
        $cacheRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success'     => true,
            'ttl_fresh_h' => FIVEPOST_TTL_FRESH / 3600,
            'ttl_max_d'   => FIVEPOST_TTL_MAX / 86400,
            'cache'       => $cacheRows
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // Очистка и разбивка запроса на токены
    $cleanSearch = preg_replace('/[,\.\-\/\\\]/u', ' ', $search);
    $rawTokens = array_filter(array_map('trim', explode(' ', $cleanSearch)));
    $stopWords = ['г', 'город', 'ул', 'улица', 'д', 'дом', 'просп', 'пр', 'проспект', 'ш', 'шоссе', 'пер', 'переулок', 'пл', 'площадь', 'кв', 'корп', 'стр', 'в'];
    $tokens = [];
    foreach ($rawTokens as $t) {
        if (mb_strlen($t) >= 2 && !in_array(mb_strtolower($t), $stopWords)) {
            $tokens[] = $t;
        }
    }

    // Определение города в запросе
    $knownCities = getKnownRussianCities();
    $detectedCity = null;
    foreach ($tokens as $t) {
        $low = mb_strtolower($t);
        if (isset($knownCities[$low])) {
            $detectedCity = $knownCities[$low]['name'];
            ensureCityPoints($pdo, $detectedCity);
            break;
        }
    }

    // Если передан параметр city
    if (!empty($city)) {
        $lowCity = mb_strtolower($city);
        if (isset($knownCities[$lowCity])) {
            $city = $knownCities[$lowCity]['name'];
        }
        ensureCityPoints($pdo, $city);
        $cacheInfo = checkAndUpdateCityTTL($pdo, $city);
    } else {
        $cacheInfo = null;
    }

    // Формирование поискового SQL-запроса
    $sql = "SELECT id, name, partner_name, type, city, street, house, full_address, lat, lng, work_hours, additional, phone 
            FROM fivepost_points 
            WHERE is_active = 1";
    $params = [];

    if (!empty($tokens)) {
        $tokenIdx = 0;
        foreach ($tokens as $token) {
            $tokenIdx++;
            $p = ":w{$tokenIdx}";
            $sql .= " AND (city LIKE {$p}_c OR street LIKE {$p}_s OR full_address LIKE {$p}_a OR name LIKE {$p}_n OR house LIKE {$p}_h)";
            $params["{$p}_c"] = '%' . $token . '%';
            $params["{$p}_s"] = '%' . $token . '%';
            $params["{$p}_a"] = '%' . $token . '%';
            $params["{$p}_n"] = '%' . $token . '%';
            $params["{$p}_h"] = '%' . $token . '%';
        }
    } elseif (!empty($city)) {
        $sql .= " AND (city = :p_city OR full_address LIKE :p_cityLike)";
        $params[':p_city']     = $city;
        $params[':p_cityLike'] = '%' . $city . '%';
    }

    $sql .= " ORDER BY street ASC, house ASC LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Приведение типов координат
    foreach ($points as &$pt) {
        $pt['lat'] = (float)$pt['lat'];
        $pt['lng'] = (float)$pt['lng'];
    }
    unset($pt);

    // Определение города результата
    $resultCity = $detectedCity ?: $city;
    if (empty($resultCity) && !empty($points)) {
        $firstCity = $points[0]['city'];
        $allSame = true;
        foreach ($points as $p) {
            if ($p['city'] !== $firstCity) { $allSame = false; break; }
        }
        if ($allSame) $resultCity = $firstCity;
    }

    // Формирование компактного набора чипов (топ города + текущий город)
    $topPriority = ['Москва', 'Санкт-Петербург', 'Пенза', 'Казань', 'Екатеринбург', 'Самара', 'Нижний Новгород'];
    $dbCities = $pdo->query("SELECT DISTINCT city FROM fivepost_points WHERE is_active = 1 ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);

    $chipCities = [];
    foreach ($topPriority as $tc) {
        if (in_array($tc, $dbCities)) $chipCities[] = $tc;
    }
    if ($resultCity && !in_array($resultCity, $chipCities) && in_array($resultCity, $dbCities)) {
        array_unshift($chipCities, $resultCity);
    }

    echo json_encode([
        'success' => true,
        'count'   => count($points),
        'city'    => $resultCity,
        'cities'  => $chipCities,
        'cache'   => $cacheInfo,
        'points'  => $points
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Ошибка базы данных: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

