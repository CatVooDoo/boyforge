<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/delivery_availability.php';

$pageTitle = 'Доставка — BOYFORGE';
$bodyClass = 'page-delivery';

require __DIR__ . '/includes/components/header.php';
?>

<main>
    <div class="container">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a> <span>/</span> <span>Доставка</span>
      </nav>

      <!-- ===== HERO ===== -->
      <section class="svc-hero reveal">
        <h1>Как посылка<br>доедет до вас</h1>
        <p>Выбирайте <?= CDEK_CHECKOUT_ENABLED ? '5Post или СДЭК и удобный пункт выдачи' : 'удобный пункт выдачи 5Post' ?> при оформлении заказа. Доставку за пределы России обсудим в Telegram.</p>
      </section>

      <!-- ===== FACTS ===== -->
      <div class="delivery-facts reveal">
        <div class="delivery-fact">
          <span class="fact-num">7–10</span>
          <span class="fact-label">дней по России в среднем</span>
        </div>
        <div class="delivery-fact">
          <span class="fact-num"><?= CDEK_CHECKOUT_ENABLED ? '2' : '1' ?></span>
          <span class="fact-label"><?= CDEK_CHECKOUT_ENABLED ? 'службы доставки: 5Post и СДЭК' : 'служба доставки: 5Post' ?></span>
        </div>
        <div class="delivery-fact">
          <span class="fact-num">СНГ</span>
          <span class="fact-label">доставка по индивидуальному согласованию</span>
        </div>
      </div>

      <!-- ===== PROCESS ===== -->
      <h2 class="section-title reveal">Путь заказа</h2>
      <div class="delivery-path">
        <div class="delivery-step reveal">
          <div class="delivery-num">1</div>
          <div class="delivery-body">
            <h3>Оформление заказа</h3>
            <p>Выберите вещь, размер и пункт выдачи на карте. Оплатите заказ онлайн; электронный чек придёт на email.</p>
          </div>
        </div>
        <div class="delivery-step reveal">
          <div class="delivery-num">2</div>
          <div class="delivery-body">
            <h3>Сборка и отправка</h3>
            <p>Аккуратно упаковываем заказ и передаём в службу доставки.</p>
          </div>
        </div>
        <div class="delivery-step reveal">
          <div class="delivery-num">3</div>
          <div class="delivery-body">
            <h3>В пути</h3>
            <p>Доставка по России занимает в среднем 7–10 дней в зависимости от региона. В страны СНГ — дольше.</p>
          </div>
        </div>
        <div class="delivery-step reveal">
          <div class="delivery-num">4</div>
          <div class="delivery-body">
            <h3>Получение</h3>
            <p>Забираете посылку в выбранном пункте <?= CDEK_CHECKOUT_ENABLED ? '5Post или СДЭК' : '5Post' ?>. Служба доставки сообщит о готовности к выдаче.</p>
          </div>
        </div>
      </div>

      <!-- ===== SERVICES ===== -->
      <h2 class="section-title reveal">Службы доставки</h2>
      <div class="svc-cards">
        <div class="svc-card reveal">
          <h3>5Post</h3>
          <p class="svc-card-tag">Пятёрочка / Перекрёсток</p>
          <p>Получайте заказы в магазинах и постаматах сети. Выберите удобную точку на карте при оформлении.</p>
        </div>
        <?php if (CDEK_CHECKOUT_ENABLED): ?>
        <div class="svc-card reveal">
          <h3>СДЭК</h3>
          <p class="svc-card-tag">Пункты выдачи</p>
          <p>Переключите службу на СДЭК на карте. После выбора пункта покажем стоимость и срок доставки до оплаты заказа.</p>
        </div>
        <?php endif; ?>
      </div>

      <!-- ===== CONDITIONS ===== -->
      <div class="delivery-note reveal">
        <p><strong>Условия доставки:</strong> <?= CDEK_CHECKOUT_ENABLED ? 'службу доставки выбираете вы' : 'выберите удобный пункт выдачи 5Post' ?> при оформлении заказа.</p>
        <p>Доставку 5Post оплачивает магазин.</p>
        <?php if (CDEK_CHECKOUT_ENABLED): ?>
        <p>Стоимость доставки СДЭК добавляется к стоимости товара и оплачивается онлайн.</p>
        <?php endif; ?>
        <p>Доставку в страны СНГ согласуем индивидуально.</p>
        <p>Сроки по предзаказу устанавливаются индивидуально и составляют от 21 дня с момента оплаты. Подробности — в Публичной оферте.</p>
      </div>

      <!-- ===== CTA ===== -->
      <section class="svc-cta reveal">
        <h2>Остались вопросы по доставке?</h2>
        <p>Подскажем сроки, стоимость и удобный способ получения именно для вашего города.</p>
        <a class="btn btn-primary" href="https://t.me/theboyforge" target="_blank" rel="noopener">Написать в Telegram</a>
      </section>

    </div>
  </main>

  <?php require __DIR__ . '/includes/components/footer.php'; ?>
