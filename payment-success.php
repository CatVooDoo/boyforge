<?php
declare(strict_types=1);

$pageTitle = 'Оплата прошла успешно — BOYFORGE';
$bodyClass = 'page-payment-success';
require __DIR__ . '/includes/components/header.php';
?>

<main>
    <div class="container" style="text-align: center; padding: 100px 20px;">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 20px;">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
      </svg>
      <h1 style="font-size: 32px; font-weight: 800; margin-bottom: 16px;">Оплата прошла успешно!</h1>
      <p style="font-size: 16px; color: #4b5563; max-width: 500px; margin: 0 auto 30px;">
        Спасибо за заказ. Ваш платёж обрабатывается, а заказ формируется для отправки через 5Post. 
        Электронный чек был отправлен на ваш email.
      </p>
      <a href="/" class="btn-primary" style="display: inline-block;">Вернуться на главную</a>
    </div>
</main>

<?php require __DIR__ . '/includes/components/footer.php'; ?>
