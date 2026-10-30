<?php
declare(strict_types=1);
// Standalone contract tests for multi-item basket, YooKassa 54-FZ/FFD 1.2 receipts, CDEK, 5Post & email receipt formatting.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/order_items.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cdek.php';

function logOrderEvent(string $id, string $event, array $data = []): void {}

function check(bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo "OK: $message\n";
}

$variantProduct = ['name' => 'Футболка', 'sizes' => ['Мужской' => ['L']]];
foreach ([['', ''], ['', 'M'], ['Мужской', ''], ['Женский', '3XL'], ['Мужской', 'L']] as [$gender, $size]) {
    check(productVariantError($variantProduct, $gender, $size) !== '', 'empty, invalid or unavailable variant is rejected: ' . $gender . '/' . $size);
}
check(productVariantError($variantProduct, 'Мужской', 'M') === '', 'available variant is accepted');

class FakeBasketCdek extends CdekClient {
    public array $calls = [];
    public array $responses = [];
    protected function http(string $method, string $path, array $data = [], bool $auth = true): array {
        $this->calls[] = compact('method', 'path', 'data');
        return array_shift($this->responses) ?? [];
    }
}

// 1. Multi-item normalization & DB column-width summary safety
$sampleItems = [
    [
        'product_id' => '101',
        'name'       => 'Худи BOYFORGE «Кузница» оверсайз с авторским принтом на спине',
        'gender'     => 'Мужской',
        'size'       => 'XL',
        'qty'        => 2,
        'price'      => 4900,
    ],
    [
        'product_id' => '102',
        'name'       => 'Футболка BOYFORGE «Тишина»',
        'gender'     => 'Женский',
        'size'       => 'S',
        'qty'        => 1,
        'price'      => 3200,
    ],
];

$summary = summarizeOrderFields($sampleItems);
check($summary['price'] === 13000.0, 'goods total sums qty * unit price across all items (2*4900 + 1*3200 = 13000)');
check(mb_strlen($summary['product_id'], 'UTF-8') <= 64, 'product_id summary fits VARCHAR(64)');
check(mb_strlen($summary['product_name'], 'UTF-8') <= 255, 'product_name summary fits VARCHAR(255)');
check(mb_strlen($summary['gender'], 'UTF-8') <= 50, 'gender summary fits VARCHAR(50)');
check(mb_strlen($summary['size'], 'UTF-8') <= 20, 'size summary fits VARCHAR(20)');

$multiOrder = [
    'order_id'               => 'BF-BASKETTEST01',
    'product_id'             => $summary['product_id'],
    'product_name'           => $summary['product_name'],
    'gender'                 => $summary['gender'],
    'size'                   => $summary['size'],
    'price'                  => $summary['price'],
    'items_json'             => json_encode($sampleItems, JSON_UNESCAPED_UNICODE),
    'delivery_provider'      => 'cdek',
    'delivery_mode'          => 'sandbox',
    'delivery_cost'          => 510.60,
    'fio'                    => 'Иванов Иван Иванович',
    'email'                  => 'customer@example.com',
    'phone'                  => '+79001112233',
    'fivepost_point_id'      => 'MSK1',
    'fivepost_point_name'    => 'ПВЗ СДЭК Тест',
    'fivepost_address'       => 'г. Москва, ул. Тестовая, д. 1',
    'payment_transaction_id' => '2e9b0000-0000-5000-8000-111122223333',
];

check(orderTotalUnits($multiOrder) === 3, 'orderTotalUnits counts 3 physical units (2 + 1)');
check(count(expandOrderItemsToUnits($multiOrder)) === 3, 'expandOrderItemsToUnits expands 2+1 into 3 physical units');

// 2. YooKassa 54-FZ Prepayment Receipt (full_prepayment)
$prepayItems = buildYookassaPrepaymentItems($sampleItems, 1, 510.60, 'BF-BASKETTEST01');
check(count($prepayItems) === 3, 'prepayment receipt has 2 goods lines + 1 CDEK service line');
check($prepayItems[0]['amount']['value'] === '4900.00' && $prepayItems[0]['quantity'] === 2.0, 'first line uses unit price 4900.00 and float quantity 2.0');
check($prepayItems[1]['amount']['value'] === '3200.00' && $prepayItems[1]['quantity'] === 1.0, 'second line uses unit price 3200.00 and float quantity 1.0');
check($prepayItems[2]['amount']['value'] === '510.60' && $prepayItems[2]['payment_subject'] === 'service' && $prepayItems[2]['quantity'] === 1.0, 'third line is CDEK delivery service 510.60');

