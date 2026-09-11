<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fivepost.php';

$city   = isset($_GET['city']) ? trim((string)$_GET['city']) : '';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

function getCityAliases(): array {
    return [
        'спб'             => 'Санкт-Петербург',
        'питер'           => 'Санкт-Петербург',
        'питере'          => 'Санкт-Петербург',
        'петербург'       => 'Санкт-Петербург',
        'петербурге'      => 'Санкт-Петербург',
        'ленинград'       => 'Санкт-Петербург',
        'мск'             => 'Москва',
        'москва'          => 'Москва',
        'москве'          => 'Москва',
        'москву'          => 'Москва',
        'екб'             => 'Екатеринбург',
        'ебурге'          => 'Екатеринбург',
        'ебург'           => 'Екатеринбург',
        'екатеринбург'    => 'Екатеринбург',
        'екатеринбурге'   => 'Екатеринбург',
        'нск'             => 'Новосибирск',
        'новосиб'         => 'Новосибирск',
        'новосибирске'    => 'Новосибирск',
        'новосибирск'     => 'Новосибирск',
        'нижний'          => 'Нижний Новгород',
        'нижнем'          => 'Нижний Новгород',
        'нн'              => 'Нижний Новгород',
        'ростов'          => 'Ростов-на-Дону',
        'ростове'         => 'Ростов-на-Дону',
        'ростов на дону'  => 'Ростов-на-Дону',
        'ростов-на-дону'  => 'Ростов-на-Дону',
        'казань'          => 'Казань',
        'казани'          => 'Казань',
        'самара'          => 'Самара',
        'самаре'          => 'Самара',
        'самару'          => 'Самара',
        'пенза'           => 'Пенза',
        'пензе'           => 'Пенза',
        'пензу'           => 'Пенза',
        'краснодар'       => 'Краснодар',
        'краснодаре'      => 'Краснодар',
        'воронеж'         => 'Воронеж',
        'воронеже'        => 'Воронеж',
        'уфа'             => 'Уфа',
        'уфе'             => 'Уфа',
        'челябинск'       => 'Челябинск',
        'челябинске'      => 'Челябинск',
        'пермь'           => 'Пермь',
        'перми'           => 'Пермь',
        'волгоград'       => 'Волгоград',
        'волгограде'      => 'Волгоград',
        'саратов'         => 'Саратов',
        'саратове'        => 'Саратов',
        'тюмень'          => 'Тюмень',
        'тюмени'          => 'Тюмень',
        'сочи'            => 'Сочи',
        'ярославль'       => 'Ярославль',
        'ярославле'       => 'Ярославль',
        'ставрополь'      => 'Ставрополь',
        'ставрополе'      => 'Ставрополь',
        'тверь'           => 'Тверь',
        'твери'           => 'Тверь',
        'тула'            => 'Тула',
        'туле'            => 'Тула',
        'курск'           => 'Курск',
        'курске'          => 'Курск',
        'белгород'        => 'Белгород',
        'белгороде'       => 'Белгород',
        'владимир'        => 'Владимир',
        'владимире'       => 'Владимир',
        'калуга'          => 'Калуга',
        'калуге'          => 'Калуга',
        'рязань'          => 'Рязань',
        'рязани'          => 'Рязань',
        'брянск'          => 'Брянск',
        'брянске'         => 'Брянск',
        'иваново'         => 'Иваново',
        'липецк'          => 'Липецк',
        'липецке'         => 'Липецк',
        'тамбов'          => 'Тамбов',
        'тамбове'         => 'Тамбов',
        'чебоксары'       => 'Чебоксары',
        'чебоксарах'      => 'Чебоксары',
        'саранск'         => 'Саранск',
        'саранске'        => 'Саранск',
        'ульяновск'       => 'Ульяновск',
        'ульяновске'      => 'Ульяновск',
        'оренбург'        => 'Оренбург',
        'оренбурге'       => 'Оренбург',
        'тольятти'        => 'Тольятти',
        'калининград'     => 'Калининград',
        'калининграде'    => 'Калининград',
        'владивосток'     => 'Владивосток',
        'владивостоке'    => 'Владивосток'
    ];
}

