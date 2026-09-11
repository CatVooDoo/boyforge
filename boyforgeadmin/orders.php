<?php
declare(strict_types=1);

$pageTitle = 'Заказы и доставка 5Post';
require_once __DIR__ . '/includes/header.php';

// Получаем все заказы из базы данных
$stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOrders = count($orders);
$paidOrders = 0;
$totalRevenue = 0.0;
$fivepostSynced = 0;

foreach ($orders as $o) {
    if ($o['payment_status'] === 'paid') {
        $paidOrders++;
        $totalRevenue += (float)$o['price'];
    }
    if (!empty($o['fivepost_barcode'])) {
        $fivepostSynced++;
    }
}
?>

<div class="page-head">
  <div>
    <h1 class="page-title">Заказы и логистика 5Post</h1>
    <div class="page-subtitle">История заказов, онлайн-оплат и трек-номеров отправлений 5Post C2C</div>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon-wrapper">
      <?= renderSvgIcon('box') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value"><?= $totalOrders ?></span>
      <span class="stat-label">Всего заказов</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrapper" style="color: var(--accent-success);">
      <?= renderSvgIcon('eye') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value"><?= $paidOrders ?></span>
      <span class="stat-label">Оплачено</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrapper" style="color: #6366f1;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
    </div>
    <div class="stat-info">
      <span class="stat-value"><?= $fivepostSynced ?></span>
      <span class="stat-label">5Post Треков</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <span class="stat-value"><?= number_format($totalRevenue, 0, '', ' ') ?> ₽</span>
      <span class="stat-label">Выручка</span>
    </div>
  </div>
</div>

