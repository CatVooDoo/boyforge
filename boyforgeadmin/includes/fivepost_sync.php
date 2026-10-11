<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/fivepost.php';

function syncFivepostOrders(PDO $pdo, bool $force = false): array {
    $cacheFile = dirname(__DIR__, 2) . '/logs/5post_sync_cache.json';
    $now = time();
    $cache = ['last_sync' => 0, 'updated_count' => 0, 'status' => 'idle'];

    if (file_exists($cacheFile)) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            $cache = array_merge($cache, $cached);
        }
    }

    $ttl = 300; // 5 минут
    if (!$force && ($now - (int)$cache['last_sync'] < $ttl)) {
        return [
            'synced' => false,
            'cached' => true,
            'last_sync' => (int)$cache['last_sync'],
            'updated_count' => (int)($cache['updated_count'] ?? 0),
            'message' => 'Данные актуальны (кэш 5 мин)'
        ];
    }

    // Выбираем заказы, отправленные в 5Post, которые еще не завершены
    $stmt = $pdo->query("
        SELECT order_id, fivepost_order_id, fivepost_status 
        FROM orders 
        WHERE (fivepost_order_id IS NOT NULL AND fivepost_order_id != '') 
          AND (fivepost_status NOT IN ('DONE', 'PICKED_UP', 'CANCELLED', 'LOST', 'UTILIZED') OR fivepost_status IS NULL)
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $activeOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($activeOrders)) {
        $cache['last_sync'] = $now;
        $cache['updated_count'] = 0;
        $cache['status'] = 'ok';
        @file_put_contents($cacheFile, json_encode($cache));
        return [
            'synced' => true,
            'cached' => false,
            'last_sync' => $now,
            'updated_count' => 0,
            'message' => 'Нет активных заказов для обновления'
        ];
    }

    $orderIds = array_column($activeOrders, 'order_id');
    try {
        $client = new FivePostClient();
        $res = $client->getOrderStatuses($orderIds);

        $updated = 0;
        if (!empty($res['success']) && !empty($res['data']) && is_array($res['data'])) {
            $updStmt = $pdo->prepare("UPDATE orders SET fivepost_status = :st WHERE order_id = :oid");
            foreach ($res['data'] as $item) {
                $soId = $item['senderOrderId'] ?? null;
                $st = $item['status'] ?? null;
                if ($soId && $st) {
                    $updStmt->execute([':st' => $st, ':oid' => $soId]);
                    $updated++;
                }
            }
        }

        $cache['last_sync'] = $now;
        $cache['updated_count'] = $updated;
        $cache['status'] = 'ok';
        @file_put_contents($cacheFile, json_encode($cache));

        return [
            'synced' => true,
            'cached' => false,
            'last_sync' => $now,
            'updated_count' => $updated,
            'message' => "Успешно обновлено статусов: {$updated}"
        ];
    } catch (Throwable $e) {
        $cache['last_sync'] = $now;
        $cache['status'] = 'error';
        $cache['error'] = $e->getMessage();
        @file_put_contents($cacheFile, json_encode($cache));
        return [
            'synced' => false,
            'cached' => false,
            'error' => $e->getMessage()
        ];
    }
}
