<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

$envFile = dirname(__DIR__, 2) . '/.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $env[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
        }
    }
}

define('BOYFORGE_ADMIN_PASS', $env['ADMIN_PASSWORD'] ?? 'boyforge2026');

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
} catch (PDOException $e) {
    die("Database connection error: " . htmlspecialchars($e->getMessage()));
}

function initDatabase(PDO $pdo): void {
    $sql = "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cat_id VARCHAR(64) NOT NULL DEFAULT 'tshirt',
        cat VARCHAR(128) NOT NULL DEFAULT 'Футболка',
        name VARCHAR(255) NOT NULL,
        price VARCHAR(64) NOT NULL DEFAULT '3 200 ₽',
        price_numeric INT UNSIGNED NOT NULL DEFAULT 0,
        img VARCHAR(255) NOT NULL DEFAULT '',
        imgs JSON NULL,
        sub VARCHAR(255) NULL,
        tags JSON NULL,
        description TEXT NULL,
        specs JSON NULL,
        tg_link VARCHAR(500) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);

    $count = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($count === 0) {
        $baseSpecs = json_encode([
            ["Материал", "Футер 2-нитка, 95% хлопок / 5% эластан"],
            ["Плотность", "240 г/м²"],
            ["Печать", "DTF"],
            ["Размеры", "S–3XL"],
            ["Пошив", "Россия"]
        ], JSON_UNESCAPED_UNICODE);

        $initialProducts = [
            [
                'id' => 1,
                'cat_id' => 'tshirt',
                'cat' => 'Футболка',
                'name' => 'Футболка «Братья Святославичи»',
                'price' => '3 200 ₽',
                'price_numeric' => 3200,
                'img' => 'images/bratya/p-bratya.jpg',
                'imgs' => json_encode([
                    'images/bratya/p-bratya.jpg',
                    'images/bratya/p-bratya-2.jpg',
                    'images/bratya/p-bratya-3.jpg',
                    'images/bratya/p-bratya-4.jpg',
                    'images/bratya/p-bratya-5.jpg',
                    'images/bratya/p-bratya-6.jpg',
                    'images/bratya/p-bratya-7.jpg'
                ], JSON_UNESCAPED_UNICODE),
                'sub' => 'Футболка · 95% хлопок / 5% эластан',
                'tags' => json_encode(['S–3XL', 'Новая коллекция'], JSON_UNESCAPED_UNICODE),
                'description' => 'Футболка «Братья Святославичи» — авторский принт. Футер 2-нитка (95% хлопок / 5% эластан), 240 г/м², DTF-печать: мягкая, не трескается и держит цвет после стирок. Ровный крой, комфортная посадка на каждый день.',
                'specs' => $baseSpecs,
                'tg_link' => 'https://telegram.me/theboyforge?text=' . rawurlencode('Здравствуйте! Хочу заказать: Братья Святославичи'),
                'is_active' => 1,
                'sort_order' => 1
            ],
            [
                'id' => 2,
                'cat_id' => 'tshirt',
                'cat' => 'Футболка',
                'name' => 'Футболка «Врёшь Кривжа»',
                'price' => '3 200 ₽',
                'price_numeric' => 3200,
                'img' => 'images/krivzha/p-krivzha.jpg',
                'imgs' => json_encode([
                    'images/krivzha/p-krivzha.jpg',
                    'images/krivzha/p-krivzha-2.jpg',
                    'images/krivzha/p-krivzha-3.jpg',
                    'images/krivzha/p-krivzha-4.jpg',
                    'images/krivzha/p-krivzha-5.jpg',
                    'images/krivzha/p-krivzha-6.jpg',
                    'images/krivzha/p-krivzha-7.jpg'
                ], JSON_UNESCAPED_UNICODE),
                'sub' => 'Футболка · 95% хлопок / 5% эластан',
                'tags' => json_encode(['S–3XL', 'Новая коллекция'], JSON_UNESCAPED_UNICODE),
                'description' => 'Футболка «Врёшь Кривжа» — авторский принт. Футер 2-нитка (95% хлопок / 5% эластан), 240 г/м², стойкая DTF-печать с насыщенными деталями. Носится легко и сочетается с любым гардеробом.',
                'specs' => $baseSpecs,
                'tg_link' => 'https://telegram.me/theboyforge?text=' . rawurlencode('Здравствуйте! Хочу заказать: Врёшь Кривжа'),
                'is_active' => 1,
                'sort_order' => 2
            ],
            [
                'id' => 3,
                'cat_id' => 'tshirt',
                'cat' => 'Футболка',
                'name' => 'Футболка «Варяга меч кормит»',
                'price' => '3 200 ₽',
                'price_numeric' => 3200,
                'img' => 'images/varyag/p-varyag.jpg',
                'imgs' => json_encode([
                    'images/varyag/p-varyag.jpg',
                    'images/varyag/p-varyag-2.jpg',
                    'images/varyag/p-varyag-3.jpg',
                    'images/varyag/p-varyag-4.jpg',
                    'images/varyag/p-varyag-5.jpg',
                    'images/varyag/p-varyag-6.jpg',
                    'images/varyag/p-varyag-7.jpg'
                ], JSON_UNESCAPED_UNICODE),
                'sub' => 'Футболка · 95% хлопок / 5% эластан',
                'tags' => json_encode(['S–3XL', 'Новая коллекция'], JSON_UNESCAPED_UNICODE),
                'description' => 'Футболка «Варяга меч кормит» — авторский принт. Футер 2-нитка (95% хлопок / 5% эластан), 240 г/м², DTF-печать — мягкий стойкий принт. Уход простой: стирка при 30°, без отбеливателя.',
                'specs' => $baseSpecs,
                'tg_link' => 'https://telegram.me/theboyforge?text=' . rawurlencode('Здравствуйте! Хочу заказать: Варяга меч кормит'),
                'is_active' => 1,
                'sort_order' => 3
            ],
            [
                'id' => 4,
                'cat_id' => 'tshirt',
                'cat' => 'Футболка',
                'name' => 'Футболка «Рано меня похоронили»',
                'price' => '3 200 ₽',
                'price_numeric' => 3200,
                'img' => 'images/ranopoh/p-ranopoh.jpg',
                'imgs' => json_encode([
                    'images/ranopoh/p-ranopoh.jpg',
                    'images/ranopoh/p-ranopoh-2.jpg',
                    'images/ranopoh/p-ranopoh-3.jpg',
                    'images/ranopoh/p-ranopoh-4.jpg'
                ], JSON_UNESCAPED_UNICODE),
                'sub' => 'Футболка · 95% хлопок / 5% эластан',
                'tags' => json_encode(['S–3XL', 'Хит'], JSON_UNESCAPED_UNICODE),
                'description' => 'Футболка «Рано меня похоронили» — авторский принт. Футер 2-нитка (95% хлопок / 5% эластан), 240 г/м², стойкая DTF-печать. Ровный крой, комфортная посадка на каждый день.',
                'specs' => $baseSpecs,
                'tg_link' => 'https://telegram.me/theboyforge?text=' . rawurlencode('Здравствуйте! Хочу заказать: Рано меня похоронили'),
                'is_active' => 1,
                'sort_order' => 4
            ]
        ];

        $stmt = $pdo->prepare("INSERT INTO products (id, cat_id, cat, name, price, price_numeric, img, imgs, sub, tags, description, specs, tg_link, is_active, sort_order)
            VALUES (:id, :cat_id, :cat, :name, :price, :price_numeric, :img, :imgs, :sub, :tags, :description, :specs, :tg_link, :is_active, :sort_order)");

        foreach ($initialProducts as $p) {
            $stmt->execute($p);
        }
    }

    $sqlCats = "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(64) NOT NULL UNIQUE,
        name VARCHAR(128) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sqlCats);

    $catCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($catCount === 0) {
        $initialCats = [
            ['slug' => 'tshirt', 'name' => 'Футболка', 'sort_order' => 1],
            ['slug' => 'sweatshirt', 'name' => 'Свитшот', 'sort_order' => 2],
            ['slug' => 'hoodie', 'name' => 'Худи', 'sort_order' => 3],
            ['slug' => 'longsleeve', 'name' => 'Лонгслив', 'sort_order' => 4],
            ['slug' => 'cap', 'name' => 'Кепка', 'sort_order' => 5],
            ['slug' => 'accessories', 'name' => 'Аксессуары', 'sort_order' => 6],
        ];

        $stmtCat = $pdo->prepare("INSERT INTO categories (slug, name, sort_order, is_active) VALUES (:slug, :name, :sort_order, 1)");
        foreach ($initialCats as $c) {
            $stmtCat->execute($c);
        }
    }

    $sqlTags = "CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sqlTags);

    $tagsCount = (int)$pdo->query("SELECT COUNT(*) FROM tags")->fetchColumn();
    if ($tagsCount === 0) {
        $initialTags = ['Новая коллекция', 'Хит', 'S–3XL', 'Оверсайз', 'Лимитированный тираж'];
        $stmtTag = $pdo->prepare("INSERT IGNORE INTO tags (name) VALUES (:name)");
        foreach ($initialTags as $t) {
            $stmtTag->execute([':name' => $t]);
        }

        // Import existing tags from products if any
        $existingProductTags = $pdo->query("SELECT tags FROM products WHERE tags IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($existingProductTags as $tJson) {
            $parsed = json_decode((string)$tJson, true);
            if (is_array($parsed)) {
                foreach ($parsed as $singleTag) {
                    $singleTag = trim((string)$singleTag);
                    if ($singleTag !== '') {
                        $stmtTag->execute([':name' => $singleTag]);
                    }
                }
            }
        }
    }
}

initDatabase($pdo);

function getCsrfToken(): string {
    if (empty($_SESSION['boyforge_csrf_token'])) {
        $_SESSION['boyforge_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['boyforge_csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['boyforge_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['boyforge_csrf_token'], $token);
}

function checkCsrfToken(?string $token): bool {
    return verifyCsrfToken($token);
}

function isAdminLoggedIn(): bool {
    return !empty($_SESSION['boyforge_admin_logged']);
}

function requireAdminAuth(): void {
    if (!isAdminLoggedIn()) {
        $loginUrl = 'login.php';
        if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
            header("Location: {$loginUrl}");
            exit;
        }
    }
}
