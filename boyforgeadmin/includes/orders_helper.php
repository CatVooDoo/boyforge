<?php
declare(strict_types=1);

/**
 * Определение стадии заказа для CRM и Канбана
 */
function getOrderStage(array $order): string {
    $markCode = $order['mark_code'] ?? '';
    $paymentStatus = $order['payment_status'] ?? 'unpaid';
    $fpStatus = $order['fivepost_status'] ?? '';

    // 1. Отменен или возврат
    if ($markCode === '__CANCELLED__' || $paymentStatus === 'refunded' || in_array($fpStatus, ['CANCELLED', 'REJECTED', 'RETURNED_TO_PARTNER', 'LOST', 'UTILIZED'], true)) {
        return 'cancelled';
    }
    // 2. Выполнен / Получен
    if (in_array($fpStatus, ['DONE', 'PICKED_UP'], true)) {
        return 'done';
    }
    // 3. В пункте выдачи (постамат или касса)
    if (in_array($fpStatus, ['PLACED_IN_POSTAMAT', 'READY_FOR_WITHDRAW_FROM_PICKUP_POINT', 'WAITING_FOR_REPICKUP'], true)) {
        return 'ready_pickup';
    }
    // 4. В пути 5Post
    if (in_array($fpStatus, [
        'SENT', 'CREATED', 'APPROVED', 'IN_PROCESS', 'RECEIVED_IN_WAREHOUSE_BY_PLACES', 
        'PRESORTED', 'RECEIVED_IN_WAREHOUSE_IN_DETAILS', 'SORTED_IN_WAREHOUSE', 
        'COMPLECTED_IN_WAREHOUSE', 'READY_TO_BE_SHIPPED_FROM_WAREHOUSE'
    ], true)) {
        return 'in_transit';
    }
    // 5. Оплачен или ручной, ожидает передачи в 5Post
    if ($paymentStatus === 'paid' || $paymentStatus === 'manual') {
        return 'to_ship';
    }
    // 6. Ожидает оплаты
    return 'pending';
}

/**
 * Метаданные бейджа статуса 5Post
 */
function getFivepostBadgeData(?string $status): array {
    $map = [
        'CREATED' => ['label' => '5Post: Создан', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.1)'],
        'APPROVED' => ['label' => '5Post: Подтвержден', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.1)'],
        'SENT' => ['label' => '5Post: В пути', 'color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.1)'],
        'IN_PROCESS' => ['label' => '5Post: В пути', 'color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.1)'],
        'SORTED_IN_WAREHOUSE' => ['label' => '5Post: Сортировка', 'color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.1)'],
        'COMPLECTED_IN_WAREHOUSE' => ['label' => '5Post: Скомплектован', 'color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.1)'],
        'READY_TO_BE_SHIPPED_FROM_WAREHOUSE' => ['label' => '5Post: Отгрузка', 'color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.1)'],
        'PLACED_IN_POSTAMAT' => ['label' => '5Post: В постамате', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.1)'],
        'READY_FOR_WITHDRAW_FROM_PICKUP_POINT' => ['label' => '5Post: Готов в ПВЗ', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.1)'],
        'PICKED_UP' => ['label' => '5Post: Получен', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.1)'],
        'DONE' => ['label' => '5Post: Выдан', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.1)'],
        'CANCELLED' => ['label' => '5Post: Отменен', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.1)'],
        'REJECTED' => ['label' => '5Post: Отклонен', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.1)'],
        'RETURNED_TO_PARTNER' => ['label' => '5Post: Возврат', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.1)'],
        'ERROR' => ['label' => '5Post: Ошибка', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.1)'],
        'PENDING_REGISTRATION' => ['label' => '5Post: Оформление', 'color' => '#d97706', 'bg' => 'rgba(245, 158, 11, 0.1)'],
        'PENDING' => ['label' => '5Post: Ожидает', 'color' => '#6b7280', 'bg' => 'rgba(107, 114, 128, 0.1)']
    ];

    if ($status && isset($map[$status])) {
        return $map[$status];
    }
    return ['label' => $status ? "5Post: {$status}" : 'Без доставки', 'color' => '#6b7280', 'bg' => 'rgba(107, 114, 128, 0.08)'];
}

