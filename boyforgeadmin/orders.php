<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireAdminAuth();

$pageTitle = 'Управление заказами (CRM)';
$csrfToken = getCsrfToken();

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

require __DIR__ . '/includes/header.php';

function getPaymentBadge($status, $markCode) {
    if ($markCode === '__CANCELLED__') return '<span class="status-badge badge-cancelled">Отменён</span>';
    if ($status === 'paid') return '<span class="status-badge badge-paid">Оплачен</span>';
    if ($status === 'manual') return '<span class="status-badge badge-manual">Ручной</span>';
    if ($status === 'refunded') return '<span class="status-badge badge-cancelled">Возврат</span>';
    return '<span class="status-badge badge-pending">Ожидает</span>';
}

function getDeliveryBadge($status) {
    if ($status === 'SENT') return '<span class="status-badge badge-paid">5Post: Создан</span>';
    if ($status === 'ERROR') return '<span class="status-badge badge-cancelled">5Post: Ошибка</span>';
    return '<span class="status-badge badge-pending">Нет доставки</span>';
}
?>

<style>
/* Специфичные стили для CRM */
.crm-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
.status-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.badge-paid { background-color: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
.badge-pending { background-color: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); }
.badge-cancelled { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
.badge-manual { background-color: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }

.order-row { cursor: pointer; transition: background-color 0.2s; }
.order-row:hover td { background-color: #fafaf8; }
.order-row.cancelled { opacity: 0.6; }

.modal-overlay { display:none; opacity: 1 !important; pointer-events: auto !important; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; padding: 20px; }
.modal-content { background:#fff; padding:32px; border-radius:12px; width:100%; max-width: 640px; max-height: 90vh; overflow-y: auto; box-shadow:0 10px 25px rgba(0,0,0,0.1); position: relative; }
.modal-close { position:absolute; top:24px; right:24px; cursor:pointer; color:#999; transition:0.2s; }
.modal-close:hover { color:#000; }

.modal-section { margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color); }
.modal-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.modal-section-title { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 12px; font-weight: 600; }
.info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.info-item .lbl { font-size: 11px; color: var(--text-muted); margin-bottom: 4px; }
.info-item .val { font-size: 14px; color: #000; font-weight: 500; word-break: break-all; }

#btnTgSettings .icon { transition: transform 0.6s ease-out; }
#btnTgSettings:hover .icon { transform: rotate(720deg); }

/* 5Post Map Styles */
.fivepost-selected-card{ display:flex; align-items:center; gap:12px; padding:12px 14px; background:#f0fdf4; border:1.5px solid #86efac; border-radius:10px; animation:fivepostFadeIn .25s ease; }
.fivepost-selected-icon{ width:36px; height:36px; border-radius:50%; background:#5EBC52; color:#ffffff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.fivepost-selected-details{ flex-grow:1; display:flex; flex-direction:column; gap:2px; min-width:0; }
.fivepost-selected-header{ display:flex; align-items:center; gap:6px; }
.fivepost-selected-badge{ background:#16a34a; color:#ffffff; font-size:10px; font-weight:700; text-transform:uppercase; padding:2px 6px; border-radius:4px; letter-spacing:0.4px; }
.fivepost-selected-name{ font-size:13px; font-weight:700; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.fivepost-selected-address{ font-size:12px; font-weight:600; color:#1f2937; line-height:1.35; }
.fivepost-selected-extra{ font-size:11px; color:#6b7280; line-height:1.3; }
.fivepost-change-btn{ background:#ffffff; border:1px solid #d1d5db; border-radius:6px; padding:6px 12px; font-size:11px; font-weight:700; color:#374151; cursor:pointer; transition:all .2s; white-space:nowrap; flex-shrink:0; outline: none; }
.fivepost-change-btn:hover{ background:#5EBC52; border-color:#5EBC52; color:#ffffff; }

.fivepost-map-wrapper{ position:relative; width:100%; border-radius:14px; overflow:hidden; border:1px solid #e5e7eb; background:#f9fafb; outline: none; }
.bf-city-search-wrap{ padding:12px; background:#ffffff; border-bottom:1px solid #eeeeee; display:flex; flex-direction:column; gap:8px; outline: none; }
.bf-city-search-box{ position:relative; display:flex; align-items:center; outline: none; }
.bf-search-ico{ position:absolute; left:12px; color:#9ca3af; pointer-events:none; }
.bf-city-search-input{ padding-left:36px !important; padding-right:32px !important; font-size:12px !important; height:38px !important; border-radius:8px !important; border:1px solid #e5e7eb !important; width: 100%; box-sizing: border-box; outline: none; }
.bf-city-search-input:focus { outline: none !important; border-color: #5EBC52 !important; box-shadow: none !important; }
.bf-clear-btn{ position:absolute; right:8px; background:none; border:none; color:#9ca3af; font-size:18px; line-height:1; cursor:pointer; padding:4px 8px; border-radius:4px; outline: none; }
.bf-clear-btn:hover{ color:#111; }

.bf-custom-map{ width:100%; height:340px; position:relative; background:#e5e7eb; outline: none; }
.bf-custom-map * { outline: none !important; }

.bf-map-pin{ position:relative; width:36px; height:44px; transform:translate(-18px, -44px); cursor:pointer; transition:transform .2s cubic-bezier(0.175, 0.885, 0.32, 1.275); outline: none; }
.bf-map-pin:hover{ transform:translate(-18px, -48px) scale(1.15); z-index:9999 !important; }
.bf-pin-body{ width:36px; height:36px; background:#ffffff; border:2px solid #ffffff; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,0.2); overflow:hidden; outline: none; }
.bf-pin-img{ display:block; width:100%; height:100%; object-fit:contain; pointer-events:none; }
.bf-pin-tail{ position:absolute; bottom:2px; left:50%; transform:translateX(-50%); width:0; height:0; border-left:5px solid transparent; border-right:5px solid transparent; border-top:8px solid #ee2d24; outline: none; }

/* Балун точки на карте */
.bf-balloon{ font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color:#111111; padding:6px 2px; min-width:230px; max-width:270px; outline: none; border: none; }
.bf-balloon-header{ display:flex; align-items:center; gap:6px; margin-bottom:6px; flex-wrap:wrap; outline: none; }
.bf-balloon-badge{ background:#10b981; color:#ffffff; font-size:10px; font-weight:800; padding:2px 7px; border-radius:4px; text-transform:uppercase; letter-spacing:0.4px; outline: none; }
.bf-balloon-name{ font-size:12px; font-weight:700; color:#111827; outline: none; }
.bf-balloon-address{ font-size:13px; font-weight:600; color:#1f2937; line-height:1.4; margin-bottom:4px; outline: none; }
.bf-balloon-extra{ font-size:11px; color:#6b7280; line-height:1.4; margin-bottom:4px; outline: none; }
.bf-balloon-extra:last-of-type{ margin-bottom:10px; }
.bf-balloon-select-btn{ width:100%; background:#000000; color:#ffffff; border:none; padding:9px 12px; border-radius:8px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; cursor:pointer; transition:all .2s; outline: none; }
.bf-balloon-select-btn:hover{ background:#333333; transform:translateY(-1px); }

.fivepost-map-loader{ position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); z-index:1000; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#6b7280; font-size:13px; font-weight:600; outline: none; }
.fivepost-spinner{ width:32px; height:32px; border:3px solid #e5e7eb; border-top-color:#10b981; border-radius:50%; animation:spin 1s linear infinite; margin-bottom:12px; outline: none; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>

<div class="admin-main">
  <div class="crm-header-flex">
    <div>
        <h1 class="admin-title" style="margin-bottom: 4px;">Управление заказами</h1>
        <div class="page-subtitle">Всего заказов: <?= count($orders) ?></div>
    </div>
    <div style="display: flex; align-items: center; gap: 16px;">
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

  <div class="filter-card">
    <div class="search-box">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" id="orderSearch" class="search-input" placeholder="Поиск по номеру, ФИО или телефону...">
    </div>
    <div class="filter-group">
        <select id="statusFilter" class="filter-select">
            <option value="all">Все статусы</option>
            <option value="paid">Оплаченные</option>
            <option value="manual">Ручные</option>
            <option value="pending">Ожидают оплаты</option>
            <option value="cancelled">Отмененные</option>
        </select>
    </div>
  </div>

  <div class="table-card">
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID Заказа</th>
            <th>Дата</th>
            <th>Покупатель</th>
            <th>Товар</th>
            <th>Сумма</th>
            <th>Статусы</th>
          </tr>
        </thead>
        <tbody id="ordersTableBody">
          <?php foreach ($orders as $order): ?>
            <?php 
                $isCancelled = ($order['mark_code'] === '__CANCELLED__'); 
                $statusClass = $isCancelled ? 'cancelled' : '';
                // Serialize order for modal
                $orderJson = htmlspecialchars(json_encode($order, JSON_UNESCAPED_UNICODE));
            ?>
            <tr class="order-row <?= $statusClass ?>" data-order='<?= $orderJson ?>'>
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($order['order_id']) ?></div>
              </td>
              <td style="white-space: nowrap; font-size:12px; color:#6b7280;">
                <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
              </td>
              <td>
                <div style="font-weight:500; color:#000;"><?= htmlspecialchars($order['fio'] ?: 'Не указан') ?></div>
                <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($order['phone']) ?></div>
              </td>
              <td>
                <div style="font-weight:500; color:#000;"><?= htmlspecialchars($order['product_name']) ?></div>
                <div style="font-size:12px; color:#6b7280;">Размер: <?= htmlspecialchars($order['size'] ?: '—') ?></div>
              </td>
              <td class="price-cell">
                <?= number_format((float)$order['price'], 0, '', ' ') ?> ₽
              </td>
              <td>
                <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                    <?= getPaymentBadge($order['payment_status'], $order['mark_code']) ?>
                    <?php if ($order['payment_status'] === 'paid' || $order['payment_status'] === 'manual' || !empty($order['fivepost_order_id'])): ?>
                        <?= getDeliveryBadge($order['fivepost_status']) ?>
                    <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($orders)): ?>
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">Заказов пока нет</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Модальное окно просмотра заказа -->
<div id="orderDetailModal" class="modal-overlay">
    <div class="modal-content">
        <svg id="closeDetailModal" class="modal-close icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        
        <div style="margin-bottom: 24px;">
            <h2 id="mdlOrderId" style="font-size: 20px; font-weight: 700; margin-bottom: 4px;">Заказ #...</h2>
            <div id="mdlOrderDate" style="font-size: 12px; color: var(--text-muted);">...</div>
        </div>

        <div class="modal-section">
            <div class="modal-section-title">Данные клиента</div>
            <div class="info-grid">
                <div class="info-item"><div class="lbl">ФИО</div><div class="val" id="mdlFio"></div></div>
                <div class="info-item"><div class="lbl">Телефон</div><div class="val" id="mdlPhone"></div></div>
                <div class="info-item"><div class="lbl">Email</div><div class="val" id="mdlEmail"></div></div>
                <div class="info-item"><div class="lbl">Telegram</div><div class="val" id="mdlTg"></div></div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-section-title">Товар и оплата</div>
            <div class="info-grid">
                <div class="info-item"><div class="lbl">Название</div><div class="val" id="mdlProduct"></div></div>
                <div class="info-item"><div class="lbl">Сумма</div><div class="val" id="mdlPrice"></div></div>
                <div class="info-item"><div class="lbl">Размер</div><div class="val" id="mdlSize"></div></div>
                <div class="info-item"><div class="lbl">Статус заказа</div><div class="val" id="mdlPaymentStatus"></div></div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-section-title">Доставка 5Post</div>
            <div class="info-grid" style="margin-bottom: 16px;">
                <div class="info-item" style="grid-column: span 2;"><div class="lbl">Адрес ПВЗ</div><div class="val" id="mdl5pAddress"></div></div>
                <div class="info-item"><div class="lbl">ID Точки</div><div class="val" id="mdl5pPointId"></div></div>
                <div class="info-item"><div class="lbl">ID Заказа (5Post)</div><div class="val" id="mdl5pOrderId"></div></div>
                <div class="info-item"><div class="lbl">Штрихкод</div><div class="val" id="mdl5pBarcode" style="color: #10b981;"></div></div>
                <div class="info-item"><div class="lbl">Статус отправки</div><div class="val" id="mdl5pStatus"></div></div>
            </div>
        </div>

        <div class="modal-section" id="mdlActionsSection">
            <div class="modal-section-title">Действия</div>
            
            <div id="receiptContainer" style="display:none; background: #fafaf8; padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 16px;">
                <div style="font-size: 12px; font-weight: 600; margin-bottom: 8px;">Маркировка и закрывающий чек</div>
                <div style="display:flex; gap: 8px;">
                    <input type="text" id="mdlMarkCode" class="form-input" placeholder="Пикните сканером код маркировки" style="font-family: monospace; font-size:12px; flex: 1;">
                    <button id="btnSendReceipt" class="btn btn-primary btn-sm">Отправить чек</button>
                </div>
            </div>

            <div style="display:flex; gap: 12px; flex-wrap: wrap;">
                <button id="btnManual5Post" class="btn btn-secondary btn-sm" style="display:none; color:#10b981; border-color:#10b981;">
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
        
        <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 24px;">Создание ручного заказа 5Post</h2>
        
        <form id="createManualForm">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">ФИО клиента *</label>
                    <input type="text" id="crFio" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Телефон *</label>
                    <input type="text" id="crPhone" class="form-input" placeholder="+7..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="crEmail" class="form-input">
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
                <div class="form-group">
                    <label class="form-label">Сумма (₽) *</label>
                    <input type="number" id="crPrice" class="form-input" value="0" required>
                </div>
            </div>
            <div class="form-group" style="margin-top: 10px;">
                <label class="form-label">Выбор точки 5Post на карте *</label>
                
                <!-- Скрытые поля для данных выбранной точки -->
                <input type="hidden" id="crPointId" required>
                <input type="hidden" id="crAddress" required>
                <input type="hidden" id="crPointType">
                <input type="hidden" id="crPointName">
                <input type="hidden" id="crPointDetails">

                <!-- Карточка уже выбранного пункта выдачи -->
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

                <!-- Блок карты и поиска по городам -->
                <div class="fivepost-map-wrapper" id="adminFivepostMapWrapper">
                    <!-- Поиск по городам и улицам -->
                    <div class="bf-city-search-wrap">
                        <div class="bf-city-search-box">
                            <svg class="bf-search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" id="adminFivepostCitySearch" class="ozon-input bf-city-search-input" placeholder="Поиск по городу или улице (например, Пенза, Победы…)" autocomplete="off">
                            <button type="button" id="adminFivepostCityClear" class="bf-clear-btn" style="display:none;" aria-label="Очистить">&times;</button>
                        </div>
                    </div>

                    <!-- Контейнер интерактивной Яндекс.Карты -->
                    <div id="adminFivepostCustomMap" class="bf-custom-map" style="width: 100%; height: 350px; background: #f3f4f6; border-radius: 8px; overflow: hidden; margin-bottom: 12px; border: 1px solid var(--border-color); position:relative;">
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
        <h3 style="margin-top:0; margin-bottom:16px; font-size:18px;">Секреты Telegram</h3>
        <p style="font-size:13px; color:#6b7280; margin-bottom: 16px;">Укажите данные, чтобы уведомления приходили в нужный вам чат/группу. При пустых полях будут использоваться дефолтные из .env.</p>
        
        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label">Telegram Bot Token (HTTP API):</label>
            <input type="text" id="tgBotTokenInput" class="form-input" value="<?= htmlspecialchars($tgToken) ?>" placeholder="Например: 123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11">
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label">Telegram Chat ID (ID чата или канала):</label>
            <input type="text" id="tgChatIdInput" class="form-input" value="<?= htmlspecialchars($tgChatId) ?>" placeholder="Например: -1003895656779">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Поиск и фильтрация
    const searchInput = document.getElementById('orderSearch');
    const statusFilter = document.getElementById('statusFilter');
    const rows = document.querySelectorAll('.order-row');

    function filterTable() {
        const query = searchInput.value.toLowerCase();
        const status = statusFilter.value;

        rows.forEach(row => {
            const data = JSON.parse(row.getAttribute('data-order'));
            let textMatch = true;
            if (query) {
                const text = `${data.order_id} ${data.fio} ${data.phone}`.toLowerCase();
                textMatch = text.includes(query);
            }

            let statusMatch = true;
            const isCancelled = data.mark_code === '__CANCELLED__';
            if (status === 'paid') statusMatch = data.payment_status === 'paid' && !isCancelled;
            if (status === 'manual') statusMatch = data.payment_status === 'manual' && !isCancelled;
            if (status === 'pending') statusMatch = data.payment_status !== 'paid' && data.payment_status !== 'manual' && !isCancelled;
            if (status === 'cancelled') statusMatch = isCancelled || data.payment_status === 'refunded';

            row.style.display = (textMatch && statusMatch) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterTable);
    statusFilter.addEventListener('change', filterTable);

    // Модальное окно заказа
    const detailModal = document.getElementById('orderDetailModal');
    let currentOrderData = null;

    rows.forEach(row => {
        row.addEventListener('click', function() {
            const data = JSON.parse(this.getAttribute('data-order'));
            currentOrderData = data;
            
            document.getElementById('mdlOrderId').textContent = 'Заказ ' + data.order_id;
            document.getElementById('mdlOrderDate').textContent = data.created_at;
            document.getElementById('mdlFio').textContent = data.fio || '—';
            document.getElementById('mdlPhone').textContent = data.phone || '—';
            document.getElementById('mdlEmail').textContent = data.email || '—';
            document.getElementById('mdlTg').textContent = data.tg_username || '—';
            document.getElementById('mdlProduct').textContent = data.product_name || '—';
            document.getElementById('mdlPrice').textContent = data.price + ' ₽';
            document.getElementById('mdlSize').textContent = data.size || '—';
            
            const isCancelled = data.mark_code === '__CANCELLED__';
            
            let payStr = data.payment_status;
            if (isCancelled) payStr = 'Отменён';
            else if (payStr === 'paid') payStr = 'Оплачен (Yookassa)';
            else if (payStr === 'manual') payStr = 'Создан вручную';
            else payStr = 'Не оплачен';
            
            document.getElementById('mdlPaymentStatus').textContent = payStr;

            document.getElementById('mdl5pAddress').textContent = data.fivepost_point_address || '—';
            document.getElementById('mdl5pPointId').textContent = data.fivepost_point_id || '—';
            document.getElementById('mdl5pOrderId').textContent = data.fivepost_order_id || '—';
            document.getElementById('mdl5pBarcode').textContent = data.fivepost_barcode || '—';
            document.getElementById('mdl5pStatus').textContent = data.fivepost_status || '—';

            // Кнопки и формы действий
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
                receiptCont.innerHTML = `<div style="color:#10b981; font-weight:600;">Чек пробит</div><div style="font-size:11px; margin-top:4px;">${data.mark_code}</div>`;
            } else if (!isCancelled && data.payment_status === 'paid') {
                receiptCont.style.display = 'block';
                receiptCont.innerHTML = `
                    <div style="font-size: 12px; font-weight: 600; margin-bottom: 8px;">Маркировка и закрывающий чек</div>
                    <div style="display:flex; gap: 8px;">
                        <input type="text" id="mdlMarkCode" class="form-input" placeholder="Пикните сканером" style="font-family: monospace; font-size:12px; flex: 1;">
                        <button id="btnSendReceipt" class="btn btn-primary btn-sm">Отправить чек</button>
                    </div>
                `;
                document.getElementById('btnSendReceipt').addEventListener('click', handleSendReceipt);
            }

            if (!isCancelled && data.payment_status !== 'manual') {
                btnCancel.style.display = 'block';
            }

            detailModal.style.display = 'flex';
        });
    });

    document.getElementById('closeDetailModal').addEventListener('click', () => detailModal.style.display = 'none');

    function handleSendReceipt() {
        const markCode = document.getElementById('mdlMarkCode').value.trim();
        if (!markCode) return alert('Введите код маркировки');
        
        const btn = document.getElementById('btnSendReceipt');
        btn.disabled = true;
        btn.textContent = '...';
        
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

    // Логика каскадных дропдаунов
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
                
                // Автовыбор если пол один
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
            
            // Автовыбор если размер один
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
    
    // TG Settings
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
            .catch(err => alert('Сетевая ошибка'))
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
            .catch(err => alert('Сетевая ошибка'))
            .finally(() => { this.textContent = originalText; this.disabled = false; });
        });
    }

    // Yandex Maps Logic (Ported from checkout.js)
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

    // События карты
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
