<?php
declare(strict_types=1);

$pageTitle = 'Оплата — BOYFORGE';
$bodyClass = 'page-payment';

require __DIR__ . '/includes/components/header.php';
?>

<main>
    <div class="container">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a> <span>/</span> <span>Оплата</span>
      </nav>

      <!-- ===== HERO ===== -->
      <section class="svc-hero reveal">
        <h1>Удобно<br>и безопасно</h1>
        <p>После оплаты автоматически приходит кассовый чек — без скрытых платежей и лишних действий.</p>
      </section>

      <!-- ===== METHODS ===== -->
      <div class="pay-grid">
        <div class="pay-card pay-card--dark reveal">
          <div class="pay-tag">Основной способ</div>
          <h3>Банковская карта</h3>
          <p>Оплата товара и доставки картой через защищённую платёжную систему прямо на сайте.</p>
        </div>
        <div class="pay-card reveal">
          <div class="pay-tag">Документы</div>
          <h3>Кассовый чек</h3>
          <p>После оплаты вы получаете электронный кассовый чек. Договор считается заключённым с момента его отправки.</p>
        </div>
      </div>

      <!-- ===== STEPS ===== -->
      <h2 class="section-title reveal">Как проходит оплата</h2>
      <div class="pay-steps reveal">
        <div class="pay-chip"><b>1</b> Выбор товара</div>
        <div class="pay-chip"><b>2</b> Счёт на оплату</div>
        <div class="pay-chip"><b>3</b> Оплата картой</div>
        <div class="pay-chip"><b>4</b> Отправка</div>
      </div>

      <!-- ===== TRUST / FEATURES ===== -->
      <div class="pay-features">
        <div class="pay-feature reveal">
          <h4>Защищённый платёж</h4>
          <p>Данные карты обрабатываются на стороне платёжной системы и не хранятся у нас.</p>
        </div>
        <div class="pay-feature reveal">
          <h4>Прозрачная цена</h4>
          <p>Никаких скрытых комиссий: в счёте видна полная стоимость заказа и доставки.</p>
        </div>
        <div class="pay-feature reveal">
          <h4>Официальный чек</h4>
          <p>Каждый платёж подтверждается электронным кассовым чеком по 54-ФЗ.</p>
        </div>
      </div>

      <!-- ===== NOTE ===== -->
      <div class="pay-note reveal">
        <p><strong>Важно:</strong> по предзаказам оплата вносится заранее, сроки изготовления — от 21 дня с момента оплаты.</p>
        <p>Полные условия указаны в Публичной оферте.</p>
      </div>

    </div>
  </main>

  <?php require __DIR__ . '/includes/components/footer.php'; ?>
