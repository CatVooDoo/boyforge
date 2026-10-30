<?php
declare(strict_types=1);

/**
 * Нормализация позиций заказа (поддержка корзины items / items_json и обратной совместимости с одиночными заказами).
 * Не зависит от БД — может подключаться в любых CLI-тестах, шаблонах писем и клиентах доставки.
 */
function normalizeOrderItems(array $order): array {
    $rawItems = null;

    if (!empty($order['items']) && is_array($order['items'])) {
        $rawItems = $order['items'];
    } elseif (!empty($order['items_json'])) {
        $decoded = is_string($order['items_json'])
            ? json_decode($order['items_json'], true)
            : (is_array($order['items_json']) ? $order['items_json'] : null);
        if (is_array($decoded) && !empty($decoded)) {
            $rawItems = $decoded;
        }
    }

    if (is_array($rawItems) && !empty($rawItems)) {
        $normalized = [];
        foreach ($rawItems as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rawPrice = $row['price'] ?? 0;
            if (is_string($rawPrice)) {
                $rawPrice = (float)preg_replace('/[^\d.]/', '', str_replace(',', '.', $rawPrice));
            }
            $price = round((float)$rawPrice, 2);
            $qty = max(1, (int)($row['qty'] ?? $row['quantity'] ?? 1));
            $name = trim((string)($row['name'] ?? $row['productName'] ?? $row['product_name'] ?? 'Товар BOYFORGE'));
            if ($name === '') {
                $name = 'Товар BOYFORGE';
            }

            $normalizedItem = [
                'product_id' => (string)($row['product_id'] ?? $row['productId'] ?? $row['id'] ?? 'BF-1'),
                'name'       => $name,
                'gender'     => trim((string)($row['gender'] ?? '')),
                'size'       => trim((string)($row['size'] ?? '')),
                'qty'        => $qty,
                'price'      => $price > 0 ? $price : 0.0,
                'img'        => trim((string)($row['img'] ?? '')),
            ];
            if (isset($row['original_price'], $row['discount_percent'])) {
                $normalizedItem['original_price'] = round((float)$row['original_price'], 2);
                $normalizedItem['discount_percent'] = (int)$row['discount_percent'];
            }
            $normalized[] = $normalizedItem;
        }

        if (!empty($normalized)) {
            return $normalized;
        }
    }

    $legacyPriceRaw = $order['price'] ?? '3200';
    $legacyPrice = is_numeric($legacyPriceRaw)
        ? round((float)$legacyPriceRaw, 2)
        : (round((float)preg_replace('/[^\d.]/', '', str_replace(',', '.', (string)$legacyPriceRaw)), 2) ?: 3200.0);

    $legacyName = trim((string)($order['product_name'] ?? $order['productName'] ?? 'Товар BOYFORGE'));
    if ($legacyName === '') {
        $legacyName = 'Товар BOYFORGE';
    }

    return [[
        'product_id' => (string)($order['product_id'] ?? $order['productId'] ?? 'BF-1'),
        'name'       => $legacyName,
        'gender'     => trim((string)($order['gender'] ?? '')),
        'size'       => trim((string)($order['size'] ?? '')),
        'qty'        => 1,
        'price'      => $legacyPrice,
        'img'        => '',
    ]];
}

/**
 * Суммарное количество физических единиц товара в заказе.
 */
function orderTotalUnits(array $order): int {
    $items = normalizeOrderItems($order);
    $total = 0;
    foreach ($items as $item) {
        $total += max(1, (int)($item['qty'] ?? 1));
    }
    return max(1, $total);
}

/**
 * Суммарная стоимость товаров (без учета доставки).
 * Принимает как массив нормализованных позиций $items, так и массив заказа $order.
 */
function orderItemsGoodsTotal(array $itemsOrOrder): float {
    if (
        array_key_exists('order_id', $itemsOrOrder)
        || array_key_exists('items_json', $itemsOrOrder)
        || array_key_exists('product_name', $itemsOrOrder)
        || array_key_exists('items', $itemsOrOrder)
    ) {
        $items = normalizeOrderItems($itemsOrOrder);
    } else {
        $items = $itemsOrOrder;
    }

    $sum = 0.0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $qty = max(1, (int)($item['qty'] ?? 1));
        $price = round((float)($item['price'] ?? 0), 2);
        $sum += round($price * $qty, 2);
    }
    return round($sum, 2);
}

