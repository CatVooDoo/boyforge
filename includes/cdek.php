<?php
declare(strict_types=1);

/** API v2. Public sandbox credentials are published at https://apidoc.cdek.ru/. */
class CdekClient
{
    private string $mode;
    private string $baseUrl;
    private string $clientId;
    private string $secret;
    private ?string $token = null;

    public function __construct(?string $mode = null)
    {
        $this->mode = $mode ?? (env_get('CDEK_ENV') ?: 'sandbox');
        if (!in_array($this->mode, ['sandbox', 'production'], true)) {
            throw new RuntimeException('CDEK_ENV должен быть sandbox или production');
        }
        $test = $this->mode === 'sandbox';
        $this->baseUrl = $test ? 'https://api.edu.cdek.ru/v2' : 'https://api.cdek.ru/v2';
        $this->clientId = (string)(env_get($test ? 'CDEK_TEST_CLIENT_ID' : 'CDEK_CLIENT_ID') ?: ($test ? 'wqGwiQx0gg8mLtiEKsUinjVSICCjtTEP' : ''));
        $this->secret = (string)(env_get($test ? 'CDEK_TEST_CLIENT_SECRET' : 'CDEK_CLIENT_SECRET') ?: ($test ? 'RmAmgvSgSl1yirlz9QupbzOJVqhCxcP5' : ''));
        if (!$this->clientId || !$this->secret) {
            throw new RuntimeException('Не настроены ключи СДЭК');
        }
    }

    public function mode(): string { return $this->mode; }

