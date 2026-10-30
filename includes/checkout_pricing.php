<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/promocodes.php';

/** Один расчёт для предварительной проверки промокода и фактического создания платежа. */
function priceCheckout(PDO $pdo, array $input): array {
    $rawItems = $input['items'] ?? null;
    if (!is_array($rawItems) || !$rawItems) {
        $rawItems = [['productId' => $input['productId'] ?? '', 'gender' => $input['gender'] ?? '',
            'size' => $input['size'] ?? '', 'qty' => $input['qty'] ?? 1]];
    }
    if (count($rawItems) > 50) throw new InvalidArgumentException('Слишком много позиций в заказе.');
    $byId = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
    $byCategory = $pdo->prepare('SELECT * FROM products WHERE cat_id = ? AND is_active = 1 ORDER BY id LIMIT 1');
    $merged = [];
    foreach ($rawItems as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Некорректная позиция заказа.');
        $id = trim((string)($row['productId'] ?? $row['product_id'] ?? $row['id'] ?? ''));
        if ($id === '') throw new InvalidArgumentException('Не указан товар.');
        $stmt = ctype_digit($id) ? $byId : $byCategory;
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) throw new InvalidArgumentException('Товар не найден или снят с публикации.');
        $gender = trim((string)($row['gender'] ?? ''));
        $size = trim((string)($row['size'] ?? ''));
        $error = productVariantError($product, $gender, $size);
        if ($error !== '') throw new InvalidArgumentException($error);
        $price = round((float)preg_replace('/[^\d.]/', '', str_replace(',', '.', (string)$product['price'])), 2);
        if ($price <= 0) $price = round((float)($product['price_numeric'] ?? 0), 2);
        if ($price <= 0) throw new InvalidArgumentException('Некорректная цена товара.');
        $qty = max(1, min(20, (int)($row['qty'] ?? $row['quantity'] ?? 1)));
        $key = $product['id'] . '__' . $gender . '__' . $size;
        if (isset($merged[$key])) {
            $merged[$key]['qty'] = min(20, $merged[$key]['qty'] + $qty);
        } else {
            $img = (string)($product['img'] ?? '');
            $merged[$key] = ['product_id' => (string)$product['id'], 'name' => $product['name'],
                'gender' => $gender, 'size' => $size, 'qty' => $qty, 'price' => $price,
                'img' => $img === '' ? '' : '/' . ltrim($img, '/'),
                'category_slug' => (string)$product['cat_id'], 'category_name' => (string)($product['cat'] ?? '')];
        }
    }
    $items = array_values($merged);
    if (!$items) throw new InvalidArgumentException('Корзина пуста.');
    if (orderTotalUnits(['items' => $items]) > 50) throw new InvalidArgumentException('В одном заказе может быть не более 50 единиц товара.');
    $code = trim((string)($input['promoCode'] ?? ''));
    return applyPromoToItems($items, $code === '' ? null : findActivePromo($pdo, $code));
}
