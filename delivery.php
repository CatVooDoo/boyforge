<?php
declare(strict_types=1);

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
        <p>Выбирайте 5Post или СДЭК и удобный пункт выдачи при оформлении заказа. Доставку за пределы России обсудим в Telegram.</p>
      </section>

      <!-- ===== FACTS ===== -->
      <div class="delivery-facts reveal">
        <div class="delivery-fact">
          <span class="fact-num">7–10</span>
          <span class="fact-label">дней по России в среднем</span>
        </div>
        <div class="delivery-fact">
          <span class="fact-num">2</span>
          <span class="fact-label">службы доставки: 5Post и СДЭК</span>
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
            <p>Выберите вещь, размер, службу доставки и пункт выдачи на карте. Оплатите заказ онлайн; электронный чек придёт на email.</p>
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
            <p>Забираете посылку в выбранном пункте 5Post или СДЭК. Служба доставки сообщит о готовности к выдаче.</p>
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
        <div class="svc-card reveal">
          <h3>СДЭК</h3>
          <p class="svc-card-tag">Пункты выдачи</p>
          <p>Переключите службу на СДЭК на карте. После выбора пункта покажем стоимость и срок доставки до оплаты заказа.</p>
        </div>
      </div>

      <!-- ===== CONDITIONS ===== -->
      <div class="delivery-note reveal">
        <p><strong>Условия доставки:</strong> службу доставки выбираете вы при оформлении заказа.</p>
        <p>Доставку 5Post оплачивает магазин.</p>
        <p>Стоимость доставки СДЭК добавляется к стоимости товара и оплачивается онлайн. Доставку в страны СНГ согласуем индивидуально.</p>
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