/**
 * Человекочитаемое название позиции: «Название (Пол, Размер) × Кол-во».
 */
function formatOrderItemTitle(array $item, bool $includeQty = true): string {
    $name = trim((string)($item['name'] ?? 'Товар BOYFORGE'));
    $gender = trim((string)($item['gender'] ?? ''));
    $size = trim((string)($item['size'] ?? ''));
    $qty = max(1, (int)($item['qty'] ?? 1));

    $variantParts = array_values(array_filter([$gender, $size], static fn(string $v): bool => $v !== ''));
    $title = $name;
    if (!empty($variantParts)) {
        $title .= ' (' . implode(', ', $variantParts) . ')';
    }
    if ($includeQty && $qty > 1) {
        $title .= ' × ' . $qty . ' шт.';
    }
    return $title;
}

/**
 * Сводные поля для сохранения в плоские колонки таблицы orders (совместимость со старыми отчетами и SQL-ограничениями).
 * Ограничения колонок orders: product_id VARCHAR(64), product_name VARCHAR(255), gender VARCHAR(50), size VARCHAR(20).
 */
function summarizeOrderFields(array $items): array {
    if (empty($items)) {
        return [
            'product_id'   => 'BF-1',
            'product_name' => 'Товар BOYFORGE',
            'gender'       => '',
            'size'         => '',
            'price'        => 0.0,
        ];
    }

    $goodsTotal = orderItemsGoodsTotal($items);

    if (count($items) === 1 && (int)$items[0]['qty'] === 1) {
        return [
            'product_id'   => mb_substr((string)$items[0]['product_id'], 0, 64),
            'product_name' => mb_substr((string)$items[0]['name'], 0, 250),
            'gender'       => mb_substr((string)$items[0]['gender'], 0, 50),
            'size'         => mb_substr((string)$items[0]['size'], 0, 20),
            'price'        => $goodsTotal,
        ];
    }

    $ids = array_values(array_unique(array_map(static fn(array $i): string => (string)$i['product_id'], $items)));
    $names = array_map(static fn(array $i): string => formatOrderItemTitle($i, true), $items);

    $uniqueGenders = array_values(array_unique(array_filter(array_map(static fn(array $i): string => (string)$i['gender'], $items))));
    $genderSummary = count($uniqueGenders) === 1
        ? $uniqueGenders[0]
        : implode(', ', $uniqueGenders);

    $sizeParts = array_map(static function (array $i): string {
        $sz = (string)($i['size'] ?: '—');
        $qty = (int)($i['qty'] ?? 1);
        return $qty > 1 ? "{$sz}×{$qty}" : $sz;
    }, $items);
    $sizeSummary = implode(',', $sizeParts);
    if (mb_strlen($sizeSummary) > 20) {
        $totalQty = array_sum(array_map(static fn(array $i): int => (int)$i['qty'], $items));
        $sizeSummary = mb_substr($totalQty . ' шт. (корзина)', 0, 20);
    }

    return [
        'product_id'   => mb_substr(implode(',', $ids), 0, 64),
        'product_name' => mb_substr(implode('; ', $names), 0, 250),
        'gender'       => mb_substr($genderSummary, 0, 50),
        'size'         => mb_substr($sizeSummary, 0, 20),
        'price'        => $goodsTotal,
    ];
}

/**
 * Разворачивает позиции заказа в физические штуки (по 1 ед.) для поштучного сканирования Честного ЗНАКа.
 */
