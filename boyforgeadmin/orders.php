<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/fivepost_sync.php';
require_once __DIR__ . '/includes/orders_helper.php';

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

// Товары для создания ручного заказа
$stmtProducts = $pdo->query("SELECT id, name, sizes, price_numeric FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
$allProductsRaw = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
$productsData = [];
foreach ($allProductsRaw as $p) {
    $p['sizes'] = json_decode((string)($p['sizes'] ?? ''), true) ?? [];
    $productsData[] = $p;
}

// Настройки Telegram
$settingsFile = dirname(__DIR__) . '/includes/settings.json';
$tgEnabled = true;
$tgToken = '';
$tgChatId = '';
if (file_exists($settingsFile)) {
    $settings = json_decode((string)@file_get_contents($settingsFile), true) ?: [];
    if (isset($settings['tg_notifications_enabled'])) {
        $tgEnabled = (bool)$settings['tg_notifications_enabled'];
    }
    $tgToken = $settings['tg_bot_token'] ?? '';
    $tgChatId = $settings['tg_chat_id'] ?? '';
}

// Подсчет метрик KPI и группировка стадий
$kpi = calculateOrdersKpi($orders);
$stagesData = $kpi['stagesData'];

$cacheInfoFile = dirname(__DIR__) . '/logs/5post_sync_cache.json';
$lastSyncTs = 0;
if (file_exists($cacheInfoFile)) {
    $cData = json_decode((string)@file_get_contents($cacheInfoFile), true);
    $lastSyncTs = (int)($cData['last_sync'] ?? 0);
}
$syncDiffMinutes = $lastSyncTs > 0 ? max(0, (int)round((time() - $lastSyncTs) / 60)) : null;

require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="assets/css/orders.css">

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

    <!-- 1. KPI Статистика -->
    <?php require __DIR__ . '/includes/orders/kpi_bar.php'; ?>

    <!-- 2. Панель управления, переключатель вида, поиск и фильтры -->
    <?php require __DIR__ . '/includes/orders/control_bar.php'; ?>

    <!-- 3. Табличный реестр заказов -->
    <?php require __DIR__ . '/includes/orders/table_view.php'; ?>

    <!-- 4. Канбан-доска стадий -->
    <?php require __DIR__ . '/includes/orders/kanban_view.php'; ?>

  </div>
</div>

<!-- 5. Модальные окна -->
<?php require __DIR__ . '/includes/orders/modal_detail.php'; ?>
<?php require __DIR__ . '/includes/orders/modal_create.php'; ?>
<?php require __DIR__ . '/includes/orders/modal_tg.php'; ?>

<!-- Всплывающий тост для копирования -->
<div id="copyToast">Скопировано в буфер</div>

<!-- Передача данных товаров в JS -->
<script>
window.adminProductsData = <?= json_encode($productsData, JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- Скрипты CRM и карты -->
<script src="assets/js/orders.js"></script>
<script src="assets/js/orders_map.js"></script>

<?php
$yandexApiKey = env_get('YANDEX_MAPS_API_KEY') ?: '';
if ($yandexApiKey):
?>
<script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=<?= htmlspecialchars($yandexApiKey) ?>" defer></script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
