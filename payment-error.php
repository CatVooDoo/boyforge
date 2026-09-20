<?php
declare(strict_types=1);

$pageTitle = 'Ошибка оплаты — BOYFORGE';
$bodyClass = 'page-payment-error';
require __DIR__ . '/includes/components/header.php';
?>

<main>
    <div class="container" style="text-align: center; padding: 100px 20px;">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 20px;">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="15" y1="9" x2="9" y2="15"></line>
        <line x1="9" y1="9" x2="15" y2="15"></line>
      </svg>
      <h1 style="font-size: 32px; font-weight: 800; margin-bottom: 16px;">Оплата не удалась</h1>
      <p style="font-size: 16px; color: #4b5563; max-width: 500px; margin: 0 auto 30px;">
        К сожалению, во время обработки платежа произошла ошибка, или платёж был отменён. 
        Деньги не были списаны. Пожалуйста, попробуйте оформить заказ снова.
      </p>
      <a href="/catalog" class="btn-primary" style="display: inline-block;">Вернуться в каталог</a>
    </div>
</main>

<?php require __DIR__ . '/includes/components/footer.php'; ?>