function expandOrderItemsToUnits(array $order): array {
    $items = normalizeOrderItems($order);
    $units = [];
    $unitIndex = 0;

    foreach ($items as $item) {
        $qty = max(1, (int)($item['qty'] ?? 1));
        $baseTitle = formatOrderItemTitle($item, false);
        for ($i = 1; $i <= $qty; $i++) {
            $units[] = [
                'index'      => $unitIndex++,
                'product_id' => (string)$item['product_id'],
                'name'       => (string)$item['name'],
                'gender'     => (string)$item['gender'],
                'size'       => (string)$item['size'],
                'price'      => round((float)$item['price'], 2),
                'label'      => $qty > 1 ? "{$baseTitle} [шт. {$i}/{$qty}]" : $baseTitle,
            ];
        }
    }

    return $units;
}

/**
 * Приведение отсканированного кода маркировки Честного ЗНАКа к формату GS1 DataMatrix с разделителями \x1D.
 */
function normalizeChestnyZnakCode(string $raw): string {
    $code = trim($raw, " \t\n\r\0\x0B");
    if (strlen($code) === 83 && preg_match('/^(01\d{14}21.{13})(91.{4})(92.{44})$/', $code, $matches)) {
        $code = $matches[1] . "\x1D" . $matches[2] . "\x1D" . $matches[3];
    }
    return $code;
}

/**
 * Возвращает массив сохраненных кодов маркировки заказа.
 */
function getOrderMarkCodes(array $order): array {
    if (!empty($order['mark_codes_json']) && is_string($order['mark_codes_json'])) {
        $decoded = json_decode($order['mark_codes_json'], true);
        if (is_array($decoded)) {
            $codes = array_values(array_filter(
                array_map(static fn($c): string => normalizeChestnyZnakCode((string)$c), $decoded),
                static fn(string $c): bool => $c !== '' && $c !== '__CANCELLED__'
            ));
            if (!empty($codes)) {
                return $codes;
            }
        }
    }

    $single = trim((string)($order['mark_code'] ?? ''));
    if ($single === '' || $single === '__CANCELLED__') {
        return [];
    }

    $parts = preg_split('/\r\n|\r|\n/', $single) ?: [];
    $codes = [];
    foreach ($parts as $part) {
        $norm = normalizeChestnyZnakCode($part);
        if ($norm !== '') {
            $codes[] = $norm;
        }
    }
    return $codes;
}

/**
 * Формирует массив items для чека предоплаты (54-ФЗ full_prepayment) в ЮKassa /v3/payments.
 * В ЮKassa amount.value всегда указывается за 1 единицу товара, а quantity — количество штук.
 */
