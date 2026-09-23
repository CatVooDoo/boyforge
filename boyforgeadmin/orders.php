<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireAdminAuth();

$pageTitle = 'Заказы (Закрывающие чеки)';
$csrfToken = getCsrfToken();

// Получаем только оплаченные заказы
$stmt = $pdo->query("
    SELECT * FROM orders 
    WHERE payment_status = 'paid' 
    ORDER BY created_at DESC 
    LIMIT 200
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>

<div class="admin-main">
  <div class="admin-header-flex" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
    <h1 class="admin-title">Управление заказами и чеками</h1>
  </div>

  <div class="admin-card">
    <div style="padding: 20px;">
        <p style="margin-bottom: 15px; color: #4b5563;">
            Здесь отображаются все оплаченные заказы. Для товаров, подлежащих маркировке (одежда), 
            перед передачей в 5Post необходимо пробить закрывающий чек («Полный расчет») с указанием кода Честного ЗНАКа.
        </p>
    </div>

    <?php if (empty($orders)): ?>
      <div style="padding: 40px; text-align: center; color: #6b7280;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 16px; opacity: 0.5;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <h3>Оплаченных заказов пока нет</h3>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Дата и Заказ</th>
              <th>Покупатель</th>
              <th>Товар</th>
              <th>Доставка 5Post</th>
              <th>Закрывающий чек (Маркировка)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
              <tr id="order-row-<?= htmlspecialchars($order['order_id']) ?>">
                <td>
                  <div style="font-weight:600; margin-bottom:4px;"><?= htmlspecialchars($order['order_id']) ?></div>
                  <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($order['created_at']) ?></div>
                </td>
                <td>
                  <div><?= htmlspecialchars($order['fio']) ?></div>
                  <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($order['phone']) ?></div>
                </td>
                <td>
                  <div style="font-weight:500;"><?= htmlspecialchars($order['product_name']) ?></div>
                  <div style="font-size:12px; color:#6b7280;">
                    <?= htmlspecialchars($order['size']) ?> | <?= number_format((float)$order['price'], 0, '', ' ') ?> ₽
                  </div>
                </td>
                <td>
                  <?php if (!empty($order['fivepost_barcode'])): ?>
                    <div style="font-weight:600; color:#10b981;"><?= htmlspecialchars($order['fivepost_barcode']) ?></div>
                    <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($order['fivepost_point_address']) ?></div>
                  <?php else: ?>
                    <span style="color:#ef4444; font-size:13px;">Ошибка 5Post</span>
                  <?php endif; ?>
                </td>
                <td style="min-width: 320px;">
                  <?php if ($order['receipt_sent'] == 1): ?>
                    <div style="display:inline-flex; align-items:center; gap:6px; color:#10b981; font-size:14px; font-weight:500;">
                        <?= renderSvgIcon('check', 'icon') ?> Чек пробит
                    </div>
                    <?php if (!empty($order['mark_code'])): ?>
                        <div style="font-size:11px; color:#6b7280; margin-top:4px; word-break: break-all; max-width: 250px;">
                            <?= htmlspecialchars($order['mark_code']) ?>
                        </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="receipt-form-container">
                        <input type="text" class="form-input mark-input" placeholder="Пикните сканером код маркировки" style="margin-bottom: 8px; font-family: monospace; font-size:12px;" data-order="<?= htmlspecialchars($order['order_id']) ?>">
                        <button type="button" class="btn-primary btn-sm send-receipt-btn" data-order="<?= htmlspecialchars($order['order_id']) ?>" style="width: 100%;">
                            Отправить чек
                        </button>
                        <div class="receipt-status" style="font-size: 12px; margin-top: 6px; display: none;"></div>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.send-receipt-btn');
    
    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order');
            const row = document.getElementById('order-row-' + orderId);
            const input = row.querySelector('.mark-input');
            const statusDiv = row.querySelector('.receipt-status');
            
            const markCode = input.value.trim();
            
            if (!markCode) {
                statusDiv.style.display = 'block';
                statusDiv.style.color = '#ef4444';
                statusDiv.textContent = 'Ошибка: отсканируйте код маркировки.';
                input.focus();
                return;
            }
            
            this.disabled = true;
            this.textContent = 'Отправка...';
            statusDiv.style.display = 'none';
            
            fetch('api_receipt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: orderId,
                    mark_code: markCode
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    row.querySelector('.receipt-form-container').innerHTML = `
                        <div style="display:inline-flex; align-items:center; gap:6px; color:#10b981; font-size:14px; font-weight:500;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> 
                            Чек пробит
                        </div>
                        <div style="font-size:11px; color:#6b7280; margin-top:4px; word-break: break-all; max-width: 250px;">
                            ${markCode.replace(/</g, '&lt;')}
                        </div>
                    `;
                } else {
                    this.disabled = false;
                    this.textContent = 'Отправить чек';
                    statusDiv.style.display = 'block';
                    statusDiv.style.color = '#ef4444';
                    statusDiv.textContent = data.error || 'Произошла ошибка при отправке.';
                }
            })
            .catch(err => {
                this.disabled = false;
                this.textContent = 'Отправить чек';
                statusDiv.style.display = 'block';
                statusDiv.style.color = '#ef4444';
                statusDiv.textContent = 'Сетевая ошибка.';
            });
        });
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
