<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
$pageTitle = 'Проверка оплаты — BOYFORGE';
$bodyClass = 'page-payment-success';
require __DIR__ . '/includes/components/header.php';
require_once __DIR__ . '/includes/order_items.php';
?>

<main>
<?php
$orderId = trim((string)($_GET['orderId'] ?? ''));
$email = '';
$orderRow = null;
$orderItems = [];
$telegramLink = 'https://telegram.me/theboyforge';

if ($orderId !== '') {
    global $pdo;
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :oid LIMIT 1");
        $stmt->execute([':oid' => $orderId]);
        $orderRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($orderRow) {
            $email = (string)($orderRow['email'] ?? '');
            $orderItems = normalizeOrderItems($orderRow);
        }
    }
}
?>
    <div class="container" style="text-align: center; padding: 100px 20px;">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 20px;">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
      </svg>
      <h1 id="paymentResultTitle" style="font-size: clamp(24px, 4vw, 34px); font-weight: 800; margin-bottom: 16px;"><?= $orderRow && $orderRow['payment_status'] === 'paid' ? 'Оплата прошла успешно!' : 'Проверяем оплату заказа' ?></h1>
      <p id="paymentResultStatus" role="status" style="font-size: 15px; color: #4b5563; max-width: 520px; margin: 0 auto 24px;">
        Спасибо за заказ. Статус оплаты уточняется у платёжного сервиса.
      </p>
      
      <?php if ($orderId !== ''): ?>
      <div style="background: #f5f5f5; border: 1px solid #e6e4e0; border-radius: 14px; padding: 22px 24px; max-width: 480px; margin: 0 auto 32px; text-align: left; font-size: 13.5px; line-height: 1.55;">
          <div style="margin-bottom: 10px;"><strong>Номер заказа:</strong> <?= htmlspecialchars($orderId) ?></div>
          <?php if (!empty($orderItems)): ?>
          <div style="margin-bottom: 12px; padding: 10px 0; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">
              <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #6b7280; margin-bottom: 6px;">
                  Состав заказа (<?= orderTotalUnits($orderRow) ?> шт.)
              </div>
              <?php foreach ($orderItems as $it): ?>
                  <div style="display: flex; justify-content: space-between; gap: 12px; padding: 3px 0;">
                      <span><?= htmlspecialchars(formatOrderItemTitle($it, true)) ?></span>
                      <strong style="white-space: nowrap;"><?= number_format((float)$it['price'] * max(1, (int)$it['qty']), 2, '.', ' ') ?> ₽</strong>
                  </div>
              <?php endforeach; ?>
              <?php if ($orderRow && (float)($orderRow['delivery_cost'] ?? 0) > 0): ?>
                  <div style="display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; color: #4b5563; font-size: 12.5px;">
                      <span>Доставка СДЭК</span>
                      <span><?= number_format((float)$orderRow['delivery_cost'], 2, '.', ' ') ?> ₽</span>
                  </div>
              <?php endif; ?>
              <?php if ($orderRow): ?>
                  <?php if (!empty($orderRow['promo_code'])): ?>
                  <div style="padding:4px 0; color:#2e7d32; font-size:12px;">Промокод <?= htmlspecialchars((string)$orderRow['promo_code']) ?>: скидка <?= number_format((float)$orderRow['discount_amount'], 2, '.', ' ') ?> ₽ уже учтена в ценах товаров.</div>
                  <?php endif; ?>
                  <div style="display: flex; justify-content: space-between; gap: 12px; padding-top: 6px; margin-top: 4px; border-top: 1px dashed #d1d5db; font-weight: 700;">
                      <span>Итого</span>
                      <span><?= number_format((float)$orderRow['price'] + (float)($orderRow['delivery_cost'] ?? 0), 2, '.', ' ') ?> ₽</span>
                  </div>
              <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($email !== '' && $orderRow['payment_status'] === 'paid'): ?>
          <div style="margin-bottom: 8px;"><strong>Электронный чек отправлен на:</strong> <?= htmlspecialchars($email) ?></div>
          <?php endif; ?>
          <div><strong>Связь с нами:</strong> <a href="<?= htmlspecialchars($telegramLink) ?>" target="_blank" rel="noopener" style="color: #000; text-decoration: underline;">Telegram</a></div>
      </div>
      <?php endif; ?>
      
      <a href="/catalog" class="btn-primary" style="display: inline-block;">Продолжить покупки</a>
    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const params = new URLSearchParams(window.location.search);
    const orderId = params.get('orderId');

    if (orderId) {
        fetch('/api/yookassa/confirm_by_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ orderId: orderId })
        }).then(res => {
            if (!res.ok) throw new Error('Ошибка проверки оплаты');
            return res.json();
        }).then(data => {
            if (!data.success) throw new Error('Платёж пока не подтверждён. Корзина сохранена. Обновите страницу для повторной проверки.');
            document.getElementById('paymentResultTitle').textContent = 'Оплата прошла успешно!';
            document.getElementById('paymentResultStatus').textContent = 'Спасибо за заказ. Оплата подтверждена, заказ передан в обработку.';
            try {
                if (localStorage.getItem('bf_pending_cart_order') === orderId) {
                    window.BFCart.clear();
                }
            } catch (e) {}
        }).catch(() => {
            document.getElementById('paymentResultStatus').textContent = 'Не удалось подтвердить оплату. Корзина сохранена. Обновите страницу для повторной проверки.';
        });
    }
});
</script>

<?php require __DIR__ . '/includes/components/footer.php'; ?>