function getRussianStem(string $word): string {
    $w = mb_strtolower(trim($word));
    if (in_array($w, ['в', 'во', 'на', 'по', 'о', 'об', 'из', 'до', 'от', 'г', 'город', 'ул', 'улица', 'д', 'дом', 'просп', 'пр', 'пр-кт', 'проспект', 'ш', 'шоссе', 'пер', 'переулок'])) {
        return '';
    }
    $stem = preg_replace('/(иями|ыями|ями|ами|ого|его|ому|ему|ыми|ими|ях|ах|ов|ев|ей|ия|ие|ию|ии|ий|ый|ой|ая|ое|ые|ых|их|ом|ем|ам|ям|ой|ей|е|а|у|ы|и|о|ь|я)$/u', '', $w);
    return (mb_strlen($stem) >= 3) ? $stem : $w;
}

try {
    $client = new FivePostClient();

    // Синхронизация с API 5Post (Раздел 18.5)
    if ($action === 'sync') {
        $res = $client->fetchPickupPoints(null, 1000);
        if (!empty($res['success']) && !empty($res['data'])) {
            $upsertStmt = $pdo->prepare("
                INSERT INTO fivepost_points 
                (id, mdm_code, name, partner_name, type, city, street, house, full_address, lat, lng, work_hours, additional, phone, cash_allowed, card_allowed, is_active, updated_at)
                VALUES (:id, :mdm, :name, :partner_name, :type, :city, :street, :house, :full_address, :lat, :lng, :work_hours, :additional, :phone, :cash, :card, 1, NOW())
                ON DUPLICATE KEY UPDATE 
                    name=VALUES(name), partner_name=VALUES(partner_name), type=VALUES(type),
                    city=VALUES(city), street=VALUES(street), house=VALUES(house), full_address=VALUES(full_address),
                    lat=VALUES(lat), lng=VALUES(lng), work_hours=VALUES(work_hours),
                    additional=VALUES(additional), phone=VALUES(phone), is_active=1, updated_at=NOW()
            ");
            $saved = 0;
            foreach ($res['data'] as $p) {
                $pointId = $p['id'] ?? null;
                if (!$pointId) continue;
                $addr = $p['address'] ?? [];
                $pCity = trim((string)($addr['city'] ?? ''));
                $pStreet = trim((string)($addr['street'] ?? ''));
                $pHouse = trim((string)($addr['house'] ?? ''));
                $pFullAddr = trim((string)($p['fullAddress'] ?? "г. {$pCity}, {$pStreet}, д. {$pHouse}"));
                $pLat = (float)($addr['lat'] ?? 0);
                $pLng = (float)($addr['lng'] ?? 0);
                if ($pLat == 0.0 || $pLng == 0.0) continue;

                $upsertStmt->execute([
                    ':id'           => $pointId,
                    ':mdm'          => $p['mdmCode'] ?? null,
                    ':name'         => $p['name'] ?? 'Пункт 5Post',
                    ':partner_name' => '5Post',
                    ':type'         => $p['type'] ?? 'POSTAMAT',
                    ':city'         => $pCity,
                    ':street'       => $pStreet,
                    ':house'        => $pHouse,
                    ':full_address' => $pFullAddr,
                    ':lat'          => $pLat,
                    ':lng'          => $pLng,
                    ':work_hours'   => '08:00 - 22:00',
                    ':additional'   => $p['additional'] ?? '',
                    ':phone'        => $p['phone'] ?? '8 (800) 511-88-00',
                    ':cash'         => !empty($p['cashAllowed']) ? 1 : 0,
                    ':card'         => !empty($p['cardAllowed']) ? 1 : 0
                ]);
                $saved++;
            }
            echo json_encode(['success' => true, 'points_synced' => $saved], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => $res['error'] ?? 'API sync failed'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Экшен списка городов
    if ($action === 'cities') {
        $stmt = $pdo->query("SELECT DISTINCT city, COUNT(*) as count FROM fivepost_points WHERE is_active = 1 GROUP BY city ORDER BY count DESC LIMIT 50");
        $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'cities' => $cities], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $aliases = getCityAliases();
    $targetCity = $city;
    $cleanSearch = trim(preg_replace('/[,\.\-\/\\\]/u', ' ', $search));

    // Проверяем синонимы и распознаем город
    $lowerSearch = mb_strtolower($cleanSearch);
    $streetTokens = [];

    if (isset($aliases[$lowerSearch])) {
        $targetCity = $aliases[$lowerSearch];
    } else {
        $words = explode(' ', $lowerSearch);
        foreach ($words as $w) {
            $w = trim($w);
            if (isset($aliases[$w])) {
                $targetCity = $aliases[$w];
            } else {
                $stem = getRussianStem($w);
                if (!empty($stem)) {
                    $streetTokens[] = ['raw' => $w, 'stem' => $stem];
                }
            }
        }
    }

    if (!empty($targetCity) && isset($aliases[mb_strtolower($targetCity)])) {
        $targetCity = $aliases[mb_strtolower($targetCity)];
    }

    // Формируем запрос
    $sql = "SELECT id, mdm_code, name, partner_name, type, city, street, house, full_address, lat, lng, work_hours, additional, phone 
            FROM fivepost_points 
            WHERE is_active = 1";
    $params = [];

    if (!empty($targetCity)) {
        // Ищем строго по городу (точно или по корню названия города)
        $cityStem = getRussianStem($targetCity);
        $sql .= " AND (city = :c_exact OR city LIKE :c_stem)";
        $params[':c_exact'] = $targetCity;
        $params[':c_stem']  = $cityStem . '%';

        // Если указана также улица в этом городе
        if (!empty($streetTokens)) {
            $tokenIdx = 0;
            foreach ($streetTokens as $t) {
                $tokenIdx++;
                $p = "tok{$tokenIdx}";
                $sql .= " AND (street LIKE :{$p}_s1 OR house LIKE :{$p}_s2 OR full_address LIKE :{$p}_s3 OR name LIKE :{$p}_r)";
                $params[":{$p}_s1"] = '%' . $t['stem'] . '%';
                $params[":{$p}_s2"] = '%' . $t['stem'] . '%';
                $params[":{$p}_s3"] = '%' . $t['stem'] . '%';
                $params[":{$p}_r"]  = '%' . $t['raw'] . '%';
            }
        }
    } elseif (!empty($streetTokens)) {
        // Город не указан, поиск по общим токенам
        $tokenIdx = 0;
        foreach ($streetTokens as $t) {
            $tokenIdx++;
            $p = "tok{$tokenIdx}";
            $sql .= " AND (city LIKE :{$p}_c OR street LIKE :{$p}_s1 OR full_address LIKE :{$p}_s2 OR name LIKE :{$p}_r)";
            $params[":{$p}_c"]  = '%' . $t['stem'] . '%';
            $params[":{$p}_s1"] = '%' . $t['stem'] . '%';
            $params[":{$p}_s2"] = '%' . $t['stem'] . '%';
            $params[":{$p}_r"]  = '%' . $t['raw'] . '%';
        }
    }

    $sql .= " ORDER BY street ASC, house ASC LIMIT 250";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Если указана улица, но по ней в городе 0 точек — возвращаем все точки этого города, чтобы карта не была пустой
    if (empty($points) && !empty($targetCity) && !empty($streetTokens)) {
        $fallbackStmt = $pdo->prepare("
            SELECT id, mdm_code, name, partner_name, type, city, street, house, full_address, lat, lng, work_hours, additional, phone 
            FROM fivepost_points 
            WHERE is_active = 1 AND (city = :c_exact OR city LIKE :c_stem)
            ORDER BY street ASC, house ASC LIMIT 200
        ");
        $cityStem = getRussianStem($targetCity);
        $fallbackStmt->execute([
            ':c_exact' => $targetCity,
            ':c_stem'  => $cityStem . '%'
        ]);
        $points = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Приведение типов координат
    foreach ($points as &$pt) {
        $pt['lat'] = (float)$pt['lat'];
        $pt['lng'] = (float)$pt['lng'];
    }
    unset($pt);

    $resultCity = $targetCity;
    if (empty($resultCity) && !empty($points)) {
        $resultCity = $points[0]['city'];
    }
    if (empty($resultCity)) {
        $resultCity = 'Пенза';
    }

    $topPriority = ['Пенза', 'Москва', 'Санкт-Петербург', 'Казань', 'Екатеринбург', 'Самара', 'Нижний Новгород'];
    $chipCities = $topPriority;
    if ($resultCity && !in_array($resultCity, $chipCities)) {
        array_unshift($chipCities, $resultCity);
    }

    echo json_encode([
        'success'   => true,
        'count'     => count($points),
        'city'      => $resultCity,
        'cities'    => $chipCities,
        'hasPoints' => count($points) > 0,
        'points'    => $points
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Ошибка сервиса 5Post: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
