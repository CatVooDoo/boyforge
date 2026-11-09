<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/router.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
$rawItems = is_array($input['items'] ?? null) ? $input['items'] : [];

if (empty($rawItems)) {
    echo json_encode(['success' => true, 'items' => [], 'total' => 0, 'totalUnits' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

global $pdo;
$stmtById = $pdo->prepare("SELECT id, slug, cat_id, name, price, price_numeric, img, sizes, is_active FROM products WHERE id = :id LIMIT 1");
$stmtByCat = $pdo->prepare("SELECT id, slug, cat_id, name, price, price_numeric, img, sizes, is_active FROM products WHERE cat_id = :cat_id LIMIT 1");

$validated = [];
$total = 0.0;
$totalUnits = 0;

foreach (array_slice($rawItems, 0, 50) as $row) {
    if (!is_array($row)) {
        continue;
    }

    $productIdRaw = trim((string)($row['productId'] ?? $row['product_id'] ?? $row['id'] ?? ''));
    $gender = trim((string)($row['gender'] ?? ''));
    $size = trim((string)($row['size'] ?? ''));
    $qty = max(1, min(20, (int)($row['qty'] ?? 1)));

    if ($productIdRaw === '') {
        continue;
    }

    if (ctype_digit($productIdRaw)) {
        $stmtById->execute([':id' => (int)$productIdRaw]);
        $product = $stmtById->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmtByCat->execute([':cat_id' => $productIdRaw]);
        $product = $stmtByCat->fetch(PDO::FETCH_ASSOC);
    }

    if (!$product || (int)($product['is_active'] ?? 0) !== 1) {
        $validated[] = [
            'key'          => (string)($row['key'] ?? ($productIdRaw . '__' . $gender . '__' . $size)),
            'productId'    => $productIdRaw,
            'name'         => trim((string)($row['name'] ?? 'Товар недоступен')),
            'gender'       => $gender,
            'size'         => $size,
            'qty'          => $qty,
            'price'        => (string)($row['price'] ?? '0 ₽'),
            'priceNumeric' => 0,
            'img'          => (string)($row['img'] ?? ''),
            'url'          => '/catalog',
            'available'    => false,
            'reason'       => 'Товар снят с публикации',
        ];
        continue;
    }

    $product = withProductSizeMode($pdo, $product);
    if (!productHasDualSizes($product)) $gender = '';

    $priceStr = (string)($product['price'] ?? '0');
    $unitPrice = round((float)preg_replace('/[^\d.]/', '', str_replace(',', '.', $priceStr)), 2);
    if ($unitPrice <= 0 && !empty($product['price_numeric'])) {
        $unitPrice = round((float)$product['price_numeric'], 2);
    }

    $reason = productVariantError($product, $gender, $size);
    if ($reason === '' && $unitPrice <= 0) {
        $reason = 'Некорректная цена';
    }
    $available = $reason === '';

    $imgRaw = (string)($product['img'] ?? '');
    $img = $imgRaw !== '' ? ('/' . ltrim($imgRaw, '/')) : '';
    $pid = (int)$product['id'];

    if ($available) {
        $total += round($unitPrice * $qty, 2);
        $totalUnits += $qty;
    }

    $validated[] = [
        'key'          => $pid . '__' . $gender . '__' . $size,
        'productId'    => (string)$pid,
        'name'         => (string)$product['name'],
        'gender'       => $gender,
        'size'         => $size,
        'qty'          => $qty,
        'price'        => (string)$product['price'],
        'priceNumeric' => $unitPrice,
        'img'          => $img,
        'url'          => productUrl($pid, $product['slug'] ?? null),
        'available'    => $available,
        'reason'       => $reason,
    ];
}

echo json_encode([
    'success'    => true,
    'items'      => $validated,
    'total'      => round($total, 2),
    'totalUnits' => $totalUnits,
], JSON_UNESCAPED_UNICODE);
