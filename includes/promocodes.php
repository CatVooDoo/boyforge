<?php
declare(strict_types=1);

require_once __DIR__ . '/order_items.php';

function normalizePromoCode(string $code): string {
    return mb_strtoupper(trim($code), 'UTF-8');
}

function validatePromoCode(string $code): string {
    $code = normalizePromoCode($code);
    if ($code === '' || mb_strlen($code, 'UTF-8') > 32 || preg_match('/[\p{C}\p{Z}\s]/u', $code) !== 0) {
        throw new InvalidArgumentException('Промокод должен содержать от 1 до 32 символов без пробелов.');
    }
    return $code;
}

function generatePromoCode(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 10; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $code;
}

/** Используется при открытии админки; публичные API не меняют схему БД. */
function ensurePromoSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS promocodes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL UNIQUE,
        category_id INT NOT NULL,
        discount_percent TINYINT UNSIGNED NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_promocode_category (category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $columns = array_column($pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('promo_code', $columns, true)) $pdo->exec('ALTER TABLE orders ADD COLUMN promo_code VARCHAR(32) NULL');
    if (!in_array('discount_amount', $columns, true)) $pdo->exec('ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0');
}

/** Скидка округляется за единицу, поэтому суммы платежа, чеков и маркировки совпадают. */
function applyPromoToItems(array $items, ?array $promo): array {
    $subtotal = orderItemsGoodsTotal($items);
    $matched = false;
    if ($promo !== null) {
        $percent = (int)($promo['discount_percent'] ?? 0);
        if ($percent < 1 || $percent > 99) throw new InvalidArgumentException('Некорректная скидка промокода.');
        foreach ($items as &$item) {
            if ((string)($item['category_slug'] ?? '') !== (string)$promo['category_slug']
                && (string)($item['category_name'] ?? '') !== (string)$promo['category_name']) continue;
            $matched = true;
            $originalCents = (int)round((float)$item['price'] * 100);
            $discountedCents = (int)round($originalCents * (100 - $percent) / 100);
            if ($discountedCents < 1) throw new InvalidArgumentException('Цена товара после скидки должна быть не меньше 1 копейки.');
            $item['original_price'] = $item['price'];
            $item['price'] = $discountedCents / 100;
            $item['discount_percent'] = $percent;
        }
        unset($item);
        if (!$matched) throw new InvalidArgumentException('В заказе нет товаров категории «' . $promo['category_name'] . '».');
    }
    $total = orderItemsGoodsTotal($items);
    return ['items' => $items, 'subtotal' => $subtotal, 'total' => $total,
        'discount' => round($subtotal - $total, 2),
        'promo' => $promo === null ? null : ['code' => $promo['code'], 'percent' => (int)$promo['discount_percent'], 'category' => $promo['category_name']]];
}

function findActivePromo(PDO $pdo, string $code): array {
    $code = validatePromoCode($code);
    $stmt = $pdo->prepare('SELECT p.code, p.discount_percent, c.slug AS category_slug, c.name AS category_name
        FROM promocodes p JOIN categories c ON c.id = p.category_id
        WHERE p.code = ? AND p.is_active = 1 AND c.is_active = 1 LIMIT 1');
    $stmt->execute([$code]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$promo) throw new InvalidArgumentException('Промокод не найден или отключён.');
    return $promo;
}
