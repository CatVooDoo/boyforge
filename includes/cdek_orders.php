<?php
declare(strict_types=1);
require_once __DIR__ . '/cdek.php';

/** Called under the per-order database lock. Never repeat an ambiguous POST. */
function processCdekOrder(PDO $pdo, array &$order, ?CdekClient $client = null): void
{
    $id = $order['order_id'];
    try {
        $client = $client ?? new CdekClient($order['delivery_mode']);
        if (!empty($order['cdek_uuid'])) {
            $response = $client->order($order['cdek_uuid']);
        } elseif (empty($order['cdek_status'])) {
            $pdo->prepare("UPDATE orders SET cdek_status = 'PROCESSING' WHERE order_id = ?")->execute([$id]);
            $order['cdek_status'] = 'PROCESSING';
            $response = $client->createOrder($order);
        } else {
            // A timeout may mean the API accepted the order. Manual reconciliation is required.
            return;
        }
        $state = CdekClient::orderState($response);
        if (!$state['uuid']) throw new RuntimeException('СДЭК не вернул UUID. Проверьте заказ перед повторной отправкой.');
        $pdo->prepare('UPDATE orders SET cdek_uuid = ?, cdek_number = ?, cdek_status = ?, cdek_error = ? WHERE order_id = ?')
            ->execute([$state['uuid'], $state['number'], $state['status'], $state['error'], $id]);
        $order['cdek_uuid'] = $state['uuid'];
        $order['cdek_number'] = $state['number'];
        $order['cdek_status'] = $state['status'];
        $order['cdek_error'] = $state['error'];
        logOrderEvent($id, 'cdek_order', ['mode' => $order['delivery_mode'], 'state' => $state]);
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE orders SET cdek_status = 'NEEDS_REVIEW', cdek_error = ? WHERE order_id = ?")
            ->execute([$e->getMessage(), $id]);
        $order['cdek_status'] = 'NEEDS_REVIEW';
        logOrderEvent($id, 'cdek_error', ['error' => $e->getMessage()]);
    }
}