    protected function http(string $method, string $path, array $data = [], bool $auth = true): array
    {
        if ($auth && !$this->token) {
            $result = $this->http('POST', '/oauth/token', [
                'grant_type' => 'client_credentials', 'client_id' => $this->clientId, 'client_secret' => $this->secret,
            ], false);
            $this->token = $result['access_token'] ?? null;
            if (!$this->token) throw new RuntimeException('СДЭК: ошибка авторизации');
        }
        $url = $this->baseUrl . $path;
        if ($method === 'GET' && $data) $url .= '?' . http_build_query($data);
        $headers = ['Accept: application/json'];
        if ($auth) $headers[] = 'Authorization: Bearer ' . $this->token;
        $ch = curl_init($url);
        $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        if ($method !== 'GET') {
            $headers[] = $auth ? 'Content-Type: application/json' : 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_CUSTOMREQUEST] = $method;
            $options[CURLOPT_POSTFIELDS] = $auth ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : http_build_query($data);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            error_log('CDEK transport: ' . curl_error($ch));
            throw new RuntimeException('СДЭК временно недоступен. Попробуйте ещё раз.');
        }
        $body = json_decode($raw, true);
        if ($code < 200 || $code >= 300 || !is_array($body) || !empty($body['errors'])) {
            throw new RuntimeException('СДЭК: запрос не выполнен (HTTP ' . $code . ')');
        }
        return $body;
    }

    public function points(array $query): array
    {
        $raw = $this->http('GET', '/deliverypoints', $query + ['country_code' => 'RU', 'type' => 'PVZ', 'is_handout' => 'true']);
        $points = [];
        foreach ($raw as $p) {
            if (($p['status'] ?? 'ACTIVE') !== 'ACTIVE' || (isset($p['is_handout']) && !$p['is_handout'])) continue;
            if (empty($p['code']) || empty($p['location']['latitude']) || empty($p['location']['longitude'])) continue;
            $points[] = ['id' => $p['code'], 'name' => $p['name'] ?? ('СДЭК ' . $p['code']), 'type' => $p['type'] ?? 'PVZ',
                'full_address' => $p['location']['address_full'] ?? $p['location']['address'] ?? '',
                'city' => $p['location']['city'] ?? '', 'city_code' => $p['location']['city_code'] ?? 0,
                'lat' => $p['location']['latitude'], 'lng' => $p['location']['longitude'],
                'work_hours' => $p['work_time'] ?? '', 'additional' => $p['address_comment'] ?? ''];
        }
        return $points;
    }

    public function cityCode(string $city): ?int
    {
        $cities = $this->http('GET', '/location/cities', ['city' => $city, 'country_codes' => 'RU', 'size' => 10]);
        foreach ($cities as $c) {
            if (mb_strtolower($c['city'] ?? '') === mb_strtolower($city)) return (int)$c['code'];
        }
        return isset($cities[0]['code']) ? (int)$cities[0]['code'] : null;
    }

    public function package(): array
    {
        return ['weight' => max(1, (int)(env_get('CDEK_WEIGHT_G') ?: 350)),
            'length' => max(1, (int)(env_get('CDEK_LENGTH_CM') ?: 25)),
            'width' => max(1, (int)(env_get('CDEK_WIDTH_CM') ?: 20)),
            'height' => max(1, (int)(env_get('CDEK_HEIGHT_CM') ?: 5))];
    }

    public function quote(array $point, float $declaredValue = 0): array
    {
        $senderPoint = (string)(env_get('CDEK_SENDER_POINT') ?: ($this->mode === 'sandbox' ? 'MSK1' : ''));
        $fromCode = (int)(env_get('CDEK_FROM_CITY_CODE') ?: ($this->mode === 'sandbox' ? 44 : 0));
        if (!$senderPoint || !$fromCode) throw new RuntimeException('Настройте пункт и город отправления СДЭК');
        $body = $this->http('POST', '/calculator/tariff', [
            'type' => 1, 'tariff_code' => 136, 'currency' => 1,
            'from_location' => ['code' => $fromCode],
            'shipment_point' => $senderPoint,
            'delivery_point' => $point['id'],
            'services' => [['code' => 'INSURANCE', 'parameter' => (string)$declaredValue]],
            'to_location' => ['code' => (int)$point['city_code']], 'packages' => [$this->package()],
        ]);
        if (!isset($body['total_sum'], $body['period_min'], $body['period_max']) || (float)$body['total_sum'] <= 0) {
            throw new RuntimeException('СДЭК: тариф недоступен для выбранного пункта');
        }
        return ['cost' => round((float)$body['total_sum'], 2), 'days_min' => (int)$body['period_min'],
            'days_max' => (int)$body['period_max'], 'tariff_code' => 136];
    }

    public function createOrder(array $order): array
    {
        $senderPoint = (string)(env_get('CDEK_SENDER_POINT') ?: ($this->mode === 'sandbox' ? 'MSK1' : ''));
        if ($senderPoint === '') throw new RuntimeException('Не настроен пункт отправления CDEK_SENDER_POINT');
        return $this->http('POST', '/orders', [
            'type' => 1, 'number' => $order['order_id'], 'tariff_code' => 136,
            'shipment_point' => $senderPoint, 'delivery_point' => $order['fivepost_point_id'],
            'recipient' => ['name' => $order['fio'], 'phones' => [['number' => $order['phone']]]],
            'packages' => [$this->package() + ['number' => '1', 'items' => [[
                'name' => $order['product_name'], 'ware_key' => (string)$order['product_id'],
                'payment' => ['value' => 0], 'cost' => (float)$order['price'],
                'weight' => $this->package()['weight'], 'amount' => 1,
            ]]]],
        ]);
    }

    public function order(string $uuid): array
    {
        if (!preg_match('/^[a-f0-9-]{36}$/i', $uuid)) throw new RuntimeException('Неверный UUID СДЭК');
        return $this->http('GET', '/orders/' . $uuid);
    }

    public static function orderState(array $response): array
    {
        $entity = $response['entity'] ?? [];
        $state = !empty($entity['cdek_number']) ? ($entity['statuses'][0]['code'] ?? 'CREATED') : 'ACCEPTED';
        $errors = [];
        foreach ($response['requests'] ?? [] as $request) {
            if (($request['type'] ?? '') !== 'CREATE') continue;
            if (($request['state'] ?? '') === 'INVALID') $state = 'INVALID';
            foreach ($request['errors'] ?? [] as $error) $errors[] = $error['message'] ?? $error['code'] ?? 'Ошибка СДЭК';
        }
        return ['uuid' => $entity['uuid'] ?? null, 'number' => $entity['cdek_number'] ?? null,
            'status' => $state, 'error' => implode('; ', $errors)];
    }
}