<div class="content-card">
  <?php if (empty($orders)): ?>
    <div class="empty-state">
      <div class="empty-icon"><?= renderSvgIcon('box') ?></div>
      <div class="empty-title">Заказов пока нет</div>
      <div class="empty-desc">Новые заказы после онлайн-оплаты будут автоматически появляться здесь.</div>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>ID Заказа</th>
            <th>Дата</th>
            <th>Покупатель</th>
            <th>Товар / Размер</th>
            <th>Сумма</th>
            <th>Пункт 5Post</th>
            <th>Штрихкод 5Post</th>
            <th>5Post Интеграция</th>
            <th>Оплата</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $ord): ?>
            <tr>
              <td>
                <strong style="font-family: monospace; font-size: 13px;"><?= htmlspecialchars($ord['order_id']) ?></strong>
              </td>
              <td style="font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                <?= date('d.m.Y H:i', strtotime($ord['created_at'])) ?>
              </td>
              <td>
                <div style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($ord['fio']) ?></div>
                <div style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($ord['phone']) ?></div>
                <?php if (!empty($ord['tg_username'])): ?>
                  <div style="font-size: 11px; color: #3b82f6;"><?= htmlspecialchars($ord['tg_username']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight: 500;"><?= htmlspecialchars($ord['product_name']) ?></div>
                <div style="font-size: 12px; color: var(--text-muted);">
                  <?= htmlspecialchars($ord['gender'] ?: 'Мужской') ?>, Размер: <strong><?= htmlspecialchars($ord['size'] ?: 'M') ?></strong>
                </div>
              </td>
              <td style="white-space: nowrap; font-weight: 600;">
                <?= number_format((float)$ord['price'], 0, '', ' ') ?> ₽
              </td>
              <td style="max-width: 260px;">
                <div style="font-size: 12px; font-weight: 500; line-height: 1.3;">
                  <?= htmlspecialchars($ord['fivepost_point_address'] ?: '—') ?>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                  <?= ($ord['fivepost_point_type'] === 'POSTAMAT' ? 'Постамат 5Post' : 'Касса «Пятёрочка»') ?>
                  <?= !empty($ord['fivepost_point_id']) ? '<span style="font-family:monospace; font-size:10px;">[' . substr($ord['fivepost_point_id'], 0, 8) . '…]</span>' : '' ?>
                </div>
              </td>
              <td style="white-space: nowrap;">
                <?php if (!empty($ord['fivepost_barcode'])): ?>
                  <span class="badge" style="background:#ecfdf5; color:#065f46; font-family: monospace; font-size: 12px; padding: 4px 8px; border: 1px solid #a7f3d0;">
                    <?= htmlspecialchars($ord['fivepost_barcode']) ?>
                  </span>
                <?php else: ?>
                  <span style="color: var(--text-muted); font-size: 12px;">—</span>
                <?php endif; ?>
              </td>
              <td style="white-space: nowrap;">
                <?php 
                  $fpStatus = $ord['fivepost_status'] ?? 'NONE';
                  $fpCode = (int)($ord['fivepost_http_code'] ?? 0);
                  
                  if ($fpStatus === 'CREATED'): ?>
                    <span class="badge" style="background:#d1fae5; color:#065f46; font-size:11px; padding:3px 6px;">✓ Зарегистрирован</span>
                <?php elseif ($fpStatus === 'PENDING_REGISTRATION'): ?>
                    <span class="badge" style="background:#fef3c7; color:#92400e; font-size:11px; padding:3px 6px;">⚠ Ожидает ключ (401)</span>
                <?php else: ?>
                    <span class="badge" style="background:#f3f4f6; color:#6b7280; font-size:11px; padding:3px 6px;"><?= htmlspecialchars($fpStatus) ?></span>
                <?php endif; ?>

                <div style="margin-top: 6px;">
                  <button type="button" class="btn btn-secondary btn-sm" style="font-size:11px; padding:2px 8px;" onclick="openOrderModal(<?= (int)$ord['id'] ?>)">
                    Лог 5Post
                  </button>
                </div>

                <script type="application/json" id="order-json-<?= (int)$ord['id'] ?>">
                  <?= json_encode([
                    'order_id'       => $ord['order_id'],
                    'created_at'     => $ord['created_at'],
                    'fio'            => $ord['fio'],
                    'phone'          => $ord['phone'],
                    'address'        => $ord['fivepost_point_address'],
                    'barcode'        => $ord['fivepost_barcode'],
                    'fivepost_oid'   => $ord['fivepost_order_id'],
                    'http_code'      => $ord['fivepost_http_code'],
                    'status'         => $ord['fivepost_status'],
                    'payload'        => $ord['fivepost_request_payload'],
                    'raw_response'   => $ord['fivepost_raw_response']
                  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>
                </script>
              </td>
              <td>
                <?php if ($ord['payment_status'] === 'paid'): ?>
                  <span class="badge badge-success">Оплачен</span>
                  <?php if (!empty($ord['payment_transaction_id'])): ?>
                    <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">#<?= htmlspecialchars($ord['payment_transaction_id']) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge badge-warning">Не оплачен</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Модальное окно деталей заказа и лога 5Post -->
<div class="modal-overlay" id="orderModalOverlay" style="align-items: flex-start; padding-top: 40px; overflow-y: auto;">
  <div class="modal-box" style="max-width: 780px; width: 95%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px; border-bottom:1px solid var(--border-color); padding-bottom:12px;">
      <div>
        <h3 class="modal-title" id="modalOrderId" style="margin:0;">Заказ #</h3>
        <div style="font-size:12px; color:var(--text-muted);" id="modalCustomerInfo"></div>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeOrderModal()">✕ Закрыть</button>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom: 16px;">
      <div style="background:#f9fafb; padding:10px 14px; border-radius:8px; border:1px solid var(--border-color);">
        <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase;">Штрихкод / Трек 5Post</div>
        <div id="modalBarcode" style="font-family:monospace; font-weight:700; font-size:14px; margin-top:2px;">—</div>
      </div>
      <div style="background:#f9fafb; padding:10px 14px; border-radius:8px; border:1px solid var(--border-color);">
        <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase;">HTTP Статус шлюза 5Post</div>
        <div id="modalHttpStatus" style="font-weight:700; font-size:14px; margin-top:2px;">—</div>
      </div>
    </div>

    <div style="margin-bottom: 16px;">
      <div style="font-size:12px; font-weight:700; margin-bottom:6px; color:#18181b;">
        Отправленный JSON-пакет в 5Post (Раздел 18.2 C2C):
      </div>
      <pre id="modalPayload" style="margin:0; font-family:monospace; font-size:11px; line-height:1.4; color:#27272a; background:#f4f4f5; border:1px solid #e4e4e7; border-radius:6px; padding:10px 12px; max-height:220px; overflow-y:auto; white-space:pre-wrap;"></pre>
    </div>

    <div style="margin-bottom: 20px;">
      <div style="font-size:12px; font-weight:700; margin-bottom:6px; color:#18181b;">
        Точный сырой ответ сервера 5Post:
      </div>
      <pre id="modalResponse" style="margin:0; font-family:monospace; font-size:11px; line-height:1.4; color:#27272a; background:#f4f4f5; border:1px solid #e4e4e7; border-radius:6px; padding:10px 12px; max-height:180px; overflow-y:auto; white-space:pre-wrap;"></pre>
    </div>

    <div class="modal-actions" style="display:flex; justify-content:space-between; align-items:center;">
      <a href="logs.php" class="btn btn-secondary btn-sm" target="_blank">
        Перейти в общий журнал логов 5Post →
      </a>
      <button type="button" class="btn btn-primary btn-sm" onclick="closeOrderModal()">Понятно</button>
    </div>
  </div>
</div>

<script>
function openOrderModal(id) {
  const jsonEl = document.getElementById('order-json-' + id);
  if (!jsonEl) return;
  
  try {
    const data = JSON.parse(jsonEl.textContent.trim());
    document.getElementById('modalOrderId').innerText = 'Заказ ' + data.order_id;
    document.getElementById('modalCustomerInfo').innerText = (data.fio || '') + ' • ' + (data.phone || '') + ' • ' + (data.address || '');
    document.getElementById('modalBarcode').innerText = data.barcode || '—';

    const httpEl = document.getElementById('modalHttpStatus');
    if (data.http_code == 200) {
      httpEl.innerHTML = '<span style="color:#10b981;">200 OK (Успешно зарегистрирован)</span>';
    } else if (data.http_code == 401) {
      httpEl.innerHTML = '<span style="color:#f59e0b;">401 Unauthorized (Ключ не активирован X5)</span>';
    } else {
      httpEl.innerHTML = '<span style="color:#6b7280;">' + (data.http_code ? ('HTTP ' + data.http_code) : '—') + '</span>';
    }

    // Форматирование полезной нагрузки
    let payloadFormatted = data.payload;
    try {
      if (typeof payloadFormatted === 'string' && payloadFormatted.trim().startsWith('{')) {
        payloadFormatted = JSON.stringify(JSON.parse(payloadFormatted), null, 2);
      }
    } catch(e){}
    document.getElementById('modalPayload').innerText = payloadFormatted || 'Нет данных запроса (заказ создан до оплаты)';

    // Форматирование ответа
    let responseFormatted = data.raw_response;
    try {
      if (typeof responseFormatted === 'string' && responseFormatted.trim().startsWith('{')) {
        responseFormatted = JSON.stringify(JSON.parse(responseFormatted), null, 2);
      }
    } catch(e){}
    document.getElementById('modalResponse').innerText = responseFormatted || 'Нет ответа (заказ еще не отправлялся в API)';

    document.getElementById('orderModalOverlay').classList.add('active');
  } catch(e) {
    console.error(e);
  }
}

function closeOrderModal() {
  document.getElementById('orderModalOverlay').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeOrderModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
