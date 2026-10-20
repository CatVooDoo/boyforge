<?php
declare(strict_types=1);
// Standalone contract tests; no database, .env, payment or notifications.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/cdek.php';
require_once __DIR__ . '/../includes/cdek_orders.php';
function logOrderEvent(string $id, string $event, array $data = []): void {}
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "OK: $message\n";
}
class FakeCdek extends CdekClient {
    public array $calls = [];
    public array $responses = [];
    protected function http(string $method, string $path, array $data = [], bool $auth = true): array {
        $this->calls[] = compact('method', 'path', 'data');
        $response = array_shift($this->responses) ?? [];
        if ($response instanceof Throwable) throw $response;
        return $response;
    }
}
$client = new FakeCdek('sandbox');
$client->responses[] = [['code' => 'MSK1', 'name' => 'Test', 'location' => [
    'city_code' => 44, 'city' => 'Москва', 'address_full' => 'Москва, тестовый адрес', 'latitude' => 55.75, 'longitude' => 37.61]]];
$point = $client->points(['code' => 'MSK1'])[0];
check($point['id'] === 'MSK1' && $point['city_code'] === 44, 'office normalization');
$client->responses[] = ['delivery_sum' => 425.5, 'total_sum' => 510.6, 'period_min' => 2, 'period_max' => 4];
check($client->quote($point, 3200)['cost'] === 510.6, 'tariff includes VAT and services');
$order = ['order_id' => 'BF-TEST-' . bin2hex(random_bytes(5)), 'fivepost_point_id' => 'MSK1',
    'fio' => 'Тестовый Получатель', 'phone' => '+79000000000', 'product_name' => 'Тестовый товар', 'product_id' => 'BF-TEST', 'price' => 3200];
$client->responses[] = ['entity' => ['uuid' => '00000000-0000-4000-a000-000000000000'], 'requests' => [['type' => 'CREATE', 'state' => 'ACCEPTED']]];
$state = CdekClient::orderState($client->createOrder($order));
check($state['status'] === 'ACCEPTED' && !$state['number'], 'asynchronous acceptance is not shipment creation');
$payload = $client->calls[2]['data'];
check($payload['packages'][0]['items'][0]['payment']['value'] === 0, 'no cash on delivery after online payment');
check($payload['delivery_point'] === 'MSK1' && $payload['tariff_code'] === 136, 'office to office tariff and destination');
check(CdekClient::orderState(['requests' => [['type' => 'CREATE', 'state' => 'INVALID', 'errors' => [['message' => 'Invalid office']]]]])['status'] === 'INVALID', 'asynchronous validation failure');
check(CdekClient::orderState(['entity' => ['cdek_number' => '123']])['status'] === 'CREATED', 'real tracking number confirms creation');
$invalidModeRejected = false;
try { new CdekClient('wrong'); } catch (RuntimeException $e) { $invalidModeRejected = true; }
check($invalidModeRejected, 'invalid mode rejected');

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE orders (order_id TEXT PRIMARY KEY, cdek_uuid TEXT, cdek_number TEXT, cdek_status TEXT, cdek_error TEXT)');
$saved = $order + ['delivery_mode' => 'sandbox', 'cdek_status' => null];
$db->prepare('INSERT INTO orders (order_id) VALUES (?)')->execute([$order['order_id']]);
$persistClient = new FakeCdek('sandbox');
$persistClient->responses[] = ['entity' => ['uuid' => '00000000-0000-4000-a000-000000000000']];
processCdekOrder($db, $saved, $persistClient);
check($db->query('SELECT cdek_status FROM orders')->fetchColumn() === 'ACCEPTED', 'accepted UUID persisted before a later status refresh');
$persistClient->responses[] = ['entity' => ['uuid' => $saved['cdek_uuid'], 'cdek_number' => '123456789']];
processCdekOrder($db, $saved, $persistClient);
check($persistClient->calls[1]['method'] === 'GET' && $saved['cdek_status'] === 'CREATED', 'repeat callback refreshes UUID without a second POST');
$saved = $order + ['delivery_mode' => 'sandbox', 'cdek_status' => null];
$timeoutClient = new FakeCdek('sandbox');
$timeoutClient->responses[] = new RuntimeException('Simulated timeout after request submission');
processCdekOrder($db, $saved, $timeoutClient);
processCdekOrder($db, $saved, $timeoutClient);
check(count($timeoutClient->calls) === 1 && $saved['cdek_status'] === 'NEEDS_REVIEW', 'uncertain submission is not sent twice');
$renderReceipt = static function (array $order): string {
    ob_start();
    require __DIR__ . '/../includes/email_templates/receipt.php';
    return (string)ob_get_clean();
};
$html = $renderReceipt($order + ['delivery_provider' => 'cdek', 'delivery_mode' => 'sandbox', 'delivery_cost' => 400]);
check(str_contains($html, '3 600.00 ₽') && str_contains($html, '400.00 ₽') && str_contains($html, 'Доставка (СДЭК)'), 'customer receipt includes product plus delivery and the correct carrier');
if (in_array('--live', $argv, true)) {
    // Explicitly pin the host and credentials to the public sandbox.
    $GLOBALS['__ENV'] = [];
    $live = new CdekClient('sandbox');
    $directory = $live->points([]);
    check(count(array_filter($directory, static fn($p) => $p['city'] === 'Пенза')) > 0, 'country office directory includes Penza');
    $points = $live->points(['city_code' => 44]);
    check(count($points) > 0, 'sandbox OAuth and offices');
    $point = $points[0];
    $quote = $live->quote($point, 3200);
    check($quote['cost'] > 0, 'sandbox tariff calculation');
    echo json_encode(['office' => $point['id'], 'quote' => $quote], JSON_UNESCAPED_UNICODE) . "\n";
    $order['fivepost_point_id'] = $point['id'];
    $response = $live->createOrder($order);
    $state = CdekClient::orderState($response);
    check(!empty($state['uuid']), 'sandbox order submission');
    echo json_encode($state, JSON_UNESCAPED_UNICODE) . "\n";
    for ($attempt = 0; $attempt < 5 && $state['status'] === 'ACCEPTED'; $attempt++) {
        sleep(2);
        $state = CdekClient::orderState($live->order($state['uuid']));
    }
    echo json_encode($state, JSON_UNESCAPED_UNICODE) . "\n";
    check(!empty($state['number']) && $state['status'] !== 'INVALID', 'sandbox asynchronous order registration');
}
