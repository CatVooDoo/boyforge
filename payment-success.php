<?php
declare(strict_types=1);

$pageTitle = 'Оплата прошла успешно — BOYFORGE';
$bodyClass = 'page-payment-success';
require __DIR__ . '/includes/components/header.php';
?>

<main>
<?php
$orderId = trim($_GET['orderId'] ?? '');
$email = '';
$telegramLink = 'https://telegram.me/theboyforge';

if ($orderId) {
    global $pdo; // Should be available via header/config if included
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT email FROM orders WHERE order_id = :oid LIMIT 1");
        $stmt->execute([':oid' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['email'])) {
            $email = $row['email'];
        }
    }
}
?>
    <div class="container" style="text-align: center; padding: 100px 20px;">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 20px;">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
      </svg>
      <h1 style="font-size: 32px; font-weight: 800; margin-bottom: 16px;">Оплата прошла успешно!</h1>
      <p style="font-size: 16px; color: #4b5563; max-width: 500px; margin: 0 auto 20px;">
        Спасибо за заказ. Ваш платёж обрабатывается, а заказ формируется для отправки через 5Post. 
      </p>
      
      <?php if ($orderId): ?>
      <div style="background: #f3f4f6; border-radius: 8px; padding: 20px; max-width: 400px; margin: 0 auto 30px; text-align: left;">
          <div style="margin-bottom: 8px;"><strong>Номер заказа:</strong> <?= htmlspecialchars($orderId) ?></div>
          <?php if ($email): ?>
          <div style="margin-bottom: 8px;"><strong>Электронный чек отправлен на:</strong> <?= htmlspecialchars($email) ?></div>
          <?php endif; ?>
          <div><strong>Связь с нами:</strong> <a href="<?= htmlspecialchars($telegramLink) ?>" target="_blank" style="color: #000; text-decoration: underline;">Telegram</a></div>
      </div>
      <?php endif; ?>
      
      <a href="/" class="btn-primary" style="display: inline-block;">Вернуться на главную</a>
    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const params = new URLSearchParams(window.location.search);
    const orderId = params.get('orderId');
    if (orderId) {
        // Запускаем проверку статуса заказа на сервере
        fetch('/api/yookassa/confirm_by_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ orderId: orderId })
        }).then(res => res.json()).then(data => {
            console.log("Check result:", data);
        }).catch(err => console.error("Error checking order:", err));
    }
});
</script>

<?php require __DIR__ . '/includes/components/footer.php'; ?>