function buildYookassaPrepaymentItems(array $items, int $vatCode, float $deliveryCost = 0.0, string $orderId = ''): array {
    $receiptItems = [];
    $prefix = $orderId !== '' ? "Заказ {$orderId}: " : '';
    $isSingleLine = count($items) === 1;

    foreach ($items as $item) {
        $qty = max(1, (int)($item['qty'] ?? 1));
        $unitPrice = round((float)($item['price'] ?? 0), 2);
        $itemLabel = $isSingleLine
            ? trim((string)($item['name'] ?? 'Товар BOYFORGE'))
            : formatOrderItemTitle($item, false);
        $desc = $prefix . $itemLabel;

        $receiptItems[] = [
            'description'     => mb_substr($desc, 0, 128),
            'quantity'        => (float)$qty,
            'amount'          => [
                'value'    => number_format($unitPrice, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'vat_code'        => $vatCode,
            'payment_mode'    => 'full_prepayment',
            'payment_subject' => 'commodity',
            'measure'         => 'piece',
        ];
    }

    if ($deliveryCost > 0) {
        $receiptItems[] = [
            'description'     => 'Доставка СДЭК до пункта выдачи',
            'quantity'        => 1.000,
            'amount'          => [
                'value'    => number_format($deliveryCost, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'vat_code'        => $vatCode,
            'payment_mode'    => 'full_prepayment',
            'payment_subject' => 'service',
        ];
    }

    return $receiptItems;
}

/**
 * Формирует payload закрывающего чека («Полный расчет», 54-ФЗ / ФФД 1.2) для ЮKassa /v3/receipts.
 * Каждая физическая единица одежды передается отдельной позицией quantity=1.000 со своим кодом Честного ЗНАКа.
 */
function buildYookassaClosingReceiptPayload(
    array $order,
    array $markCodes,
    int $taxSystemCode,
    int $vatCode
): array {
    $units = expandOrderItemsToUnits($order);
    $deliveryCost = round((float)($order['delivery_cost'] ?? 0), 2);
    $email = !empty($order['email']) ? (string)$order['email'] : 'no-reply@boyforge.com';

    $normalizedCodes = array_values(array_filter(
        array_map(static fn($c): string => normalizeChestnyZnakCode((string)$c), $markCodes),
        static fn(string $c): bool => $c !== ''
    ));

    $isMarked = !empty($normalizedCodes);
    if ($isMarked && count($normalizedCodes) !== count($units)) {
        throw new InvalidArgumentException(sprintf(
            'Необходимо отсканировать код маркировки для каждой единицы товара (%d из %d).',
            count($normalizedCodes),
            count($units)
        ));
    }

    if ($isMarked && count(array_unique($normalizedCodes)) !== count($normalizedCodes)) {
        throw new InvalidArgumentException('Коды маркировки не могут повторяться — отсканируйте уникальный DataMatrix для каждой единицы.');
    }

    $receiptItems = [];
    $goodsTotal = 0.0;

    foreach ($units as $idx => $unit) {
        $unitPrice = round((float)$unit['price'], 2);
        $goodsTotal += $unitPrice;
        $unitCode = $isMarked ? $normalizedCodes[$idx] : '';
        $unitTitle = $unit['name'] . ($unit['size'] !== '' ? ' (' . $unit['size'] . ')' : '');

        $itemEntry = [
            'description'     => mb_substr('Отгрузка: ' . $unitTitle, 0, 128),
            'quantity'        => '1.000',
            'amount'          => [
                'value'    => number_format($unitPrice, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'vat_code'        => $vatCode,
            'payment_subject' => $isMarked ? 'marked' : 'commodity',
            'payment_mode'    => 'full_payment',
        ];

        if ($isMarked) {
            $itemEntry['mark_code_info'] = [
                'gs_1m' => $unitCode,
            ];
        }

        $itemEntry['measure'] = 'piece';
        $receiptItems[] = $itemEntry;
    }

    if ($deliveryCost > 0) {
        $receiptItems[] = [
            'description'     => 'Доставка СДЭК до пункта выдачи',
            'quantity'        => '1.000',
            'amount'          => [
                'value'    => number_format($deliveryCost, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'vat_code'        => $vatCode,
            'payment_subject' => 'service',
            'payment_mode'    => 'full_payment',
        ];
    }

    $settlementTotal = round($goodsTotal + $deliveryCost, 2);

    return [
        'customer'        => [
            'email' => $email,
        ],
        'type'            => 'payment',
        'send'            => true,
        'payment_id'      => (string)($order['payment_transaction_id'] ?? $order['transaction_id'] ?? ''),
        'tax_system_code' => $taxSystemCode,
        'settlements'     => [
            [
                'type'   => 'prepayment',
                'amount' => [
                    'value'    => number_format($settlementTotal, 2, '.', ''),
                    'currency' => 'RUB',
                ],
            ],
        ],
        'items'           => $receiptItems,
    ];
}

/**
 * Формирует объект receipt для чека возврата (54-ФЗ) в запросе ЮKassa POST /v3/refunds.
 * Сохраняет 100% структуру параметров старого рабочего кода (internet, timezone, type, send, payment_id, settlements, full_payment)
 * и разворачивает массив items при возврате корзины из нескольких товаров / маркированных единиц / доставки СДЭК.
 */
function buildYookassaRefundReceiptPayload(
    array $order,
    float $refundAmount,
    string $paymentId,
    int $taxSystemCode,
    int $vatCode
): array {
    $items = normalizeOrderItems($order);
    $units = expandOrderItemsToUnits($order);
    $goodsTotal = orderItemsGoodsTotal($items);
    $deliveryCost = round((float)($order['delivery_cost'] ?? 0), 2);
    $fullOrderTotal = round($goodsTotal + $deliveryCost, 2);
    $customerEmail = !empty($order['email']) ? (string)$order['email'] : 'no-reply@boyforge.com';
    $amountValue = number_format($refundAmount, 2, '.', '');

    $markCodes = getOrderMarkCodes($order);
    $hasAllUnitMarks = !empty($markCodes) && count($markCodes) === count($units);

    $isFullRefund = abs($refundAmount - $fullOrderTotal) < 0.01;
    $isGoodsOnlyRefund = ($deliveryCost > 0) && abs($refundAmount - $goodsTotal) < 0.01;

    if (count($units) > 1 && !$isFullRefund && !$isGoodsOnlyRefund) {
        throw new InvalidArgumentException('Частичный возврат корзины требует выбора возвращаемых единиц товара. Сейчас доступен возврат всех товаров с доставкой или без неё.');
    }

    $receiptItems = [];

    if ($isFullRefund || $isGoodsOnlyRefund) {
        if ($hasAllUnitMarks) {
            $isSingleUnit = count($units) === 1;
            foreach ($units as $idx => $unit) {
                $unitTitle = $isSingleUnit ? $unit['name'] : formatOrderItemTitle($unit, false);
                $receiptItems[] = [
                    'description'     => mb_substr('Возврат: ' . $unitTitle, 0, 128),
                    'quantity'        => '1.000',
                    'amount'          => [
                        'value'    => number_format((float)$unit['price'], 2, '.', ''),
                        'currency' => 'RUB',
                    ],
                    'vat_code'        => $vatCode,
                    'payment_subject' => 'marked',
                    'payment_mode'    => 'full_payment',
                    'measure'         => 'piece',
                    'mark_code_info'  => [
                        'gs_1m' => $markCodes[$idx],
                    ],
                ];
            }
        } else {
            $isSingleLine = count($items) === 1;
            foreach ($items as $item) {
                $qty = max(1, (int)($item['qty'] ?? 1));
                $itemTitle = $isSingleLine ? $item['name'] : formatOrderItemTitle($item, false);
                $receiptItems[] = [
                    'description'     => mb_substr('Возврат: ' . $itemTitle, 0, 128),
                    'quantity'        => number_format((float)$qty, 3, '.', ''),
                    'amount'          => [
                        'value'    => number_format((float)$item['price'], 2, '.', ''),
                        'currency' => 'RUB',
                    ],
                    'vat_code'        => $vatCode,
                    'payment_subject' => 'commodity',
                    'payment_mode'    => 'full_payment',
                    'measure'         => 'piece',
                ];
            }
        }

        if ($isFullRefund && $deliveryCost > 0) {
            $receiptItems[] = [
                'description'     => 'Возврат: Доставка СДЭК до пункта выдачи',
                'quantity'        => '1.000',
                'amount'          => [
                    'value'    => number_format($deliveryCost, 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'vat_code'        => $vatCode,
                'payment_subject' => 'service',
                'payment_mode'    => 'full_payment',
            ];
        }
    } else {
        $singleMark = (count($markCodes) === 1 && count($units) === 1) ? $markCodes[0] : '';
        $entry = [
            'description'     => mb_substr('Возврат: ' . (string)($order['product_name'] ?? 'Заказ'), 0, 128),
            'quantity'        => '1.000',
            'amount'          => [
                'value'    => $amountValue,
                'currency' => 'RUB',
            ],
            'vat_code'        => $vatCode,
            'payment_subject' => $singleMark !== '' ? 'marked' : 'commodity',
            'payment_mode'    => 'full_payment',
            'measure'         => 'piece',
        ];
        if ($singleMark !== '') {
            $entry['mark_code_info'] = ['gs_1m' => $singleMark];
        }
        $receiptItems[] = $entry;
    }

    return [
        'internet'        => true,
        'timezone'        => 2,
        'customer'        => [
            'email' => $customerEmail,
        ],
        'type'            => 'refund',
        'send'            => true,
        'payment_id'      => $paymentId,
        'tax_system_code' => $taxSystemCode,
        'settlements'     => [
            [
                'type'   => 'prepayment',
                'amount' => [
                    'value'    => $amountValue,
                    'currency' => 'RUB',
                ],
            ],
        ],
        'items'           => $receiptItems,
    ];
}
