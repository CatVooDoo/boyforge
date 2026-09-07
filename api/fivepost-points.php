<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';

$city   = isset($_GET['city']) ? trim((string)$_GET['city']) : '';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

try {
    if ($action === 'cities') {
        $stmt = $pdo->query("SELECT DISTINCT city, COUNT(*) as count FROM fivepost_points WHERE is_active = 1 GROUP BY city ORDER BY city ASC");
        $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'cities' => $cities], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "SELECT id, name, partner_name, type, city, street, house, full_address, lat, lng, work_hours, additional, phone 
            FROM fivepost_points 
            WHERE is_active = 1";
    $params = [];

    if (!empty($city)) {
        $sql .= " AND (city = :city OR full_address LIKE :cityLike)";
        $params[':city'] = $city;
        $params[':cityLike'] = '%' . $city . '%';
    }

    if (!empty($search)) {
        $sql .= " AND (name LIKE :search OR full_address LIKE :search OR street LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY street ASC, house ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Приведение типов координат
    foreach ($points as &$pt) {
        $pt['lat'] = (float)$pt['lat'];
        $pt['lng'] = (float)$pt['lng'];
    }
    unset($pt);

    // Доступные города
    $citiesStmt = $pdo->query("SELECT DISTINCT city FROM fivepost_points WHERE is_active = 1 ORDER BY city ASC");
    $allCities = $citiesStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'count'   => count($points),
        'cities'  => $allCities,
        'points'  => $points
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Ошибка базы данных: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