$prepaySum = 0.0;
foreach ($prepayItems as $pi) {
    $prepaySum += ((float)$pi['amount']['value']) * ((float)$pi['quantity']);
}
check(abs($prepaySum - 13510.60) < 0.001, 'prepayment receipt sum matches total payment amount 13510.60');

// 3. Chestny ZNAK 83-char scanner GS restoration + YooKassa Closing Receipt (/v3/receipts)
$scannerStripped83 = '010460123456789021ABCDEFGH12345' . '91EE06' . '92' . str_repeat('A', 44);
$restoredGs = normalizeChestnyZnakCode($scannerStripped83);
check(substr_count($restoredGs, "\x1D") === 2, '83-char DataMatrix scanner input restores both GS (0x1D) separators');

$codes = [
    $restoredGs,
    '010460123456789021ABCDEFGH67890' . "\x1D" . '91EE06' . "\x1D" . str_repeat('B', 44),
    '010460987654321021ZXCVBNMASDFGH' . "\x1D" . '91EE06' . "\x1D" . str_repeat('C', 44),
];
$closing = buildYookassaClosingReceiptPayload($multiOrder, $codes, 1, 1);
check($closing['type'] === 'payment' && $closing['send'] === true && $closing['payment_id'] === '2e9b0000-0000-5000-8000-111122223333', 'closing receipt root fields');
check(!isset($closing['internet']) && !isset($closing['timezone']), 'closing receipt preserves production KKT root schema');
check(count($closing['items']) === 4, 'closing receipt expands 3 physical units + 1 delivery service line = 4 lines');
check(
    $closing['items'][0]['quantity'] === '1.000'
    && $closing['items'][0]['payment_mode'] === 'full_payment'
    && $closing['items'][0]['payment_subject'] === 'marked'
    && $closing['items'][0]['measure'] === 'piece'
    && $closing['items'][0]['mark_code_info']['gs_1m'] === $codes[0],
    'unit #1 has marked piece attributes and exact GS1 DataMatrix'
);
check($closing['items'][1]['mark_code_info']['gs_1m'] === $codes[1], 'unit #2 has second unique DataMatrix code');
check($closing['items'][2]['amount']['value'] === '3200.00' && $closing['items'][2]['mark_code_info']['gs_1m'] === $codes[2], 'unit #3 has tshirt price and third DataMatrix code');
check($closing['items'][3]['payment_subject'] === 'service' && !isset($closing['items'][3]['mark_code_info']), 'delivery line in closing receipt is unmarked service');
check($closing['settlements'][0]['type'] === 'prepayment' && $closing['settlements'][0]['amount']['value'] === '13510.60', 'closing settlement equals goods + delivery (13510.60)');

// Reject missing or duplicate codes
$duplicateRejected = false;
try {
    buildYookassaClosingReceiptPayload($multiOrder, [$codes[0], $codes[0], $codes[2]], 1, 1);
} catch (InvalidArgumentException $e) {
    $duplicateRejected = true;
}
check($duplicateRejected, 'duplicate Chestny ZNAK code in same order is rejected');

$countMismatchRejected = false;
try {
    buildYookassaClosingReceiptPayload($multiOrder, [$codes[0], $codes[1]], 1, 1);
} catch (InvalidArgumentException $e) {
    $countMismatchRejected = true;
}
check($countMismatchRejected, 'fewer Chestny ZNAK codes than physical units is rejected');

