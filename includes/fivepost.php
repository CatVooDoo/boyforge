<?php
declare(strict_types=1);

/**
 * fivepost.php — Официальный модуль интеграции 5Post API по разделу 18 (C2C Заказы)
 * с полным сквозным логированием всех HTTP-запросов и ответов.
 */

class FivePostClient {
    private string $apiKey;
    private string $env;
    private string $baseUrl;
    private string $senderEmail;
    private string $senderPhone;
    private string $logFile;

    public function __construct(?string $apiKey = null, ?string $env = null) {
        $envVars = self::loadEnv();
        $this->apiKey = $apiKey ?: ($envVars['5POST_API_KEY'] ?? '5cdc4b25-4fd6-40ac-b7f7-d4d55cbfcc6a');
        $this->env = $env ?: ($envVars['5POST_ENV'] ?? 'prod');
        
        $this->baseUrl = ($this->env === 'preprod') 
            ? 'https://api-preprod-omni.x5.ru' 
            : 'https://api-omni.x5.ru';

        $this->senderEmail = $envVars['5POST_SENDER_EMAIL'] ?? 'theboyforge@yandex.ru';
        $this->senderPhone = $envVars['5POST_SENDER_PHONE'] ?? '+79991234567';
        $this->logFile = __DIR__ . '/../logs/5post.log';
    }

