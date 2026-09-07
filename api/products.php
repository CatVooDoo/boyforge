<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $env[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
        }
    }
}

$dbHost = $env['DB_HOST'] ?? getenv('DB_HOST') ?: 'mariadb';
$dbPort = $env['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$dbName = $env['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'boyforge';
$dbUser = $env['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'boyforge';
$dbPass = $env['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'f8a582d157';

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cat = isset($_GET['cat']) ? trim((string)$_GET['cat']) : '';

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND is_active = 1 LIMIT 1");
    $stmt->execute([':id' => $id]);
    $p = $stmt->fetch();

    if (!$p) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $item = [
        'id' => (int)$p['id'],
        'catId' => $p['cat_id'],
        'cat' => $p['cat'],
        'name' => $p['name'],
        'price' => $p['price'],
        'img' => $p['img'],
        'imgs' => json_decode($p['imgs'] ?? '[]', true) ?: [$p['img']],
        'sub' => $p['sub'],
        'tags' => json_decode($p['tags'] ?? '[]', true) ?: [],
        'desc' => $p['description'],
        'specs' => json_decode($p['specs'] ?? '[]', true) ?: [],
        'tg' => $p['tg_link']
    ];

    echo json_encode(['success' => true, 'product' => $item], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "SELECT * FROM products WHERE is_active = 1";
$params = [];

if ($cat !== '') {
    $sql .= " AND cat_id = :cat";
    $params[':cat'] = $cat;
}

$sql .= " ORDER BY sort_order ASC, id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$items = [];
foreach ($rows as $p) {
    $items[] = [
        'id' => (int)$p['id'],
        'catId' => $p['cat_id'],
        'cat' => $p['cat'],
        'name' => $p['name'],
        'price' => $p['price'],
        'img' => $p['img'],
        'imgs' => json_decode($p['imgs'] ?? '[]', true) ?: [$p['img']],
        'sub' => $p['sub'],
        'tags' => json_decode($p['tags'] ?? '[]', true) ?: [],
        'desc' => $p['description'],
        'specs' => json_decode($p['specs'] ?? '[]', true) ?: [],
        'tg' => $p['tg_link']
    ];
}

echo json_encode(['success' => true, 'count' => count($items), 'products' => $items], JSON_UNESCAPED_UNICODE);