// 4. YooKassa Multi-Item Refund Receipt (/v3/refunds) — preserves 100% HEAD root params + expands items
$refundBeforeClosing = buildYookassaRefundReceiptPayload($multiOrder, 13510.60, '2e9b0000-0000-5000-8000-111122223333', 1, 1);
check(
    $refundBeforeClosing['internet'] === true
    && $refundBeforeClosing['timezone'] === 2
    && $refundBeforeClosing['type'] === 'refund'
    && $refundBeforeClosing['send'] === true
    && $refundBeforeClosing['payment_id'] === '2e9b0000-0000-5000-8000-111122223333'
    && $refundBeforeClosing['settlements'][0]['type'] === 'prepayment'
    && $refundBeforeClosing['settlements'][0]['amount']['value'] === '13510.60',
    'refund receipt preserves 100% HEAD root parameters (internet, timezone, type, send, payment_id, settlements)'
);
check(count($refundBeforeClosing['items']) === 3, 'unmarked refund uses grouped lines + delivery');
check($refundBeforeClosing['items'][0]['payment_mode'] === 'full_payment' && $refundBeforeClosing['items'][0]['quantity'] === '2.000', 'unmarked refund preserves full_payment mode and quantity');

$closedOrder = $multiOrder + ['receipt_sent' => 1, 'mark_codes_json' => json_encode($codes, JSON_UNESCAPED_UNICODE)];
foreach ([$multiOrder, $closedOrder] as $partialOrder) {
    $partialRejected = false;
    try {
        buildYookassaRefundReceiptPayload($partialOrder, 4900.0, 'payment-test', 1, 1);
    } catch (InvalidArgumentException $e) {
        $partialRejected = true;
    }
    check($partialRejected, 'partial multi-unit refund without selected units is rejected before payment API call');
    $goodsRefund = buildYookassaRefundReceiptPayload($partialOrder, 13000.0, 'payment-test', 1, 1);
    $refundedGoods = array_reduce($goodsRefund['items'], static fn(float $sum, array $item): float => $sum + (float)$item['amount']['value'] * (float)$item['quantity'], 0.0);
    check(abs($refundedGoods - 13000.0) < 0.001, 'goods-only refund preserves all goods and excludes delivery');
}
$refundAfterClosing = buildYookassaRefundReceiptPayload($closedOrder, 13510.60, '2e9b0000-0000-5000-8000-111122223333', 1, 1);
check(count($refundAfterClosing['items']) === 4, 'marked refund expands units with stored DataMatrix codes');
check(
    $refundAfterClosing['items'][0]['payment_mode'] === 'full_payment'
    && $refundAfterClosing['items'][0]['payment_subject'] === 'marked'
    && $refundAfterClosing['items'][0]['mark_code_info']['gs_1m'] === $codes[0],
    'marked refund preserves Chestny ZNAK mark_code_info'
);

// 5. CDEK multi-item package scaling & createOrder items array
$fakeCdek = new FakeBasketCdek('sandbox');
$pkg1 = $fakeCdek->package(1);
$pkg3 = $fakeCdek->package(3);
check($pkg3['weight'] === $pkg1['weight'] * 3 && $pkg3['height'] > $pkg1['height'], 'CDEK package scales weight and height for 3 units');

$fakeCdek->responses[] = ['entity' => ['uuid' => '00000000-0000-4000-a000-000000000001']];
$fakeCdek->createOrder($multiOrder);
$cdekOrderPayload = $fakeCdek->calls[0]['data'];
check($cdekOrderPayload['packages'][0]['weight'] === $pkg3['weight'], 'CDEK createOrder package weight matches 3 units');
check(count($cdekOrderPayload['packages'][0]['items']) === 2, 'CDEK createOrder sends both distinct order items');
check(
    $cdekOrderPayload['packages'][0]['items'][0]['amount'] === 2
    && (float)$cdekOrderPayload['packages'][0]['items'][0]['cost'] === 4900.0
    && $cdekOrderPayload['packages'][0]['items'][0]['payment']['value'] === 0,
    'CDEK item #1 has amount=2, unit cost=4900 and zero COD'
);
check(
    $cdekOrderPayload['packages'][0]['items'][1]['amount'] === 1
    && (float)$cdekOrderPayload['packages'][0]['items'][1]['cost'] === 3200.0,
    'CDEK item #2 has amount=1 and unit cost=3200'
);

