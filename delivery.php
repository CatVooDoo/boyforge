<?php
declare(strict_types=1);

$pageTitle = 'Доставка — BOYFORGE';
$bodyClass = 'page-delivery';

require __DIR__ . '/includes/components/header.php';
?>

<main>
    <div class="container">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="index.php">Главная</a> <span>/</span> <span>Доставка</span>
      </nav>

      <!-- ===== HERO ===== -->
      <section class="svc-hero reveal">
        <h1>Как посылка<br>доедет до вас</h1>
        <p>Отправляем по всей России через Ozon, а туда, где Ozon недоступен, — через СДЭК. Доставляем и в Европу тоже.</p>
      </section>

      <!-- ===== FACTS ===== -->
      <div class="delivery-facts reveal">
        <div class="delivery-fact">
          <span class="fact-num">7–10</span>
          <span class="fact-label">дней по России в среднем</span>
        </div>
        <div class="delivery-fact">
          <span class="fact-num">2</span>
          <span class="fact-label">службы доставки: Ozon и СДЭК</span>
        </div>
        <div class="delivery-fact">
          <span class="fact-num">СНГ</span>
          <span class="fact-label">доставка в страны СНГ через СДЭК</span>
        </div>
      </div>

      <!-- ===== PROCESS ===== -->
      <h2 class="section-title reveal">Путь заказа</h2>
      <div class="delivery-path">
        <div class="delivery-step reveal">
          <div class="delivery-num">1</div>
          <div class="delivery-body">
            <h3>Оформление заказа</h3>
            <p>Вы выбираете вещь и пишете нам в Telegram. Подскажем с размером и подтвердим наличие.</p>
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
            <p>Забираете посылку в пункте выдачи Ozon по QR-коду (в СДЭК по трек-номеру).</p>
          </div>
        </div>
      </div>

      <!-- ===== SERVICES ===== -->
      <h2 class="section-title reveal">Службы доставки</h2>
      <div class="svc-cards">
        <div class="svc-card reveal">
          <h3>Ozon</h3>
          <p class="svc-card-tag">Основной способ</p>
          <p>Отправляем через Ozon в первую очередь — это удобно, быстро и с широкой сетью пунктов выдачи по России.</p>
        </div>
        <div class="svc-card reveal">
          <h3>СДЭК</h3>
          <p class="svc-card-tag">Резервный способ</p>
          <p>Используем СДЭК только там, куда Ozon не доставляет, а также для отправки в страны СНГ.</p>
        </div>
      </div>

      <!-- ===== CONDITIONS ===== -->
      <div class="delivery-note reveal">
        <p><strong>Условия доставки:</strong> доставка СДЭК используется только там, где недоступна доставка через Ozon.</p>
        <p>Для стран СНГ доставка СДЭК оплачивается покупателем.</p>
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
