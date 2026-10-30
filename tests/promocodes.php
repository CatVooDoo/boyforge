<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/checkout_pricing.php';

function expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    echo 'OK: ' . $message . "\n";
}
function rejects(callable $fn, string $message): void {
    try { $fn(); } catch (InvalidArgumentException $e) { expect(true, $message); return; }
    throw new RuntimeException('FAIL: ' . $message);
}
class PromoTestPDO extends PDO {
    public array $products = [];
    public array $promos = [];
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new PromoTestStatement($this, $query); }
}
class PromoTestStatement extends PDOStatement {
    private mixed $result = false;
    public function __construct(private PromoTestPDO $db, private string $sql) {}
    public function execute(?array $params = null): bool {
        $id = (string)($params[0] ?? '');
        $this->result = false;
        if (str_contains($this->sql, 'FROM products')) {
            foreach ($this->db->products as $product) {
                $match = str_contains($this->sql, 'cat_id =') ? $product['cat_id'] === $id : (string)$product['id'] === $id;
                if ($match && $product['is_active']) { $this->result = $product; break; }
            }
        } else {
            $promo = $this->db->promos[$id] ?? null;
            if ($promo && $promo['is_active'] && $promo['category_active']) $this->result = $promo;
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->result; }
}

expect(validatePromoCode('  лето-10%  ') === 'ЛЕТО-10%', 'custom words and symbols normalized without case sensitivity');
expect(validatePromoCode('★') === '★', 'single symbol accepted');
foreach (['', 'HELLO WORLD', "A\nB", str_repeat('Я', 33)] as $code) rejects(fn() => validatePromoCode($code), 'invalid code rejected');
$generated = [];
for ($i = 0; $i < 100; $i++) $generated[] = generatePromoCode();
expect(count(array_unique($generated)) === 100 && preg_match('/^[A-Z2-9]{10}$/', $generated[0]) === 1, 'random generator produces unique readable codes');

$db = new PromoTestPDO();
$db->products = [
    ['id' => 1, 'name' => 'Худи', 'cat_id' => 'hoodie', 'cat' => 'Худи', 'price' => '4 900 ₽', 'sizes' => '[]', 'is_active' => 1],
    ['id' => 2, 'name' => 'Футболка', 'cat_id' => 'tshirt', 'cat' => 'Футболка', 'price' => '3 200 ₽', 'sizes' => '[]', 'is_active' => 1],
];
$db->promos['ХУДИ10'] = ['code' => 'ХУДИ10', 'discount_percent' => 10, 'category_slug' => 'hoodie', 'category_name' => 'Худи', 'is_active' => 1, 'category_active' => 1];
$cart = ['promoCode' => 'худи10', 'price' => '1', 'discountAmount' => 999999, 'items' => [
    ['productId' => '1', 'gender' => 'Мужской', 'size' => 'L', 'qty' => 2, 'price' => 1, 'discount_percent' => 99],
    ['productId' => '2', 'gender' => 'Женский', 'size' => 'S', 'qty' => 1, 'price' => 1],
]];
$pricing = priceCheckout($db, $cart);
expect($pricing['subtotal'] === 13000.0 && $pricing['total'] === 12020.0 && $pricing['discount'] === 980.0, 'server DB prices override forged client prices and discount');
expect((float)$pricing['items'][0]['price'] === 4410.0 && (float)$pricing['items'][1]['price'] === 3200.0, 'only selected category receives discount, for every physical unit');
$single = priceCheckout($db, ['productId' => '1', 'gender' => 'Мужской', 'size' => 'L', 'promoCode' => 'ХУДИ10']);
expect($single['total'] === 4410.0, 'buy-now single-product checkout uses same pricing');
$without = priceCheckout($db, array_replace($cart, ['promoCode' => '']));
expect($without['total'] === 13000.0 && $without['discount'] === 0.0, 'no code preserves original checkout price');
rejects(fn() => priceCheckout($db, array_replace($cart, ['promoCode' => 'MISSING'])), 'unknown code rejected');
$db->promos['ХУДИ10']['is_active'] = 0;
rejects(fn() => priceCheckout($db, $cart), 'disabled code rechecked on checkout');
$db->promos['ХУДИ10']['is_active'] = 1;
$db->promos['ХУДИ10']['category_active'] = 0;
rejects(fn() => priceCheckout($db, $cart), 'inactive category rejected');
$db->promos['ХУДИ10']['category_active'] = 1;
rejects(fn() => priceCheckout($db, ['items' => [$cart['items'][1]], 'promoCode' => 'ХУДИ10']), 'code for unrelated category rejected');
rejects(fn() => priceCheckout($db, ['productId' => '1', 'gender' => '', 'size' => '', 'promoCode' => 'ХУДИ10']), 'promo preview cannot bypass size validation');
$db->products[0]['is_active'] = 0;
rejects(fn() => priceCheckout($db, $cart), 'unpublished product rejected');
$db->products[0]['is_active'] = 1;

// Prices are snapshotted; later changes to promo rules cannot change paid receipts.
$order = ['order_id' => 'BF-PROMOTEST', 'items_json' => json_encode($pricing['items']), 'price' => $pricing['total'],
    'delivery_cost' => 550, 'payment_transaction_id' => 'test-payment', 'email' => 'buyer@example.invalid'];
$stored = normalizeOrderItems($order);
expect((float)$stored[0]['original_price'] === 4900.0 && $stored[0]['discount_percent'] === 10, 'order stores original and discounted unit prices');
$sum = static fn(array $rows): float => round(array_reduce($rows, static fn(float $sum, array $row): float => $sum + (float)$row['amount']['value'] * (float)$row['quantity'], 0.0), 2);
$prepay = buildYookassaPrepaymentItems($stored, 1, 550);
expect($sum($prepay) === 12570.0 && $prepay[2]['amount']['value'] === '550.00', 'payment receipt discounts goods, never delivery');
$closing = buildYookassaClosingReceiptPayload($order, ['mark-1', 'mark-2', 'mark-3'], 1, 1);
expect($sum($closing['items']) === 12570.0 && $closing['items'][0]['amount']['value'] === '4410.00' && $closing['items'][3]['amount']['value'] === '550.00', 'closing receipt preserves individual marks, discounted units and full delivery');
$order['mark_codes_json'] = json_encode(['mark-1', 'mark-2', 'mark-3']);
$refund = buildYookassaRefundReceiptPayload($order, 12570.0, 'test-payment', 1, 1);
expect($sum($refund['items']) === 12570.0 && count($refund['items']) === 4, 'full marked refund uses actual paid amounts');
$goodsRefund = buildYookassaRefundReceiptPayload($order, 12020.0, 'test-payment', 1, 1);
expect($sum($goodsRefund['items']) === 12020.0 && count($goodsRefund['items']) === 3, 'goods-only refund excludes full delivery charge');

$db->products[0]['price'] = '99,99 ₽';
$db->promos['ХУДИ10']['discount_percent'] = 33;
$rounded = priceCheckout($db, ['items' => [$cart['items'][0]], 'promoCode' => 'ХУДИ10']);
expect(abs((float)$rounded['items'][0]['price'] - 66.99) < 0.0001 && $rounded['total'] === 133.98, 'rounding per unit keeps payment and marked receipt totals identical');
ob_start(); require __DIR__ . '/../includes/email_templates/receipt.php'; $html = ob_get_clean();
expect(str_contains($html, '12 570.00 ₽'), 'customer email uses the discounted total plus unchanged delivery');