// 6. Customer HTML email receipt renders all items, quantities, delivery & total
$renderEmail = static function (array $order): string {
    ob_start();
    require __DIR__ . '/../includes/email_templates/receipt.php';
    return (string)ob_get_clean();
};
$emailHtml = $renderEmail($multiOrder);
check(
    str_contains($emailHtml, 'Худи BOYFORGE')
    && str_contains($emailHtml, 'Футболка BOYFORGE')
    && str_contains($emailHtml, '9 800.00 ₽')
    && str_contains($emailHtml, '3 200.00 ₽')
    && str_contains($emailHtml, '510.60 ₽')
    && str_contains($emailHtml, '13 510.60 ₽'),
    'HTML email receipt renders every item line total, CDEK delivery and grand total'
);

// 7. Legacy backward compatibility & 100% 1:1 HEAD YooKassa payload equality for single-item orders
$legacyOrder = [
    'order_id'               => 'BF-LEGACY01',
    'product_id'             => '7',
    'product_name'           => 'Классическая футболка',
    'gender'                 => 'Мужской',
    'size'                   => 'L',
    'price'                  => 3200,
    'email'                  => 'old@example.com',
    'payment_transaction_id' => 'pay_legacy_01',
    'mark_code'              => $codes[0],
];
check(orderTotalUnits($legacyOrder) === 1 && orderItemsGoodsTotal($legacyOrder) === 3200.0, 'legacy single-item order without items_json remains 100% compatible');

// Byte-for-byte parity check against HEAD (feature/sdek) for single-item YooKassa payloads:
$headSinglePrepayItems = [[
    'description'     => mb_substr("Заказ BF-LEGACY01: Классическая футболка", 0, 128),
    'quantity'        => 1.000,
    'amount'          => ['value' => '3200.00', 'currency' => 'RUB'],
    'vat_code'        => 1,
    'payment_mode'    => 'full_prepayment',
    'payment_subject' => 'commodity',
    'measure'         => 'piece',
]];
check(
    json_encode(buildYookassaPrepaymentItems(normalizeOrderItems($legacyOrder), 1, 0.0, 'BF-LEGACY01')) === json_encode($headSinglePrepayItems),
    '1:1 byte-for-byte YooKassa prepayment items parity with HEAD for single item'
);

$headSingleClosing = [
    'customer'        => ['email' => 'old@example.com'],
    'type'            => 'payment',
    'send'            => true,
    'payment_id'      => 'pay_legacy_01',
    'tax_system_code' => 3,
    'settlements'     => [['type' => 'prepayment', 'amount' => ['value' => '3200.00', 'currency' => 'RUB']]],
    'items'           => [[
        'description'     => 'Отгрузка: Классическая футболка (L)',
        'quantity'        => '1.000',
        'amount'          => ['value' => '3200.00', 'currency' => 'RUB'],
        'vat_code'        => 1,
        'payment_subject' => 'marked',
        'payment_mode'    => 'full_payment',
        'mark_code_info'  => ['gs_1m' => $codes[0]],
        'measure'         => 'piece',
    ]],
];
check(
    json_encode(buildYookassaClosingReceiptPayload($legacyOrder, [$codes[0]], 3, 1), JSON_UNESCAPED_UNICODE) === json_encode($headSingleClosing, JSON_UNESCAPED_UNICODE),
    '1:1 byte-for-byte YooKassa closing receipt parity with HEAD for single marked item'
);

$headSingleRefund = [
    'internet'        => true,
    'timezone'        => 2,
    'customer'        => ['email' => 'old@example.com'],
    'type'            => 'refund',
    'send'            => true,
    'payment_id'      => 'pay_legacy_01',
    'tax_system_code' => 3,
    'settlements'     => [['type' => 'prepayment', 'amount' => ['value' => '3200.00', 'currency' => 'RUB']]],
    'items'           => [[
        'description'     => 'Возврат: Классическая футболка',
        'quantity'        => '1.000',
        'amount'          => ['value' => '3200.00', 'currency' => 'RUB'],
        'vat_code'        => 1,
        'payment_subject' => 'marked',
        'payment_mode'    => 'full_payment',
        'measure'         => 'piece',
        'mark_code_info'  => ['gs_1m' => $codes[0]],
    ]],
];
check(
    json_encode(buildYookassaRefundReceiptPayload($legacyOrder, 3200.0, 'pay_legacy_01', 3, 1), JSON_UNESCAPED_UNICODE) === json_encode($headSingleRefund, JSON_UNESCAPED_UNICODE),
    '1:1 byte-for-byte YooKassa refund receipt parity with HEAD for single marked item'
);
