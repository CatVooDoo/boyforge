<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/fivepost_sync.php';

requireAdminAuth();

$pageTitle = 'Управление заказами (CRM)';
$csrfToken = getCsrfToken();

// Автоматическая фоновая синхронизация с 5Post (кэш 5 минут)
$syncResult = syncFivepostOrders($pdo, false);

$stmt = $pdo->query("
    SELECT * FROM orders 
    ORDER BY created_at DESC 
    LIMIT 500
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем товары для модалки ручного создания
$stmtProducts = $pdo->query("SELECT id, name, sizes, price_numeric FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
$allProductsRaw = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
$productsData = [];
foreach ($allProductsRaw as $p) {
    $p['sizes'] = json_decode($p['sizes'], true) ?? [];
    $productsData[] = $p;
}

$settingsFile = dirname(__DIR__) . '/includes/settings.json';
$tgEnabled = true;
$tgToken = '';
$tgChatId = '';
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    if (isset($settings['tg_notifications_enabled'])) {
        $tgEnabled = (bool)$settings['tg_notifications_enabled'];
    }
    $tgToken = $settings['tg_bot_token'] ?? '';
    $tgChatId = $settings['tg_chat_id'] ?? '';
}

// Хелперы для статусов и стадий
function getOrderStage(array $order): string {
    $markCode = $order['mark_code'] ?? '';
    $paymentStatus = $order['payment_status'] ?? 'unpaid';
    $fpStatus = $order['fivepost_status'] ?? '';
    $fpId = $order['fivepost_order_id'] ?? '';

    if ($markCode === '__CANCELLED__' || $paymentStatus === 'refunded' || in_array($fpStatus, ['CANCELLED', 'REJECTED', 'RETURNED_TO_PARTNER', 'LOST', 'UTILIZED'])) {
        return 'cancelled';
    }
    if (in_array($fpStatus, ['DONE', 'PICKED_UP'])) {
        return 'done';
    }
    if (in_array($fpStatus, ['PLACED_IN_POSTAMAT', 'READY_FOR_WITHDRAW_FROM_PICKUP_POINT', 'WAITING_FOR_REPICKUP'])) {
        return 'ready_pickup';
    }
    if (in_array($fpStatus, ['SENT', 'CREATED', 'APPROVED', 'IN_PROCESS', 'RECEIVED_IN_WAREHOUSE_BY_PLACES', 'PRESORTED', 'RECEIVED_IN_WAREHOUSE_IN_DETAILS', 'SORTED_IN_WAREHOUSE', 'COMPLECTED_IN_WAREHOUSE', 'READY_TO_BE_SHIPPED_FROM_WAREHOUSE'])) {
        return 'in_transit';
    }
    if ($paymentStatus === 'paid' || $paymentStatus === 'manual') {
        return 'to_ship';
    }
    return 'pending';
}

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

function getPaymentBadge($status, $markCode) {
    if ($markCode === '__CANCELLED__') return '<span class="status-badge badge-cancelled">Отменён</span>';
    if ($status === 'paid') return '<span class="status-badge badge-paid">Оплачен</span>';
    if ($status === 'manual') return '<span class="status-badge badge-manual">Ручной</span>';
    if ($status === 'refunded') return '<span class="status-badge badge-cancelled">Возврат</span>';
    return '<span class="status-badge badge-pending">Ожидает оплаты</span>';
}

function getDeliveryBadge($status) {
    $info = getFivepostBadgeData($status);
    return '<span class="status-badge" style="color: ' . $info['color'] . '; background-color: ' . $info['bg'] . '; border: 1px solid ' . $info['color'] . '33;">' . htmlspecialchars($info['label']) . '</span>';
}

// Подсчет метрик
$totalOrders = count($orders);
$totalRevenue = 0;
$toShipCount = 0;
$inTransitCount = 0;
$readyPickupCount = 0;
$doneCount = 0;
$pendingCount = 0;
$cancelledCount = 0;

$stagesData = [
    'pending' => ['title' => 'Ожидают оплаты', 'orders' => [], 'sum' => 0, 'color' => '#d97706'],
    'to_ship' => ['title' => 'К отправке / Сборка', 'orders' => [], 'sum' => 0, 'color' => '#2563eb'],
    'in_transit' => ['title' => 'В пути (5Post)', 'orders' => [], 'sum' => 0, 'color' => '#7c3aed'],
    'ready_pickup' => ['title' => 'В пункте выдачи', 'orders' => [], 'sum' => 0, 'color' => '#059669'],
    'done' => ['title' => 'Выполнен', 'orders' => [], 'sum' => 0, 'color' => '#10b981'],
    'cancelled' => ['title' => 'Отменен / Возврат', 'orders' => [], 'sum' => 0, 'color' => '#ef4444'],
];

foreach ($orders as $o) {
    $stage = getOrderStage($o);
    $price = (float)$o['price'];
    
    if ($o['payment_status'] === 'paid' || $o['payment_status'] === 'manual') {
        if ($o['mark_code'] !== '__CANCELLED__' && $o['payment_status'] !== 'refunded') {
            $totalRevenue += $price;
        }
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

$cacheInfoFile = dirname(__DIR__) . '/logs/5post_sync_cache.json';
$lastSyncTs = 0;
if (file_exists($cacheInfoFile)) {
    $cData = json_decode((string)@file_get_contents($cacheInfoFile), true);
    $lastSyncTs = (int)($cData['last_sync'] ?? 0);
}
$syncDiffMinutes = $lastSyncTs > 0 ? max(0, (int)round((time() - $lastSyncTs) / 60)) : null;

require __DIR__ . '/includes/header.php';
?>

<style>
/* Boyforge CRM Orders Theme Extension */
.crm-container {
    max-width: 1600px;
    margin: 0 auto;
    padding-bottom: 40px;
}

/* KPI Summary Bar */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
@media (max-width: 1024px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .kpi-grid { grid-template-columns: 1fr; }
}

.kpi-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 18px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform var(--transition), box-shadow var(--transition);
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}
.kpi-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.kpi-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-muted);
}
.kpi-icon-wrap {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-card-muted);
    color: var(--text-main);
}
.kpi-val {
    font-family: var(--font-family);
    font-size: 24px;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1.1;
    margin-bottom: 4px;
    letter-spacing: -0.5px;
}
.kpi-meta {
    font-size: 12px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Control & Filter Bar */
.crm-control-bar {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 16px 20px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.control-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.control-left-group {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-grow: 1;
    max-width: 700px;
}

/* View Switcher */
.view-switch-tabs {
    display: inline-flex;
    background: var(--bg-card-muted);
    border: 1px solid var(--border-color);
    padding: 3px;
    border-radius: 10px;
}
.view-tab-btn {
    padding: 7px 14px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-radius: 8px;
    color: var(--text-muted);
    background: transparent;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
    cursor: pointer;
}
.view-tab-btn:hover {
    color: #000000;
}
.view-tab-btn.active {
    background: #000000;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}

/* Search Input */
.crm-search-box {
    position: relative;
    flex-grow: 1;
    min-width: 240px;
}
.crm-search-input {
    width: 100%;
    height: 40px;
    padding-left: 38px;
    padding-right: 32px;
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 13px;
    color: var(--text-main);
    outline: none;
    transition: border-color var(--transition), box-shadow var(--transition);
}
.crm-search-input:focus {
    border-color: #000000;
    box-shadow: 0 0 0 1px #000000;
}
.crm-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
}
.crm-search-clear {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-dim);
    font-size: 16px;
    cursor: pointer;
    display: none;
}
.crm-search-clear:hover { color: #000; }

/* Filter Chips */
.filter-chips-row {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 2px;
}
.filter-chip {
    padding: 5px 12px;
    background: var(--bg-main);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-pill);
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.filter-chip:hover {
    background: #ffffff;
    color: #000000;
    border-color: #000000;
}
.filter-chip.active {
    background: #000000;
    color: #ffffff;
    border-color: #000000;
}
.filter-chip .chip-cnt {
    font-size: 11px;
    padding: 1px 6px;
    border-radius: 999px;
    background: rgba(0,0,0,0.06);
    color: inherit;
}
.filter-chip.active .chip-cnt {
    background: rgba(255,255,255,0.25);
    color: #ffffff;
}

/* Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: var(--radius-pill);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.badge-paid { background-color: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.25); }
.badge-pending { background-color: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.25); }
.badge-cancelled { background-color: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.25); }
.badge-manual { background-color: rgba(59, 130, 246, 0.1); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.25); }

/* KANBAN BOARD */
.kanban-board-wrap {
    width: 100%;
    overflow-x: auto;
    padding-bottom: 20px;
}
.kanban-board {
    display: flex;
    gap: 16px;
    min-width: 1320px;
    align-items: flex-start;
}
.kanban-col {
    flex: 1 1 0;
    min-width: 250px;
    background: #f1f1ed;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 14px 12px;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 280px);
}
.kanban-col-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    padding: 0 4px;
}
.col-title-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.col-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
}
.col-title {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-main);
}
.col-badge {
    background: #ffffff;
    border: 1px solid var(--border-color);
    color: var(--text-main);
    font-size: 11px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 999px;
}
.col-sum {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
}
.kanban-cards-container {
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 2px 2px 8px 2px;
    flex-grow: 1;
}