    public static function loadEnv(): array {
        static $cached = null;
        if ($cached !== null) return $cached;
        $envFile = __DIR__ . '/../.env';
        $res = [];
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $res[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
                }
            }
        }
        $cached = $res;
        return $res;
    }

    /**
     * Запись подробного лога с разделителями
     */
    public function log(string $action, array $data): void {
        $timestamp = date('Y-m-d H:i:s');
        $divider = str_repeat('=', 80);
        $entry = "\n{$divider}\n[{$timestamp}] [5POST ACTION: {$action}]\n";
        foreach ($data as $key => $val) {
            if (is_array($val) || is_object($val)) {
                $entry .= "{$key}:\n" . json_encode($val, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            } else {
                $entry .= "{$key}: {$val}\n";
            }
        }
        $entry .= "{$divider}\n";
        @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Раздел 4: Получение Bearer токена POST jwtGenerate
     */
    public function getJwtToken(): ?string {
        $userSuffix = function_exists('posix_geteuid') ? ('_' . posix_geteuid()) : '';
        $cacheFile = sys_get_temp_dir() . '/5post_jwt_' . md5($this->apiKey . $this->baseUrl) . $userSuffix . '.json';
        if (file_exists($cacheFile)) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached['jwt']) && !empty($cached['expires_at'])) {
                if (time() < $cached['expires_at']) {
                    return $cached['jwt'];
                }
            }
        }

        $url = $this->baseUrl . '/jwt-generate-claims/rs256/1?apikey=' . urlencode($this->apiKey);
        $maskedKey = substr($this->apiKey, 0, 8) . '...' . substr($this->apiKey, -4);
        
        $this->log('JWT_REQUEST', [
            'Environment' => $this->env,
            'Endpoint'    => $url,
            'API_Key'     => $maskedKey,
            'Method'      => 'POST',
            'Headers'     => ['Content-Type: application/x-www-form-urlencoded', "X-Gravitee-Api-Key: {$maskedKey}"],
            'Body'        => 'subject=OpenAPI&audience=A122019!'
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'X-Gravitee-Api-Key: ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS     => 'subject=OpenAPI&audience=A122019!',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $duration = round(microtime(true) - $startTime, 3);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $this->log('JWT_RESPONSE', [
            'HTTP_Code'   => $httpCode,
            'Duration_s'  => $duration,
            'Curl_Error'  => $curlErr ?: 'none',
            'Raw_Response'=> (string)$response
        ]);

        if ($httpCode === 200 && is_string($response)) {
            $json = json_decode($response, true);
            if (!empty($json['jwt'])) {
                // Decode JWT exp claim for accurate cache TTL
                $jwtParts = explode('.', $json['jwt']);
                $jwtPayload = (count($jwtParts) >= 2) ? json_decode(base64_decode(strtr($jwtParts[1], '-_', '+/')), true) : null;
                $expiresAt = (!empty($jwtPayload['exp'])) ? (int)$jwtPayload['exp'] - 60 : time() + 3000; // 60s safety margin
                @file_put_contents($cacheFile, json_encode([
                    'jwt' => $json['jwt'],
                    'expires_at' => $expiresAt
                ]));
                @chmod($cacheFile, 0666);
                return $json['jwt'];
            }
        }

        return null;
    }

    /**
     * Диагностический тест соединения с API 5Post (проверка API-ключа)
     */
    public function testConnection(): array {
        $startTime = microtime(true);
        $url = $this->baseUrl . '/jwt-generate-claims/rs256/1?apikey=' . urlencode($this->apiKey);
        $maskedKey = substr($this->apiKey, 0, 8) . '...' . substr($this->apiKey, -4);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'X-Gravitee-Api-Key: ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS     => 'subject=OpenAPI&audience=A122019!',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        $response = curl_exec($ch);
        $duration = round(microtime(true) - $startTime, 3);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $this->log('TEST_CONNECTION', [
            'Environment' => $this->env,
            'Endpoint'    => $url,
            'API_Key'     => $maskedKey,
            'HTTP_Code'   => $httpCode,
            'Duration_s'  => $duration,
            'Curl_Error'  => $curlErr ?: 'none',
            'Response'    => (string)$response
        ]);

        return [
            'environment' => $this->env,
            'baseUrl'     => $this->baseUrl,
            'endpoint'    => $url,
            'maskedKey'   => $maskedKey,
            'http_code'   => $httpCode,
            'duration_s'  => $duration,
            'curl_error'  => $curlErr ?: null,
            'raw'         => (string)$response,
            'is_valid'    => ($httpCode === 200)
        ];
    }

    /**
     * Раздел 18.5: Получение точек выдачи — POST /api/v2/points/pickup
     */
    public function fetchPickupPoints(?string $pageToken = null, int $maxPageSize = 1000): array {
        $jwt = $this->getJwtToken();
        if (!$jwt) {
            return [
                'success' => false,
                'error'   => 'Не удалось получить JWT токен 5Post (проверьте активность API-ключа в .env)'
            ];
        }

        $url = $this->baseUrl . '/api/v2/points/pickup';
        $payload = ['maxPageSize' => $maxPageSize];
        if (!empty($pageToken)) $payload['pageToken'] = $pageToken;

        $this->log('FETCH_PICKUP_POINTS_REQUEST', [
            'Endpoint' => $url,
            'Payload'  => $payload
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $jwt,
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->log('FETCH_PICKUP_POINTS_RESPONSE', [
            'HTTP_Code'    => $httpCode,
            'Response_Len' => strlen((string)$response)
        ]);

        if ($httpCode === 200 && is_string($response)) {
            $json = json_decode($response, true);
            return [
                'success'       => true,
                'data'          => $json['data'] ?? [],
                'nextPageToken' => $json['nextPageToken'] ?? null
            ];
        }

        return [
            'success'   => false,
            'http_code' => $httpCode,
            'error'     => '5Post API Error: ' . substr((string)$response, 0, 400)
        ];
    }

    /**
     * Раздел 18.2: Создание C2C-заказа — POST /api/v1/orders/c2c
     */
    public function createC2COrder(array $order): array {
        $senderOrderId = (string)($order['order_id'] ?? ('BF-' . strtoupper(substr(md5(uniqid()), 0, 8))));
        $clientOrderId = (string)($order['client_order_id'] ?? $senderOrderId);
        $receiverLocation = (string)($order['fivepost_point_id'] ?? '');

        // Нормализация телефона (+7...)
        $receiverPhone = preg_replace('/[^\d\+]/', '', (string)($order['phone'] ?? ''));
        if (strpos($receiverPhone, '8') === 0 && strlen($receiverPhone) === 11) {
            $receiverPhone = '+7' . substr($receiverPhone, 1);
        } elseif (strpos($receiverPhone, '7') === 0 && strlen($receiverPhone) === 11) {
            $receiverPhone = '+' . $receiverPhone;
        }

        $receiverName = trim((string)($order['fio'] ?? 'Покупатель BOYFORGE'));
        $receiverEmail = !empty($order['email']) ? trim((string)$order['email']) : null;
        $productName = (string)($order['product_name'] ?? 'Товар BOYFORGE');
        $itemPrice = (float)($order['price'] ?? 3200);
        $weightGrams = (int)($order['weight_g'] ?? 350);
        $weightMg = $weightGrams * 1000;
        $heightMm = (int)($order['height_mm'] ?? 50);
        $lengthMm = (int)($order['length_mm'] ?? 300);
        $widthMm  = (int)($order['width_mm'] ?? 250);
        $cargoData = [
            'height'        => $heightMm,
            'length'        => $lengthMm,
            'width'         => $widthMm,
            'weight'        => $weightMg,
            'price'         => $itemPrice,
            'productValues' => [
                [
                    'name'       => $productName,
                    'price'      => $itemPrice,
                    'value'      => 1,
                    'vat'        => -1,
                    'vendorCode' => (string)($order['product_id'] ?? 'BF-ITEM')
                ]
            ]
        ];

        if (!empty($order['sender_cargo_id'])) {
            $cargoData['senderCargoId'] = (string)$order['sender_cargo_id'];
        }

        // Структура JSON строго по Разделу 18.2 документации
        $payload = [
            'senderOrderId'       => $senderOrderId,
            'clientOrderId'       => $clientOrderId,
            'receiverLocation'    => $receiverLocation,
            'receiverClientName'  => $receiverName,
            'receiverClientPhone' => $receiverPhone,
            'senderClientEmail'   => $this->senderEmail,
            'senderClientPhone'   => $this->senderPhone,
            'cargo'               => $cargoData,
            'cost' => [
                'paymentType'    => 'PREPAYMENT',
                'prepaymentSum'  => $itemPrice,
                'price'          => $itemPrice,
                'services'       => [
                    [
                        'name'         => 'DELIVERY_COST',
                        'paymentValue' => 0,
                        'vat'          => -1
                    ]
                ]
            ]
        ];

        if ($receiverEmail) {
            $payload['receiverClientEmail'] = $receiverEmail;
        }

        // Валидация UUID точки выдачи (receiverLocation)
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $receiverLocation)) {
            $err = 'Некорректный UUID точки выдачи 5Post: ' . $receiverLocation;
            $this->log('CREATE_C2C_ORDER_ABORTED', [
                'Reason'  => $err,
                'OrderId' => $senderOrderId
            ]);
            return [
                'success' => false,
                'error'   => $err,
                'payload' => $payload,
                'raw'     => json_encode(['error' => $err], JSON_UNESCAPED_UNICODE)
            ];
        }

        // 1. Попытка получить JWT токен
        $jwt = $this->getJwtToken();
        if (!$jwt) {
            $err = 'Не удалось авторизоваться в 5Post: API-ключ не активирован на шлюзе X5 (401 Unauthorized)';
            $this->log('CREATE_C2C_ORDER_ABORTED', [
                'Reason'  => $err,
                'OrderId' => $senderOrderId,
                'Payload' => $payload
            ]);

            return [
                'success'         => false,
                'is_auth_error'   => true,
                'http_code'       => 401,
                'error'           => $err,
                'payload'         => $payload,
                'raw'             => json_encode(['error' => $err, 'status' => 401], JSON_UNESCAPED_UNICODE)
            ];
        }

        // 2. Отправка C2C заказа в 5Post API (Раздел 18.2)
        $url = $this->baseUrl . '/api/v1/orders/c2c';
        $this->log('CREATE_C2C_ORDER_REQUEST', [
            'OrderId'  => $senderOrderId,
            'Endpoint' => $url,
            'Headers'  => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . substr($jwt, 0, 15) . '...',
                'Accept-Language: ru'
            ],
            'Payload'  => $payload
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $jwt,
                'Accept: application/json',
                'Accept-Language: ru'
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $duration = round(microtime(true) - $startTime, 3);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $this->log('CREATE_C2C_ORDER_RESPONSE', [
            'OrderId'     => $senderOrderId,
            'HTTP_Code'   => $httpCode,
            'Duration_s'  => $duration,
            'Curl_Error'  => $curlErr ?: 'none',
            'Raw_Response'=> (string)$response
        ]);

        if ($curlErr) {
            return [
                'success'   => false,
                'http_code' => 0,
                'error'     => 'Сбой cURL соединения с 5Post: ' . $curlErr,
                'payload'   => $payload,
                'raw'       => $curlErr
            ];
        }

        $json = json_decode((string)$response, true);

        // Успешный ответ по 18.2
        if ($httpCode === 200 && is_array($json) && !empty($json['created'])) {
            $fivepostOrderId = $json['orderId'] ?? null;
            $cargoData = $json['cargoes'][0] ?? [];
            $fivepostBarcode = $cargoData['barcode'] ?? null;
            $fivepostCargoId = $cargoData['cargoId'] ?? null;

            return [
                'success'         => true,
                'http_code'       => 200,
                'orderId'         => $fivepostOrderId,
                'barcode'         => $fivepostBarcode,
                'cargoId'         => $fivepostCargoId,
                'senderOrderId'   => $json['senderOrderId'] ?? $senderOrderId,
                'payload'         => $payload,
                'raw'             => (string)$response
            ];
        }

        // Ошибка валидации со стороны 5Post
        $errorMsg = "Ошибка 5Post HTTP {$httpCode}";
        if (is_array($json) && !empty($json['errors'])) {
            $errParts = [];
            foreach ($json['errors'] as $errItem) {
                $errParts[] = ($errItem['message'] ?? '') . ' (код ' . ($errItem['code'] ?? '') . ')';
            }
            $errorMsg = implode('; ', $errParts);
        } elseif (is_array($json) && !empty($json['message'])) {
            if (is_array($json['message'])) {
                $msgParts = [];
                foreach ($json['message'] as $m) {
                    $msgParts[] = ($m['name'] ?? '?') . ': ' . ($m['message'] ?? '');
                }
                $errorMsg = implode('; ', $msgParts);
            } else {
                $errorMsg = (string)$json['message'];
            }
        }

        return [
            'success'   => false,
            'http_code' => $httpCode,
            'error'     => $errorMsg,
            'payload'   => $payload,
            'raw'       => (string)$response
        ];
    }

    /**
     * Отмена заказа в 5Post: DELETE /api/v2/cancelOrder/byOrderId/{orderId}
     */
    public function cancelOrder(string $orderId): array {
        $jwt = $this->getJwtToken();
        if (!$jwt) {
            return [
                'success'   => false,
                'http_code' => 401,
                'error'     => 'Не удалось получить авторизационный токен 5Post'
            ];
        }

        $url = $this->baseUrl . '/api/v2/cancelOrder/byOrderId/' . urlencode($orderId);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $jwt,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $this->log('CANCEL_ORDER_RESPONSE', [
            'OrderId'    => $orderId,
            'HTTP_Code'  => $httpCode,
            'Curl_Error' => $curlErr ?: 'none',
            'Response'   => (string)$response
        ]);

        return [
            'success'   => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'response'  => (string)$response
        ];
    }
}