/**
 * HTML бейдж оплаты
 */
function getPaymentBadge($status, $markCode): string {
    if ($markCode === '__CANCELLED__') return '<span class="status-badge badge-cancelled">Отменён</span>';
    if ($status === 'paid') return '<span class="status-badge badge-paid">Оплачен</span>';
    if ($status === 'manual') return '<span class="status-badge badge-manual">Ручной</span>';
    if ($status === 'refunded') return '<span class="status-badge badge-cancelled">Возврат</span>';
    return '<span class="status-badge badge-pending">Ожидает оплаты</span>';
}

/**
 * HTML бейдж доставки
 */
function getDeliveryBadge($status): string {
    $info = getFivepostBadgeData($status);
    return '<span class="status-badge" style="color: ' . $info['color'] . '; background-color: ' . $info['bg'] . '; border: 1px solid ' . $info['color'] . '33;">' . htmlspecialchars($info['label']) . '</span>';
}

/**
 * Расчет KPI метрик и группировка по стадиям канбана
 */
function calculateOrdersKpi(array $orders): array {
    $totalOrders = count($orders);
    $totalRevenue = 0.0;
    $toShipCount = 0;
    $inTransitCount = 0;
    $readyPickupCount = 0;
    $doneCount = 0;
    $pendingCount = 0;
    $cancelledCount = 0;
    $paidCount = 0;
    $manualCount = 0;

    $stagesData = [
        'pending' => ['title' => 'Ожидают оплаты', 'orders' => [], 'sum' => 0.0, 'color' => '#d97706'],
        'to_ship' => ['title' => 'К отправке / Сборка', 'orders' => [], 'sum' => 0.0, 'color' => '#2563eb'],
        'in_transit' => ['title' => 'В пути (5Post)', 'orders' => [], 'sum' => 0.0, 'color' => '#7c3aed'],
        'ready_pickup' => ['title' => 'В пункте выдачи', 'orders' => [], 'sum' => 0.0, 'color' => '#059669'],
        'done' => ['title' => 'Выполнен', 'orders' => [], 'sum' => 0.0, 'color' => '#10b981'],
        'cancelled' => ['title' => 'Отменен / Возврат', 'orders' => [], 'sum' => 0.0, 'color' => '#ef4444'],
    ];

    foreach ($orders as $o) {
        $stage = getOrderStage($o);
        $price = (float)$o['price'];
        $isCancelled = ($o['mark_code'] === '__CANCELLED__' || $o['payment_status'] === 'refunded');

        if ($o['payment_status'] === 'paid' && !$isCancelled) {
            $paidCount++;
            $totalRevenue += $price;
        } elseif ($o['payment_status'] === 'manual' && !$isCancelled) {
            $manualCount++;
            $totalRevenue += $price;
        }

        if ($stage === 'to_ship') $toShipCount++;
        elseif ($stage === 'in_transit') $inTransitCount++;
        elseif ($stage === 'ready_pickup') $readyPickupCount++;
        elseif ($stage === 'done') $doneCount++;
        elseif ($stage === 'cancelled') $cancelledCount++;
        else $pendingCount++;

        $stagesData[$stage]['orders'][] = $o;
        $stagesData[$stage]['sum'] += $price;
    }

    return [
        'totalOrders' => $totalOrders,
        'totalRevenue' => $totalRevenue,
        'toShipCount' => $toShipCount,
        'inTransitCount' => $inTransitCount,
        'readyPickupCount' => $readyPickupCount,
        'doneCount' => $doneCount,
        'pendingCount' => $pendingCount,
        'cancelledCount' => $cancelledCount,
        'paidCount' => $paidCount,
        'manualCount' => $manualCount,
        'stagesData' => $stagesData
    ];
}