/* KANBAN CARD */
.k-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    cursor: pointer;
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s, border-color 0.2s;
    display: flex;
    flex-direction: column;
    gap: 10px;
    position: relative;
}
.k-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
    border-color: #000000;
}
.k-card.cancelled {
    opacity: 0.65;
    background: #fbfbfb;
}
.k-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.k-card-id {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: #000000;
}
.k-card-time {
    font-size: 11px;
    color: var(--text-muted);
    white-space: nowrap;
}
.k-card-cust {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.k-card-name {
    font-size: 13px;
    font-weight: 600;
    color: #111827;
}
.k-card-phone {
    font-size: 12px;
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.k-card-prod {
    background: var(--bg-main);
    border-radius: 6px;
    padding: 8px 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}
.k-card-prod-title {
    font-size: 12px;
    font-weight: 600;
    color: #111;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.k-card-prod-size {
    font-size: 10px;
    font-weight: 700;
    background: #ffffff;
    border: 1px solid var(--border-color);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--text-main);
    white-space: nowrap;
}
.k-card-delivery {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 11px;
}
.k-card-delivery-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
}
.k-barcode-chip {
    font-family: monospace;
    font-size: 11px;
    color: #059669;
    background: rgba(16, 185, 129, 0.08);
    border: 1px dashed rgba(16, 185, 129, 0.4);
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.k-card-address {
    color: var(--text-muted);
    font-size: 11px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.k-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    border-top: 1px solid var(--border-color);
    padding-top: 8px;
    margin-top: 2px;
}
.k-card-price {
    font-family: var(--font-family);
    font-size: 14px;
    font-weight: 800;
    color: #000000;
}

/* TABLE VIEW STYLES */
.orders-table-container {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.order-row { cursor: pointer; transition: background-color 0.15s; }
.order-row:hover td { background-color: #fafaf8; }
.order-row.cancelled { opacity: 0.6; }

/* Quick copy toast */
#copyToast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #000000;
    color: #ffffff;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    z-index: 99999;
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    display: none;
    animation: fadeInToast 0.2s ease;
}
@keyframes fadeInToast {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Modals */
.modal-overlay { display:none; opacity: 1 !important; pointer-events: auto !important; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; padding: 20px; }
.modal-content { background:#fff; padding:32px; border-radius:12px; width:100%; max-width: 660px; max-height: 90vh; overflow-y: auto; box-shadow:0 10px 25px rgba(0,0,0,0.1); position: relative; }
.modal-close { position:absolute; top:24px; right:24px; cursor:pointer; color:#999; transition:0.2s; }
.modal-close:hover { color:#000; }
.modal-section { margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color); }
.modal-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.modal-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 12px; font-weight: 700; }
.info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.info-item .lbl { font-size: 11px; color: var(--text-muted); margin-bottom: 4px; }
.info-item .val { font-size: 14px; color: #000; font-weight: 600; word-break: break-all; }

/* Lifecycle Progress Bar in Modal */
.order-lifecycle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    margin: 16px 0 24px 0;
    padding: 0 10px;
}
.order-lifecycle::before {
    content: '';
    position: absolute;
    top: 14px;
    left: 20px;
    right: 20px;
    height: 3px;
    background: #e5e5e0;
    z-index: 1;
}
.lifecycle-step {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.step-circle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #ffffff;
    border: 2px solid #d1d5db;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #6b7280;
    transition: all 0.2s;
}
.lifecycle-step.active .step-circle {
    background: #000000;
    border-color: #000000;
    color: #ffffff;
}
.lifecycle-step.done .step-circle {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
}
.step-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    text-align: center;
}
.lifecycle-step.active .step-label {
    color: #000000;
    font-weight: 700;
}

/* 5Post Map Styles (Preserved) */
.fivepost-selected-card{ display:flex; align-items:center; gap:12px; padding:12px 14px; background:#f0fdf4; border:1.5px solid #86efac; border-radius:10px; }
.fivepost-selected-details{ flex-grow:1; display:flex; flex-direction:column; gap:2px; min-width:0; }
.fivepost-selected-name{ font-size:13px; font-weight:700; color:#111827; }
.fivepost-selected-address{ font-size:12px; font-weight:600; color:#1f2937; }
.fivepost-selected-extra{ font-size:11px; color:#6b7280; }
.fivepost-change-btn{ background:#ffffff; border:1px solid #d1d5db; border-radius:6px; padding:6px 12px; font-size:11px; font-weight:700; color:#374151; cursor:pointer; }
.fivepost-map-wrapper{ position:relative; width:100%; border-radius:14px; overflow:hidden; border:1px solid #e5e7eb; background:#f9fafb; }
.bf-city-search-wrap{ padding:12px; background:#ffffff; border-bottom:1px solid #eeeeee; }
.bf-city-search-box{ position:relative; display:flex; align-items:center; }
.bf-search-ico{ position:absolute; left:12px; color:#9ca3af; pointer-events:none; }
.bf-city-search-input{ padding-left:36px !important; padding-right:32px !important; font-size:12px !important; height:38px !important; border-radius:8px !important; border:1px solid #e5e7eb !important; width: 100%; box-sizing: border-box; }
.bf-clear-btn{ position:absolute; right:8px; background:none; border:none; color:#9ca3af; font-size:18px; cursor:pointer; }
.bf-custom-map{ width:100%; height:340px; position:relative; background:#e5e7eb; }
.bf-map-pin{ position:relative; width:36px; height:44px; transform:translate(-18px, -44px); cursor:pointer; }
.bf-pin-body{ width:36px; height:36px; background:#ffffff; border:2px solid #ffffff; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,0.2); overflow:hidden; }
.bf-pin-img{ display:block; width:100%; height:100%; object-fit:contain; }
.bf-pin-tail{ position:absolute; bottom:2px; left:50%; transform:translateX(-50%); width:0; height:0; border-left:5px solid transparent; border-right:5px solid transparent; border-top:8px solid #ee2d24; }
.bf-balloon{ font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color:#111111; padding:6px 2px; min-width:230px; }
.bf-balloon-name{ font-size:12px; font-weight:700; color:#111827; }
.bf-balloon-address{ font-size:13px; font-weight:600; color:#1f2937; line-height:1.4; margin-bottom:4px; }
.bf-balloon-extra{ font-size:11px; color:#6b7280; line-height:1.4; margin-bottom:6px; }
.bf-balloon-select-btn{ width:100%; background:#000000; color:#ffffff; border:none; padding:8px 12px; border-radius:8px; font-size:11px; font-weight:800; text-transform:uppercase; cursor:pointer; }
.fivepost-map-loader{ position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); z-index:1000; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#6b7280; font-size:13px; font-weight:600; }
.fivepost-spinner{ width:32px; height:32px; border:3px solid #e5e7eb; border-top-color:#10b981; border-radius:50%; animation:spin 1s linear infinite; margin-bottom:12px; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>

<div class="admin-main">
  <div class="crm-container">

    <!-- Header Actions -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 class="admin-title" style="margin-bottom: 4px;">Управление заказами (CRM)</h1>
            <div class="page-subtitle">Центр заказов, чеков и логистики 5Post</div>
        </div>
        <div style="display:flex; align-items:center; gap: 12px; flex-wrap:wrap;">
            <button id="btnSync5Post" class="btn btn-secondary btn-sm" title="Синхронизировать статусы посылок с 5Post">
                <svg id="syncIcon" class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                <span id="syncBtnText">Обновить 5Post</span>
            </button>
            <button id="btnTgSettings" class="btn btn-secondary btn-sm" title="Настройки Telegram">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Настройки ТГ
            </button>
            <button id="btnCreateOrder" class="btn btn-primary">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Создать заказ вручную
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-card-header">
                <span class="kpi-title">Всего заказов</span>
                <div class="kpi-icon-wrap">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                </div>
            </div>
            <div class="kpi-val"><?= number_format($totalOrders, 0, '', ' ') ?></div>
            <div class="kpi-meta">
                <span>В базе магазина</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card-header">
                <span class="kpi-title">Выручка (Оплачено)</span>
                <div class="kpi-icon-wrap" style="color: #10b981; background: rgba(16, 185, 129, 0.1);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                </div>
            </div>
            <div class="kpi-val"><?= number_format($totalRevenue, 0, '', ' ') ?> ₽</div>
            <div class="kpi-meta">
                <span style="color: #10b981; font-weight:600;">Успешные и ручные оплаты</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card-header">
                <span class="kpi-title">К отправке / Сборка</span>
                <div class="kpi-icon-wrap" style="color: #2563eb; background: rgba(37, 99, 235, 0.1);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                </div>
            </div>
            <div class="kpi-val"><?= $toShipCount ?></div>
            <div class="kpi-meta">
                <span>Оплачены, ждут передачу в 5Post</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card-header">
                <span class="kpi-title">В доставке 5Post</span>
                <div class="kpi-icon-wrap" style="color: #7c3aed; background: rgba(124, 58, 237, 0.1);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polyline></svg>
                </div>
            </div>
            <div class="kpi-val"><?= ($inTransitCount + $readyPickupCount) ?></div>
            <div class="kpi-meta" id="kpi5postMeta">
                <?php if ($lastSyncTs > 0): ?>
                    <span>Синхронизировано <?= ($syncDiffMinutes === 0 ? 'только что' : "{$syncDiffMinutes} мин назад") ?></span>
                <?php else: ?>
                    <span>Синхронизация активна</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Control & Filter Bar -->
    <div class="crm-control-bar">
        <div class="control-top-row">
            <div class="control-left-group">
                <!-- View Switcher -->
                <div class="view-switch-tabs" role="tablist">
                    <button type="button" class="view-tab-btn active" id="tabViewTable" title="Табличный вид списка">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                        Список
                    </button>
                    <button type="button" class="view-tab-btn" id="tabViewKanban" title="Канбан-доска стадий">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="18" rx="1"></rect><rect x="14" y="3" width="7" height="11" rx="1"></rect></svg>
                        Канбан
                    </button>
                </div>

                <!-- Live Search -->
                <div class="crm-search-box">
                    <svg class="crm-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="orderSearch" class="crm-search-input" placeholder="Поиск по номеру заказа, ФИО, телефону, товару или треку 5Post…">
                    <button type="button" id="searchClearBtn" class="crm-search-clear">&times;</button>
                </div>
            </div>

            <div style="font-size:12px; color:var(--text-muted); font-weight:600;" id="searchStatsText">
                Показано заказов: <span id="visibleCount" style="color:#000;"><?= $totalOrders ?></span>
            </div>
        </div>

        <!-- Filter Chips -->
        <div class="filter-chips-row">
            <button type="button" class="filter-chip active" data-filter="all">
                Все заказы <span class="chip-cnt"><?= $totalOrders ?></span>
            </button>
            <button type="button" class="filter-chip" data-filter="to_ship">
                К отправке <span class="chip-cnt"><?= $toShipCount ?></span>
            </button>
            <button type="button" class="filter-chip" data-filter="in_transit">
                В пути 5Post <span class="chip-cnt"><?= $inTransitCount ?></span>
            </button>
            <button type="button" class="filter-chip" data-filter="ready_pickup">
                В пункте выдачи <span class="chip-cnt"><?= $readyPickupCount ?></span>
            </button>
            <button type="button" class="filter-chip" data-filter="paid">
                Оплаченные <span class="chip-cnt"><?= count(array_filter($orders, fn($o) => $o['payment_status'] === 'paid' && $o['mark_code'] !== '__CANCELLED__')) ?></span>
            </button>
            <button type="button" class="filter-chip" data-filter="manual">
                Ручные <span class="chip-cnt"><?= count(array_filter($orders, fn($o) => $o['payment_status'] === 'manual' && $o['mark_code'] !== '__CANCELLED__')) ?></span>
            </button>
            <button type="button" class="filter-chip" data-filter="pending">
                Ожидают оплаты <span class="chip-cnt"><?= $pendingCount ?></span>
            </button>
            <button type="button" class="filter-chip" data-filter="cancelled">
                Отмененные <span class="chip-cnt"><?= $cancelledCount ?></span>
            </button>
        </div>
    </div>

    <!-- VIEW 1: TABLE VIEW (СПИСОК) -->
    <div id="ordersTableView" class="orders-table-container">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 140px;">ID Заказа</th>
                        <th style="width: 130px;">Дата</th>
                        <th>Покупатель</th>
                        <th>Товар и размер</th>
                        <th style="width: 170px;">Доставка (5Post)</th>
                        <th style="width: 120px;">Сумма</th>
                        <th style="width: 140px;">Оплата</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <?php foreach ($orders as $order): ?>
                        <?php 
                            $stage = getOrderStage($order);
                            $isCancelled = ($stage === 'cancelled'); 
                            $orderJson = htmlspecialchars(json_encode($order, JSON_UNESCAPED_UNICODE));
                            $barcode = $order['fivepost_barcode'] ?: '';
                        ?>
                        <tr class="order-row <?= $isCancelled ? 'cancelled' : '' ?>" 
                            data-order='<?= $orderJson ?>' 
                            data-stage="<?= $stage ?>"
                            data-payment="<?= htmlspecialchars($order['payment_status']) ?>">
                            <td>
                                <div style="font-family: var(--font-family); font-weight: 700; font-size: 13px; color: #000;">
                                    <?= htmlspecialchars($order['order_id']) ?>
                                </div>
                            </td>
                            <td style="white-space: nowrap; font-size:12px; color:var(--text-muted);">
                                <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
                            </td>
                            <td>
                                <div style="font-weight:600; color:#111;"><?= htmlspecialchars($order['fio'] ?: 'Покупатель') ?></div>
                                <div style="font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:4px; margin-top:2px;">
                                    <span><?= htmlspecialchars($order['phone']) ?></span>
                                    <?php if (!empty($order['phone'])): ?>
                                        <button type="button" class="copy-btn-inline" data-copy="<?= htmlspecialchars($order['phone']) ?>" title="Копировать номер" style="background:none; border:none; padding:0; cursor:pointer; color:var(--text-dim);">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600; color:#111;"><?= htmlspecialchars($order['product_name']) ?></div>
                                <div style="margin-top:2px;">
                                    <span style="font-size:11px; font-weight:600; background:var(--bg-main); border:1px solid var(--border-color); padding:1px 6px; border-radius:4px; color:var(--text-muted);">
                                        <?= htmlspecialchars($order['size'] ?: '—') ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                                    <?= getDeliveryBadge($order['fivepost_status']) ?>
                                    <?php if ($barcode): ?>
                                        <div class="k-barcode-chip" title="Штрихкод 5Post">
                                            <span><?= htmlspecialchars($barcode) ?></span>
                                            <button type="button" class="copy-btn-inline" data-copy="<?= htmlspecialchars($barcode) ?>" title="Копировать трек-номер" style="background:none; border:none; padding:0; cursor:pointer; color:#059669;">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                            </button>
                                        </div>
                                    <?php elseif (!empty($order['fivepost_point_address'])): ?>
                                        <span style="font-size:11px; color:var(--text-muted); max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($order['fivepost_point_address']) ?>">
                                            <?= htmlspecialchars($order['fivepost_point_address']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="price-cell" style="font-family: var(--font-family); font-weight:800; font-size:14px;">
                                <?= number_format((float)$order['price'], 0, '', ' ') ?> ₽
                            </td>
                            <td>
                                <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                                    <?= getPaymentBadge($order['payment_status'], $order['mark_code']) ?>
                                    <?php if ($order['receipt_sent'] == 1): ?>
                                        <span style="font-size:10px; font-weight:700; color:#10b981; display:flex; align-items:center; gap:2px;">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg> Чек пробит
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px; color: var(--text-muted);">
                                Заказов пока нет
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- VIEW 2: KANBAN BOARD (КАНБАН) -->
    <div id="ordersKanbanView" class="kanban-board-wrap" style="display:none;">
        <div class="kanban-board">
            <?php foreach ($stagesData as $stageKey => $stage): ?>
                <div class="kanban-col" data-col-stage="<?= $stageKey ?>">
                    <div class="kanban-col-header">
                        <div class="col-title-group">
                            <span class="col-dot" style="background-color: <?= $stage['color'] ?>;"></span>
                            <span class="col-title"><?= htmlspecialchars($stage['title']) ?></span>
                            <span class="col-badge" id="badge-<?= $stageKey ?>"><?= count($stage['orders']) ?></span>
                        </div>
                        <div class="col-sum" id="sum-<?= $stageKey ?>"><?= number_format($stage['sum'], 0, '', ' ') ?> ₽</div>
                    </div>

                    <div class="kanban-cards-container" id="colCards-<?= $stageKey ?>">
                        <?php foreach ($stage['orders'] as $order): ?>
                            <?php 
                                $orderJson = htmlspecialchars(json_encode($order, JSON_UNESCAPED_UNICODE));
                                $barcode = $order['fivepost_barcode'] ?: '';
                                $addr = $order['fivepost_point_address'] ?: '';
                                $timeStr = date('d M, H:i', strtotime($order['created_at']));
                                if (date('Y-m-d') === date('Y-m-d', strtotime($order['created_at']))) {
                                    $timeStr = 'Сегодня ' . date('H:i', strtotime($order['created_at']));
                                }
                            ?>
                            <div class="k-card <?= ($stageKey === 'cancelled') ? 'cancelled' : '' ?>" 
                                 data-order='<?= $orderJson ?>' 
                                 data-stage="<?= $stageKey ?>"
                                 data-payment="<?= htmlspecialchars($order['payment_status']) ?>">
                                
                                <div class="k-card-top">
                                    <span class="k-card-id"><?= htmlspecialchars($order['order_id']) ?></span>
                                    <span class="k-card-time"><?= $timeStr ?></span>
                                </div>

                                <div class="k-card-cust">
                                    <div class="k-card-name"><?= htmlspecialchars($order['fio'] ?: 'Покупатель') ?></div>
                                    <div class="k-card-phone">
                                        <span><?= htmlspecialchars($order['phone']) ?></span>
                                        <?php if (!empty($order['phone'])): ?>
                                            <button type="button" class="copy-btn-inline" data-copy="<?= htmlspecialchars($order['phone']) ?>" title="Копировать номер" style="background:none; border:none; padding:0; cursor:pointer; color:var(--text-dim);">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="k-card-prod">
                                    <span class="k-card-prod-title" title="<?= htmlspecialchars($order['product_name']) ?>"><?= htmlspecialchars($order['product_name']) ?></span>
                                    <span class="k-card-prod-size"><?= htmlspecialchars($order['size'] ?: '—') ?></span>
                                </div>

                                <?php if (!empty($order['fivepost_status']) || $barcode || $addr): ?>
                                    <div class="k-card-delivery">
                                        <div class="k-card-delivery-row">
                                            <?= getDeliveryBadge($order['fivepost_status']) ?>
                                            <?php if ($barcode): ?>
                                                <span class="k-barcode-chip" title="Штрихкод 5Post">
                                                    <?= htmlspecialchars($barcode) ?>
                                                    <button type="button" class="copy-btn-inline" data-copy="<?= htmlspecialchars($barcode) ?>" title="Копировать трек" style="background:none; border:none; padding:0; cursor:pointer; color:#059669;">
                                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                                    </button>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($addr): ?>
                                            <div class="k-card-address" title="<?= htmlspecialchars($addr) ?>">
                                                📍 <?= htmlspecialchars($addr) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="k-card-footer">
                                    <span class="k-card-price"><?= number_format((float)$order['price'], 0, '', ' ') ?> ₽</span>
                                    <div>
                                        <?= getPaymentBadge($order['payment_status'], $order['mark_code']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

  </div>
</div>

<!-- Модальное окно просмотра заказа -->
<div id="orderDetailModal" class="modal-overlay">
    <div class="modal-content">
        <svg id="closeDetailModal" class="modal-close icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        
        <div style="margin-bottom: 20px;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
                <h2 id="mdlOrderId" style="font-family: var(--font-family); font-size: 20px; font-weight: 800; margin:0;">Заказ #...</h2>
                <span id="mdlStageBadge" class="status-badge">...</span>
            </div>
            <div id="mdlOrderDate" style="font-size: 12px; color: var(--text-muted);">...</div>
        </div>

        <!-- Визуальный таймлайн стадий заказа -->
        <div class="order-lifecycle" id="mdlLifecycleBar">
            <div class="lifecycle-step" id="step-created">
                <div class="step-circle">1</div>
                <div class="step-label">Создан</div>
            </div>
            <div class="lifecycle-step" id="step-paid">
                <div class="step-circle">2</div>
                <div class="step-label">Оплачен</div>
            </div>
            <div class="lifecycle-step" id="step-shipped">
                <div class="step-circle">3</div>
                <div class="step-label">В 5Post</div>
            </div>
            <div class="lifecycle-step" id="step-pickup">
                <div class="step-circle">4</div>
                <div class="step-label">В ПВЗ</div>
            </div>
            <div class="lifecycle-step" id="step-done">
                <div class="step-circle">5</div>
                <div class="step-label">Вручен</div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-section-title">Данные клиента</div>
            <div class="info-grid">
                <div class="info-item"><div class="lbl">ФИО</div><div class="val" id="mdlFio"></div></div>
                <div class="info-item">
                    <div class="lbl">Телефон</div>
                    <div class="val" style="display:flex; align-items:center; gap:6px;">
                        <span id="mdlPhone"></span>
                        <button type="button" id="mdlCopyPhoneBtn" style="background:none; border:none; cursor:pointer; color:var(--text-dim);" title="Копировать телефон">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="info-item"><div class="lbl">Email</div><div class="val" id="mdlEmail"></div></div>
                <div class="info-item"><div class="lbl">Telegram</div><div class="val" id="mdlTg"></div></div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-section-title">Товар и расчет</div>
            <div class="info-grid">
                <div class="info-item"><div class="lbl">Название</div><div class="val" id="mdlProduct"></div></div>
                <div class="info-item"><div class="lbl">Сумма</div><div class="val" id="mdlPrice" style="font-family: var(--font-family); font-weight:800; color:#000;"></div></div>
                <div class="info-item"><div class="lbl">Размер</div><div class="val" id="mdlSize"></div></div>
                <div class="info-item"><div class="lbl">Статус платежа</div><div class="val" id="mdlPaymentStatus"></div></div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-section-title">Доставка 5Post</div>
            <div class="info-grid" style="margin-bottom: 12px;">
                <div class="info-item" style="grid-column: span 2;"><div class="lbl">Адрес ПВЗ</div><div class="val" id="mdl5pAddress"></div></div>
                <div class="info-item"><div class="lbl">ID Точки (UUID)</div><div class="val" id="mdl5pPointId" style="font-family:monospace; font-size:12px;"></div></div>
                <div class="info-item"><div class="lbl">ID Заказа (5Post)</div><div class="val" id="mdl5pOrderId" style="font-family:monospace; font-size:12px;"></div></div>
                <div class="info-item">
                    <div class="lbl">Штрихкод / Трек-номер</div>
                    <div class="val" style="display:flex; align-items:center; gap:6px;">
                        <span id="mdl5pBarcode" style="color:#059669; font-weight:700; font-family:monospace;"></span>
                        <button type="button" id="mdlCopyBarcodeBtn" style="background:none; border:none; cursor:pointer; color:#059669;" title="Копировать трек">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="info-item"><div class="lbl">Статус 5Post</div><div class="val" id="mdl5pStatus"></div></div>
            </div>
            <div id="mdlTrackingLinkWrap" style="display:none; margin-top:8px;">
                <a id="mdlTrackingLink" href="#" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:6px; font-size:12px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    Открыть на fivepost.ru
                </a>
            </div>
        </div>

        <div class="modal-section" id="mdlActionsSection">
            <div class="modal-section-title">Действия с заказом</div>
            
            <div id="receiptContainer" style="display:none; background: #fafaf8; padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 16px;">
                <div style="font-size: 12px; font-weight: 700; margin-bottom: 8px;">Маркировка и закрывающий чек 54-ФЗ</div>
                <div style="display:flex; gap: 8px;">
                    <input type="text" id="mdlMarkCode" class="form-input" placeholder="Пикните сканером код маркировки (Честный ЗНАК)" style="font-family: monospace; font-size:12px; flex: 1;">
                    <button id="btnSendReceipt" class="btn btn-primary btn-sm">Отправить чек</button>
                </div>
            </div>

            <div style="display:flex; gap: 12px; flex-wrap: wrap;">
                <button id="btnManual5Post" class="btn btn-secondary btn-sm" style="display:none; color:#10b981; border-color:#10b981; font-weight:700;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Отправить в 5Post вручную
                </button>
                <button id="btnCancelOrder" class="btn btn-danger btn-sm" style="display:none;">
                    Отменить заказ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно создания ручного заказа -->
<div id="createManualModal" class="modal-overlay">
    <div class="modal-content">
        <svg id="closeCreateModal" class="modal-close icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        
        <h2 style="font-family: var(--font-family); font-size: 20px; font-weight: 800; margin-bottom: 24px;">Создание ручного заказа 5Post</h2>
        
        <form id="createManualForm">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">ФИО клиента *</label>
                    <input type="text" id="crFio" class="form-input" required placeholder="Иван Иванов">
                </div>
                <div class="form-group">
                    <label class="form-label">Телефон *</label>
                    <input type="text" id="crPhone" class="form-input" placeholder="+7..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="crEmail" class="form-input" placeholder="client@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Название товара *</label>
                    <select id="crProduct" class="form-input" required>
                        <option value="">Выберите товар...</option>
                        <?php foreach ($productsData as $prod): ?>
                            <option value="<?= htmlspecialchars($prod['name']) ?>" data-id="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Пол *</label>
                    <select id="crGender" class="form-input" required disabled>
                        <option value="">Сначала выберите товар</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Размер *</label>
                    <select id="crSize" class="form-input" required disabled>
                        <option value="">Сначала выберите пол</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Сумма (₽) *</label>
                    <input type="number" id="crPrice" class="form-input" value="0" required>
                </div>
            </div>
            <div class="form-group" style="margin-top: 14px;">
                <label class="form-label">Выбор точки 5Post на карте *</label>
                
                <input type="hidden" id="crPointId" required>
                <input type="hidden" id="crAddress" required>
                <input type="hidden" id="crPointType">
                <input type="hidden" id="crPointName">
                <input type="hidden" id="crPointDetails">

                <div class="fivepost-selected-card" id="adminFivepostSelectedCard" style="display:none; margin-bottom: 12px;">
                    <div class="fivepost-selected-details">
                        <div class="fivepost-selected-header">
                            <strong class="fivepost-selected-name" id="adminFivepostPointNameDisplay">Пятёрочка</strong>
                        </div>
                        <div class="fivepost-selected-address" id="adminFivepostPointAddressDisplay">Адрес</div>
                        <div class="fivepost-selected-extra" id="adminFivepostPointExtraDisplay">Выдача заказа 5Post</div>
                    </div>
                    <button type="button" class="fivepost-change-btn" id="adminFivepostChangeBtn">Изменить</button>
                </div>

                <div class="fivepost-map-wrapper" id="adminFivepostMapWrapper">
                    <div class="bf-city-search-wrap">
                        <div class="bf-city-search-box">
                            <svg class="bf-search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" id="adminFivepostCitySearch" class="ozon-input bf-city-search-input" placeholder="Поиск по городу или улице (например, Пенза, Победы…)" autocomplete="off">
                            <button type="button" id="adminFivepostCityClear" class="bf-clear-btn" style="display:none;" aria-label="Очистить">&times;</button>
                        </div>
                    </div>

                    <div id="adminFivepostCustomMap" class="bf-custom-map" style="width: 100%; height: 340px; background: #f3f4f6; position:relative;">
                        <div class="fivepost-map-loader" id="adminFivepostMapLoader" style="display:none;">
                            <div class="fivepost-spinner"></div>
                            <span>Загрузка карты и точек выдачи…</span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top: 24px; display:flex; gap: 12px; justify-content: flex-end;">
                <button type="button" id="btnCancelCreate" class="btn btn-secondary">Отмена</button>
                <button type="submit" id="btnSubmitCreate" class="btn btn-primary">Создать и отправить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модалка настроек ТГ -->
<div id="tgSettingsModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 520px;">
        <svg id="tgSettingsCloseIcon" class="modal-close icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        <h3 style="margin-top:0; margin-bottom:16px; font-size:18px; font-family:var(--font-family); font-weight:800;">Секреты Telegram</h3>
        <p style="font-size:13px; color:var(--text-muted); margin-bottom: 16px;">Укажите данные бота и канала. Все уведомления маршрутизируются через Cloudflare Worker.</p>
        
        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label">Telegram Bot Token (HTTP API):</label>
            <input type="text" id="tgBotTokenInput" class="form-input" value="<?= htmlspecialchars($tgToken) ?>" placeholder="123456:ABC-DEF...">
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label">Telegram Chat ID (ID чата или канала):</label>
            <input type="text" id="tgChatIdInput" class="form-input" value="<?= htmlspecialchars($tgChatId) ?>" placeholder="-1003895656779">
        </div>

        <div style="display:flex; gap:12px; justify-content:space-between; align-items:center; margin-top: 16px;">
            <button id="tgTestBtn" class="btn btn-secondary" style="font-size: 13px;">Отправить тест</button>
            <div style="display:flex; gap:12px;">
                <button id="tgSettingsClose" class="btn btn-secondary">Отмена</button>
                <button id="tgSettingsSave" class="btn btn-primary">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<!-- Всплывающий тост для копирования -->
<div id="copyToast">Скопировано в буфер</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Переключение режимов: Таблица / Канбан
    const tabTable = document.getElementById('tabViewTable');
    const tabKanban = document.getElementById('tabViewKanban');
    const tableView = document.getElementById('ordersTableView');
    const kanbanView = document.getElementById('ordersKanbanView');

    function setViewMode(mode) {
        if (mode === 'kanban') {
            tabTable.classList.remove('active');
            tabKanban.classList.add('active');
            tableView.style.display = 'none';
            kanbanView.style.display = 'block';
            localStorage.setItem('bf_orders_view_mode', 'kanban');
        } else {
            tabKanban.classList.remove('active');
            tabTable.classList.add('active');
            kanbanView.style.display = 'none';
            tableView.style.display = 'block';
            localStorage.setItem('bf_orders_view_mode', 'table');
        }
    }

    const savedMode = localStorage.getItem('bf_orders_view_mode') || 'table';
    setViewMode(savedMode);

    tabTable.addEventListener('click', () => setViewMode('table'));
    tabKanban.addEventListener('click', () => setViewMode('kanban'));

    // Живой поиск и фильтры по чипсам
    const searchInput = document.getElementById('orderSearch');
    const searchClear = document.getElementById('searchClearBtn');
    const filterChips = document.querySelectorAll('.filter-chip');
    const tableRows = document.querySelectorAll('#ordersTableBody .order-row');
    const kanbanCards = document.querySelectorAll('.kanban-cards-container .k-card');
    const visibleCountSpan = document.getElementById('visibleCount');

    let currentFilter = 'all';

    function runFilter() {
        const query = searchInput.value.trim().toLowerCase();
        searchClear.style.display = query ? 'block' : 'none';

        let visibleTotal = 0;

        // Фильтруем таблицу
        tableRows.forEach(row => {
            const data = JSON.parse(row.getAttribute('data-order'));
            const stage = row.getAttribute('data-stage');
            const payment = row.getAttribute('data-payment');
            const isCancelled = (stage === 'cancelled');

            let textMatch = true;
            if (query) {
                const combined = `${data.order_id} ${data.fio} ${data.phone} ${data.product_name} ${data.fivepost_barcode || ''} ${data.fivepost_point_address || ''}`.toLowerCase();
                textMatch = combined.includes(query);
            }

            let chipMatch = true;
            if (currentFilter === 'to_ship') chipMatch = (stage === 'to_ship');
            else if (currentFilter === 'in_transit') chipMatch = (stage === 'in_transit');
            else if (currentFilter === 'ready_pickup') chipMatch = (stage === 'ready_pickup');
            else if (currentFilter === 'paid') chipMatch = (payment === 'paid' && !isCancelled);
            else if (currentFilter === 'manual') chipMatch = (payment === 'manual' && !isCancelled);
            else if (currentFilter === 'pending') chipMatch = (stage === 'pending');
            else if (currentFilter === 'cancelled') chipMatch = (stage === 'cancelled');

            const isShown = textMatch && chipMatch;
            row.style.display = isShown ? '' : 'none';
            if (isShown) visibleTotal++;
        });

        // Фильтруем карточки Канбана и обновляем бейджи колонок
        document.querySelectorAll('.kanban-col').forEach(col => {
            const colStage = col.getAttribute('data-col-stage');
            const cards = col.querySelectorAll('.k-card');
            let colVisible = 0;
            let colSum = 0;

            cards.forEach(card => {
                const data = JSON.parse(card.getAttribute('data-order'));
                const stage = card.getAttribute('data-stage');
                const payment = card.getAttribute('data-payment');
                const isCancelled = (stage === 'cancelled');

                let textMatch = true;
                if (query) {
                    const combined = `${data.order_id} ${data.fio} ${data.phone} ${data.product_name} ${data.fivepost_barcode || ''} ${data.fivepost_point_address || ''}`.toLowerCase();
                    textMatch = combined.includes(query);
                }

                let chipMatch = true;
                if (currentFilter === 'to_ship') chipMatch = (stage === 'to_ship');
                else if (currentFilter === 'in_transit') chipMatch = (stage === 'in_transit');
                else if (currentFilter === 'ready_pickup') chipMatch = (stage === 'ready_pickup');
                else if (currentFilter === 'paid') chipMatch = (payment === 'paid' && !isCancelled);
                else if (currentFilter === 'manual') chipMatch = (payment === 'manual' && !isCancelled);
                else if (currentFilter === 'pending') chipMatch = (stage === 'pending');
                else if (currentFilter === 'cancelled') chipMatch = (stage === 'cancelled');

                const isShown = textMatch && chipMatch;
                card.style.display = isShown ? '' : 'none';
                if (isShown) {
                    colVisible++;
                    colSum += parseFloat(data.price || 0);
                }
            });

            const badge = document.getElementById('badge-' + colStage);
            const sumEl = document.getElementById('sum-' + colStage);
            if (badge) badge.textContent = colVisible;
            if (sumEl) sumEl.textContent = new Intl.NumberFormat('ru-RU').format(colSum) + ' ₽';
        });

        if (visibleCountSpan) visibleCountSpan.textContent = visibleTotal;
    }

    searchInput.addEventListener('input', runFilter);
    searchClear.addEventListener('click', () => {
        searchInput.value = '';
        runFilter();
    });

    filterChips.forEach(chip => {
        chip.addEventListener('click', function() {
            filterChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.getAttribute('data-filter');
            runFilter();
        });
    });

    // Быстрое копирование в буфер
    const copyToast = document.getElementById('copyToast');
    let toastTimeout = null;
    function showToast(text) {
        copyToast.textContent = text || 'Скопировано в буфер';
        copyToast.style.display = 'block';
        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => copyToast.style.display = 'none', 1800);
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.copy-btn-inline');
        if (btn) {
            e.stopPropagation();
            const text = btn.getAttribute('data-copy');
            if (text) {
                navigator.clipboard.writeText(text).then(() => showToast('Скопировано: ' + text));
            }
        }
    });

    // Синхронизация 5Post по кнопке
    const btnSync5Post = document.getElementById('btnSync5Post');
    const syncIcon = document.getElementById('syncIcon');
    const syncBtnText = document.getElementById('syncBtnText');

    btnSync5Post.addEventListener('click', function() {
        syncIcon.style.animation = 'spin 1s linear infinite';
        syncBtnText.textContent = 'Обновление…';
        btnSync5Post.disabled = true;

        fetch('api_sync_5post.php?force=1', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.result.message || 'Статусы 5Post обновлены');
                    setTimeout(() => location.reload(), 600);
                } else {
                    alert('Ошибка: ' + (data.result?.error || 'Сбой обновления'));
                }
            })
            .catch(() => alert('Сетевая ошибка при обращении к 5Post'))
            .finally(() => {
                syncIcon.style.animation = '';
                syncBtnText.textContent = 'Обновить 5Post';
                btnSync5Post.disabled = false;
            });
    });

    // Модальное окно деталей заказа
    const detailModal = document.getElementById('orderDetailModal');
    let currentOrderData = null;

    function openOrderDetail(data) {
        currentOrderData = data;
        
        document.getElementById('mdlOrderId').textContent = 'Заказ #' + data.order_id;
        document.getElementById('mdlOrderDate').textContent = 'Оформлен: ' + data.created_at;
        document.getElementById('mdlFio').textContent = data.fio || 'Покупатель';
        document.getElementById('mdlPhone').textContent = data.phone || '—';
        document.getElementById('mdlEmail').textContent = data.email || '—';
        document.getElementById('mdlTg').textContent = data.tg_username ? ('@' + data.tg_username.replace(/^@/, '')) : '—';
        document.getElementById('mdlProduct').textContent = data.product_name || '—';
        document.getElementById('mdlPrice').textContent = (new Intl.NumberFormat('ru-RU').format(data.price || 0)) + ' ₽';
        document.getElementById('mdlSize').textContent = data.size || '—';
        
        const isCancelled = (data.mark_code === '__CANCELLED__' || data.payment_status === 'refunded');
        
        let payStr = data.payment_status;
        if (isCancelled) payStr = 'Отменён';
        else if (payStr === 'paid') payStr = 'Оплачен (ЮKassa)';
        else if (payStr === 'manual') payStr = 'Ручной заказ';
        else payStr = 'Ожидает оплаты';
        document.getElementById('mdlPaymentStatus').textContent = payStr;

        document.getElementById('mdl5pAddress').textContent = data.fivepost_point_address || '—';
        document.getElementById('mdl5pPointId').textContent = data.fivepost_point_id || '—';
        document.getElementById('mdl5pOrderId').textContent = data.fivepost_order_id || '—';
        document.getElementById('mdl5pBarcode').textContent = data.fivepost_barcode || '—';
        document.getElementById('mdl5pStatus').textContent = data.fivepost_status || 'Без отправки';

        // Трекинг ссылка
        const trWrap = document.getElementById('mdlTrackingLinkWrap');
        const trLink = document.getElementById('mdlTrackingLink');
        if (data.fivepost_barcode) {
            trWrap.style.display = 'block';
            trLink.href = 'https://fivepost.ru/';
        } else {
            trWrap.style.display = 'none';
        }

        // Стадия и Lifecycle таймлайн
        const stBadge = document.getElementById('mdlStageBadge');
        const fpSt = data.fivepost_status || '';
        const isPaid = (data.payment_status === 'paid' || data.payment_status === 'manual');
        const isSent = Boolean(data.fivepost_order_id);
        const isPickup = ['PLACED_IN_POSTAMAT', 'READY_FOR_WITHDRAW_FROM_PICKUP_POINT', 'WAITING_FOR_REPICKUP'].includes(fpSt);
        const isDone = ['DONE', 'PICKED_UP'].includes(fpSt);

        const sCreated = document.getElementById('step-created');
        const sPaid = document.getElementById('step-paid');
        const sShipped = document.getElementById('step-shipped');
        const sPickup = document.getElementById('step-pickup');
        const sDone = document.getElementById('step-done');

        [sCreated, sPaid, sShipped, sPickup, sDone].forEach(s => {
            s.classList.remove('active', 'done');
        });

        sCreated.classList.add('done');
        if (isPaid) sPaid.classList.add('done');
        else sPaid.classList.add('active');

        if (isSent) sShipped.classList.add('done');
        else if (isPaid) sShipped.classList.add('active');

        if (isPickup) sPickup.classList.add('done');
        else if (isSent && !isDone) sPickup.classList.add('active');

        if (isDone) sDone.classList.add('done');

        if (isCancelled) {
            stBadge.className = 'status-badge badge-cancelled';
            stBadge.textContent = 'Отменён';
        } else if (isDone) {
            stBadge.className = 'status-badge badge-paid';
            stBadge.textContent = 'Выполнен';
        } else if (isPickup) {
            stBadge.className = 'status-badge';
            stBadge.style.color = '#059669';
            stBadge.style.background = 'rgba(16,185,129,0.1)';
            stBadge.textContent = 'В пункте выдачи';
        } else if (isSent) {
            stBadge.className = 'status-badge';
            stBadge.style.color = '#7c3aed';
            stBadge.style.background = 'rgba(124,58,237,0.1)';
            stBadge.textContent = 'В пути 5Post';
        } else if (isPaid) {
            stBadge.className = 'status-badge badge-manual';
            stBadge.textContent = 'К отправке';
        } else {
            stBadge.className = 'status-badge badge-pending';
            stBadge.textContent = 'Ожидает оплаты';
        }

        // Кнопки действий
        const receiptCont = document.getElementById('receiptContainer');
        const btnCancel = document.getElementById('btnCancelOrder');
        const btnManual = document.getElementById('btnManual5Post');
        const markInput = document.getElementById('mdlMarkCode');

        receiptCont.style.display = 'none';
        btnCancel.style.display = 'none';
        btnManual.style.display = 'none';
        markInput.value = '';

        if (data.receipt_sent == 1) {
            receiptCont.style.display = 'block';
            receiptCont.innerHTML = `<div style="color:#059669; font-weight:700;">Чек 54-ФЗ успешно пробит</div><div style="font-size:11px; margin-top:4px; font-family:monospace;">${data.mark_code}</div>`;
        } else if (!isCancelled && data.payment_status === 'paid') {
            receiptCont.style.display = 'block';
            receiptCont.innerHTML = `
                <div style="font-size: 12px; font-weight: 700; margin-bottom: 8px;">Маркировка и закрывающий чек 54-ФЗ</div>
                <div style="display:flex; gap: 8px;">
                    <input type="text" id="mdlMarkCode" class="form-input" placeholder="Пикните сканером код маркировки" style="font-family: monospace; font-size:12px; flex: 1;">
                    <button id="btnSendReceipt" class="btn btn-primary btn-sm">Отправить чек</button>
                </div>
            `;
            document.getElementById('btnSendReceipt').addEventListener('click', handleSendReceipt);
        }

        // Ручная отправка в 5Post если не отправлен
        if (!isCancelled && !data.fivepost_order_id && data.fivepost_point_id && isPaid) {
            btnManual.style.display = 'inline-flex';
        }

        if (!isCancelled && data.payment_status !== 'manual') {
            btnCancel.style.display = 'inline-flex';
        }

        detailModal.style.display = 'flex';
    }

    // Слушатели открытия модалки по клику
    document.querySelectorAll('.order-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.closest('.copy-btn-inline')) return;
            const data = JSON.parse(this.getAttribute('data-order'));
            openOrderDetail(data);
        });
    });

    document.querySelectorAll('.k-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.closest('.copy-btn-inline')) return;
            const data = JSON.parse(this.getAttribute('data-order'));
            openOrderDetail(data);
        });
    });

    document.getElementById('closeDetailModal').addEventListener('click', () => detailModal.style.display = 'none');

    document.getElementById('mdlCopyPhoneBtn').addEventListener('click', () => {
        if (currentOrderData && currentOrderData.phone) {
            navigator.clipboard.writeText(currentOrderData.phone).then(() => showToast('Телефон скопирован'));
        }
    });

    document.getElementById('mdlCopyBarcodeBtn').addEventListener('click', () => {
        if (currentOrderData && currentOrderData.fivepost_barcode) {
            navigator.clipboard.writeText(currentOrderData.fivepost_barcode).then(() => showToast('Штрихкод скопирован'));
        }
    });

    // Отправка чека
    function handleSendReceipt() {
        const markCode = document.getElementById('mdlMarkCode').value.trim();
        if (!markCode) return alert('Введите код маркировки');
        
        const btn = document.getElementById('btnSendReceipt');
        btn.disabled = true;
        btn.textContent = 'Отправка…';
        
        fetch('api_receipt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: currentOrderData.order_id, mark_code: markCode })
        })
        .then(r => r.json())
        .then(d => {
            if(d.success) {
                alert('Чек пробит!');
                location.reload();
            } else {
                alert('Ошибка: ' + d.error);
                btn.disabled = false;
                btn.textContent = 'Отправить чек';
            }
        });
    }

    // Ручная отправка в 5Post из модалки
    document.getElementById('btnManual5Post').addEventListener('click', function() {
        if (!confirm('Отправить заказ ' + currentOrderData.order_id + ' в 5Post сейчас?')) return;
        this.disabled = true;
        this.textContent = 'Отправка…';

        fetch('api_manual_5post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: currentOrderData.order_id })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('Заказ успешно зарегистрирован в 5Post!');
                location.reload();
            } else {
                alert('Ошибка 5Post: ' + (d.error || 'Неизвестная ошибка'));
                this.disabled = false;
                this.textContent = 'Отправить в 5Post вручную';
            }
        })
        .catch(() => {
            alert('Сетевая ошибка');
            this.disabled = false;
            this.textContent = 'Отправить в 5Post вручную';
        });
    });

    // Отмена заказа
    document.getElementById('btnCancelOrder').addEventListener('click', function() {
        if (!confirm('Точно отменить заказ ' + currentOrderData.order_id + '?')) return;
        this.disabled = true;
        
        fetch('api_cancel_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: currentOrderData.order_id })
        })
        .then(r => r.json())
        .then(d => {
            if(d.success) location.reload();
            else { alert(d.error); this.disabled = false; }
        });
    });

    // Модалка создания ручного заказа
    const createModal = document.getElementById('createManualModal');
    document.getElementById('btnCreateOrder').addEventListener('click', () => {
        createModal.style.display = 'flex';
        setTimeout(() => {
            if (typeof ymaps !== 'undefined') ymaps.ready(window.initAdminMap);
        }, 100);
    });
    
    document.getElementById('closeCreateModal').addEventListener('click', () => createModal.style.display = 'none');
    document.getElementById('btnCancelCreate').addEventListener('click', () => createModal.style.display = 'none');

    // Каскадные выпадающие списки (Товар -> Пол -> Размер -> Цена)
    const adminProductsData = <?= json_encode($productsData, JSON_UNESCAPED_UNICODE) ?>;
    const selectCrProduct = document.getElementById('crProduct');
    const selectCrGender = document.getElementById('crGender');
    const selectCrSize = document.getElementById('crSize');
    const inputCrPrice = document.getElementById('crPrice');

    selectCrProduct.addEventListener('change', function() {
        selectCrGender.innerHTML = '<option value="">Выберите пол...</option>';
        selectCrSize.innerHTML = '<option value="">Сначала выберите пол</option>';
        selectCrGender.disabled = true;
        selectCrSize.disabled = true;
        
        const selVal = this.value;
        if (!selVal) return;
        
        const prod = adminProductsData.find(p => p.name === selVal);
        if (prod) {
            inputCrPrice.value = prod.price_numeric || 0;
            if (prod.sizes && Object.keys(prod.sizes).length > 0) {
                const genders = Object.keys(prod.sizes);
                genders.forEach(g => {
                    const opt = document.createElement('option');
                    opt.value = g;
                    opt.textContent = g;
                    selectCrGender.appendChild(opt);
                });
                selectCrGender.disabled = false;
                if (genders.length === 1) {
                    selectCrGender.value = genders[0];
                    selectCrGender.dispatchEvent(new Event('change'));
                }
            } else {
                selectCrGender.innerHTML = '<option value="-">Нет данных о поле</option>';
                selectCrSize.innerHTML = '<option value="-">Нет данных о размере</option>';
                selectCrGender.disabled = false;
                selectCrSize.disabled = false;
            }
        }
    });

    selectCrGender.addEventListener('change', function() {
        selectCrSize.innerHTML = '<option value="">Выберите размер...</option>';
        selectCrSize.disabled = true;
        
        const selGender = this.value;
        if (!selGender) return;
        
        const prod = adminProductsData.find(p => p.name === selectCrProduct.value);
        if (prod && prod.sizes && prod.sizes[selGender]) {
            const sizes = prod.sizes[selGender];
            sizes.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s;
                opt.textContent = s;
                selectCrSize.appendChild(opt);
            });
            selectCrSize.disabled = false;
            if (sizes.length === 1) {
                selectCrSize.value = sizes[0];
            }
        }
    });

    document.getElementById('createManualForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitCreate');
        btn.disabled = true;
        btn.textContent = 'Отправка...';

        const payload = {
            fio: document.getElementById('crFio').value,
            phone: document.getElementById('crPhone').value,
            email: document.getElementById('crEmail').value,
            product_name: document.getElementById('crProduct').value,
            price: document.getElementById('crPrice').value,
            size: (document.getElementById('crGender').value !== '-' ? document.getElementById('crGender').value + ' ' : '') + document.getElementById('crSize').value,
            fivepost_point_id: document.getElementById('crPointId').value,
            fivepost_point_address: document.getElementById('crAddress').value
        };

        fetch('api_create_manual_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(d => {
            if(d.success) {
                alert('Успешно создано и отправлено в 5Post!');
                location.reload();
            } else {
                alert(d.error);
                btn.disabled = false;
                btn.textContent = 'Создать и отправить';
            }
        });
    });
    
    // Telegram Settings
    const tgSettingsModal = document.getElementById('tgSettingsModal');
    const btnTgSettings = document.getElementById('btnTgSettings');
    const tgSettingsClose = document.getElementById('tgSettingsClose');
    const tgSettingsCloseIcon = document.getElementById('tgSettingsCloseIcon');
    const tgSettingsSave = document.getElementById('tgSettingsSave');
    const tgTestBtn = document.getElementById('tgTestBtn');

    if (btnTgSettings && tgSettingsModal) {
        btnTgSettings.addEventListener('click', () => tgSettingsModal.style.display = 'flex');
        tgSettingsClose.addEventListener('click', () => tgSettingsModal.style.display = 'none');
        tgSettingsCloseIcon.addEventListener('click', () => tgSettingsModal.style.display = 'none');

        tgSettingsSave.addEventListener('click', function() {
            const token = document.getElementById('tgBotTokenInput').value.trim();
            const chatId = document.getElementById('tgChatIdInput').value.trim();
            const originalText = this.textContent;
            this.textContent = 'Сохранение...';
            this.disabled = true;

            fetch('api_tg_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tg_bot_token: token, tg_chat_id: chatId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    tgSettingsModal.style.display = 'none';
                    alert('Настройки Telegram успешно сохранены!');
                } else {
                    alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                }
            })
            .catch(() => alert('Сетевая ошибка'))
            .finally(() => { this.textContent = originalText; this.disabled = false; });
        });
        
        tgTestBtn.addEventListener('click', function() {
            const originalText = this.textContent;
            this.textContent = 'Отправка...';
            this.disabled = true;
            fetch('api_tg_test.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if (data.success) alert('Тестовое сообщение успешно отправлено!');
                else alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
            })
            .catch(() => alert('Сетевая ошибка'))
            .finally(() => { this.textContent = originalText; this.disabled = false; });
        });
    }

    // Yandex Maps Logic для выбора точек 5Post
    let adminMapInitialized = false;
    let adminMap = null;
    let adminPointsCollection = null;
    let currentAdminPoints = [];
    let customAdminPinLayout = null;
    const renderedAdminPointIds = new Set();
    let adminBoundsDebounce = null;
    let isAdminProgrammaticMove = false;
    let currentAdminCity = 'Пенза';

    function setAdminProgrammaticMove() {
        isAdminProgrammaticMove = true;
        setTimeout(() => isAdminProgrammaticMove = false, 700);
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function handleSelectAdminPoint(point) {
        if (!point) return;
        const pointType = point.type || "POSTAMAT";
        const pointName = point.name || (pointType === "POSTAMAT" ? "Постамат 5Post" : "Касса «Пятёрочка»");
        const pointAddr = point.full_address || point.resultAddress || point.address || "";
        const pointExtra = [point.additional, point.work_hours].filter(Boolean).join(" · ");

        document.getElementById('crPointId').value = point.id || "";
        document.getElementById('crAddress').value = pointAddr;
        document.getElementById('crPointType').value = pointType;
        document.getElementById('crPointName').value = pointName;
        document.getElementById('crPointDetails').value = pointExtra;

        document.getElementById('adminFivepostPointNameDisplay').textContent = pointName;
        document.getElementById('adminFivepostPointAddressDisplay').textContent = pointAddr;
        document.getElementById('adminFivepostPointExtraDisplay').textContent = pointExtra || "Выдача заказа 5Post";

        document.getElementById('adminFivepostSelectedCard').style.display = 'flex';
        document.getElementById('adminFivepostMapWrapper').style.display = 'none';
        
        if (adminMap && adminMap.balloon) adminMap.balloon.close();
    }

    function createAdminCustomPinLayout() {
        if (customAdminPinLayout || typeof ymaps === "undefined") return customAdminPinLayout;
        customAdminPinLayout = ymaps.templateLayoutFactory.createClass(
            '<div class="bf-map-pin fivepost-pin" title="$[properties.hintContent]">' +
            '<div class="bf-pin-body">' +
            '<img src="/images/pyaterochka.png" alt="Пятёрочка" class="bf-pin-img" width="36" height="36">' +
            '</div>' +
            '<div class="bf-pin-tail"></div>' +
            '</div>'
        );
        return customAdminPinLayout;
    }

    function createAdminPointPlacemark(point, pinLayout) {
        const isPostamat = (point.type === "POSTAMAT");
        const titleName = point.name || (isPostamat ? "Постамат 5Post" : "Касса «Пятёрочка»");
        const fullAddr = point.full_address || point.street || "";

        const balloonHtml = `
            <div class="bf-balloon">
                <div class="bf-balloon-header">
                    <strong class="bf-balloon-name">${escapeHtml(titleName)}</strong>
                </div>
                <div class="bf-balloon-address">${escapeHtml(fullAddr)}</div>
                ${point.work_hours ? `<div class="bf-balloon-extra">${escapeHtml(point.work_hours)}</div>` : ''}
                ${point.additional ? `<div class="bf-balloon-extra">${escapeHtml(point.additional)}</div>` : ''}
                <button type="button" class="bf-balloon-select-btn" data-point-id="${escapeHtml(point.id)}">Выбрать эту точку</button>
            </div>
        `;

        return new ymaps.Placemark([point.lat, point.lng], {
            hintContent: (point.name ? point.name + " · " : "") + fullAddr,
            balloonContent: balloonHtml
        }, {
            iconLayout: pinLayout,
            iconOffset: [0, 0],
            iconShape: { type: "Rectangle", coordinates: [[-18, -44], [18, 0]] },
            hideIconOnBalloonOpen: false,
            balloonOffset: [0, -44],
            balloonCloseButton: true,
            balloonAutoPan: true
        });
    }

    function renderAdminMarkers(points, adjustViewport = true) {
        if (!adminPointsCollection || !adminMap) return;
        adminPointsCollection.removeAll();
        renderedAdminPointIds.clear();
        currentAdminPoints = [];
        if (!points || points.length === 0) return;

        const pinLayout = createAdminCustomPinLayout();
        points.forEach(point => {
            if (!point.lat || !point.lng) return;
            renderedAdminPointIds.add(String(point.id));
            currentAdminPoints.push(point);
            adminPointsCollection.add(createAdminPointPlacemark(point, pinLayout));
        });

        if (adjustViewport) {
            setAdminProgrammaticMove();
            if (points.length === 1) {
                adminMap.setCenter([points[0].lat, points[0].lng], 15, { checkZoomRange: true });
            } else if (points.length > 1) {
                const bounds = adminPointsCollection.getBounds();
                if (bounds) adminMap.setBounds(bounds, { checkZoomRange: true, zoomMargin: 40 });
            }
        }
    }

    function appendAdminMarkers(newPoints) {
        if (!adminPointsCollection || !adminMap || !newPoints || newPoints.length === 0) return;
        const pinLayout = createAdminCustomPinLayout();
        newPoints.forEach(point => {
            if (!point.lat || !point.lng) return;
            const strId = String(point.id);
            if (renderedAdminPointIds.has(strId)) return;
            renderedAdminPointIds.add(strId);
            currentAdminPoints.push(point);
            adminPointsCollection.add(createAdminPointPlacemark(point, pinLayout));
        });
    }

    function loadAdminPointsByBounds() {
        if (!adminMap || !adminMapInitialized) return;
        const bounds = adminMap.getBounds();
        if (!bounds) return;
        const zoom = adminMap.getZoom();
        if (zoom < 8) return;

        const latMin = Math.min(bounds[0][0], bounds[1][0]);
        const latMax = Math.max(bounds[0][0], bounds[1][0]);
        const lngMin = Math.min(bounds[0][1], bounds[1][1]);
        const lngMax = Math.max(bounds[0][1], bounds[1][1]);
        const boundsParam = `${latMin.toFixed(6)},${lngMin.toFixed(6)},${latMax.toFixed(6)},${lngMax.toFixed(6)}`;
        
        fetch(`/api/fivepost-points.php?bounds=${encodeURIComponent(boundsParam)}&zoom=${zoom}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.success && Array.isArray(data.points)) appendAdminMarkers(data.points);
            }).catch(err => console.warn(err));
    }

    function loadAdminPoints(city, search, isInitial) {
        const loader = document.getElementById('adminFivepostMapLoader');
        if (isInitial && loader) loader.style.display = "flex";
        let url = "/api/fivepost-points.php?";
        if (city) url += "city=" + encodeURIComponent(city) + "&";
        if (search) url += "search=" + encodeURIComponent(search);

        fetch(url).then(res => res.json()).then(data => {
            if (data && data.success && Array.isArray(data.points)) {
                renderAdminMarkers(data.points);
                if (data.city) currentAdminCity = data.city;
                if (data.points.length === 0 && search && adminMap && typeof ymaps !== "undefined" && ymaps.geocode) {
                    ymaps.geocode(data.city || search).then(res => {
                        const firstGeo = res.geoObjects.get(0);
                        if (firstGeo) {
                            setAdminProgrammaticMove();
                            adminMap.setCenter(firstGeo.geometry.getCoordinates(), 11, { checkZoomRange: true });
                        }
                    });
                }
            }
        }).catch(err => console.error(err)).finally(() => {
            if (loader) loader.style.display = "none";
        });
    }

    window.initAdminMap = function() {
        if (adminMapInitialized || typeof ymaps === 'undefined') {
            if (adminMap) setTimeout(() => { try { adminMap.container.fitToViewport(); } catch(e){} }, 300);
            return;
        }
        
        adminMap = new ymaps.Map('adminFivepostCustomMap', {
            center: [53.20066, 44.99965],
            zoom: 12,
            controls: ['zoomControl', 'fullscreenControl']
        }, { suppressMapOpenBlock: true });

        adminPointsCollection = new ymaps.GeoObjectCollection();
        adminMap.geoObjects.add(adminPointsCollection);
        adminMapInitialized = true;

        adminMap.events.add('boundschange', function () {
            if (isAdminProgrammaticMove) return;
            clearTimeout(adminBoundsDebounce);
            adminBoundsDebounce = setTimeout(loadAdminPointsByBounds, 300);
        });

        loadAdminPoints(currentAdminCity, "", true);
        setTimeout(() => { try { adminMap.container.fitToViewport(); } catch(e){} }, 350);
    };

    document.getElementById('adminFivepostCustomMap').addEventListener("click", function (e) {
        const btn = e.target.closest(".bf-balloon-select-btn");
        if (btn) {
            const pid = btn.getAttribute("data-point-id");
            const pt = currentAdminPoints.find(p => String(p.id) === String(pid));
            if (pt) handleSelectAdminPoint(pt);
        }
    });

    let adminSearchDebounceTimer = null;
    document.getElementById('adminFivepostCitySearch').addEventListener("input", function () {
        const val = this.value.trim();
        document.getElementById('adminFivepostCityClear').style.display = val ? "block" : "none";
        clearTimeout(adminSearchDebounceTimer);
        adminSearchDebounceTimer = setTimeout(() => loadAdminPoints("", val, false), 400);
    });

    document.getElementById('adminFivepostCityClear').addEventListener("click", function () {
        document.getElementById('adminFivepostCitySearch').value = "";
        this.style.display = "none";
        loadAdminPoints(currentAdminCity, "", false);
    });

    document.getElementById('adminFivepostChangeBtn').addEventListener("click", function () {
        document.getElementById('adminFivepostSelectedCard').style.display = "none";
        document.getElementById('adminFivepostMapWrapper').style.display = "block";
        if (adminMap) setTimeout(() => { try { adminMap.container.fitToViewport(); } catch(e){} }, 100);
    });

});
</script>

<?php
$yandexApiKey = env_get('YANDEX_MAPS_API_KEY') ?: '';
if ($yandexApiKey):
?>
<script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=<?= htmlspecialchars($yandexApiKey) ?>" defer></script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
