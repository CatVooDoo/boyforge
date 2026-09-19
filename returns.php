<?php
declare(strict_types=1);

$pageTitle = 'Возврат и обмен — BOYFORGE';
$bodyClass = 'page-returns';

require __DIR__ . '/includes/components/header.php';
?>

<main>
    <div class="container">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a> <span>/</span> <span>Возврат</span>
      </nav>

      <!-- ===== HERO ===== -->
      <section class="svc-hero reveal">
        <h1>Правила возврата<br>и обмена товара</h1>
      </section>

      <div class="return-lead reveal">
        <p>Мы дорожим доверием. Возврат оформляется по закону и занимает до 10 дней с момента получения заявления.</p>
      </div>

      <!-- ===== RULES ===== -->
      <div class="return-list">
        <div class="return-rule return-rule--yes reveal">
          <div class="return-mark">✓</div>
          <div class="return-body">
            <h3>Возврат возможен</h3>
            <p>В течение 7 дней после получения, если сохранены товарный вид, упаковка и потребительские свойства.</p>
          </div>
        </div>
        <div class="return-rule return-rule--yes reveal">
          <div class="return-mark">✓</div>
          <div class="return-body">
            <h3>Производственный брак</h3>
            <p>При подтверждённом дефекте возврат возможен в течение 10 дней с момента получения.</p>
          </div>
        </div>
        <div class="return-rule return-rule--no reveal">
          <div class="return-mark">✕</div>
          <div class="return-body">
            <h3>Возврат невозможен</h3>
            <p>Если товар со следами использования, повреждён по вине покупателя или имеет индивидуальные характеристики (уникальный принт, размер под заказ).</p>
          </div>
        </div>
      </div>
              <div class="return-rule return-rule--yes reveal">
          <div class="return-mark">✓</div>
          <div class="return-body">
            <h3>Обмен на другой размер</h3>
            <p>Если размер не подошёл, обменяем на нужный в течение 7 дней при сохранении товарного вида и упаковки. Возврат товара оплачивается покупателем.</p>
          </div>
        </div>


      <!-- ===== HOW TO ===== -->
      <h2 class="section-title reveal">Как оформить возврат</h2>
      <div class="return-steps">
        <div class="return-step reveal">
          <span class="return-step-num">01</span>
          <h4>Напишите нам</h4>
          <p>Свяжитесь в Telegram и опишите причину возврата или обмена.</p>
        </div>
        <div class="return-step reveal">
          <span class="return-step-num">02</span>
          <h4>Заявление</h4>
          <p>Поможем оформить заявление на возврат и подскажем, что приложить.</p>
        </div>
        <div class="return-step reveal">
          <span class="return-step-num">03</span>
          <h4>Отправка товара</h4>
          <p>Вы отправляете товар обратно в исходном виде и упаковке.</p>
        </div>
        <div class="return-step reveal">
          <span class="return-step-num">04</span>
          <h4>Возврат средств</h4>
          <p>После проверки возвращаем деньги в течение 10 дней тем же способом.</p>
        </div>
      </div>

      <!-- ===== NOTE ===== -->
      <div class="return-note reveal">
        <p><strong>Обратите внимание:</strong> товары, изготовленные под заказ (индивидуальный принт, размер под заказ), возврату и обмену не подлежат, кроме случаев производственного брака.</p>
        <p>Полные условия возврата указаны в Публичной оферте.</p>
      </div>

      <!-- ===== CTA ===== -->
      <div class="return-cta reveal">
        <h2>Нужно оформить возврат?</h2>
        <p>Напишите нам — поможем на каждом шаге.</p>
        <a href="https://t.me/theboyforge" target="_blank" rel="noopener" class="btn btn-primary">Написать в Telegram</a>
      </div>

    </div>
  </main>

  <?php require __DIR__ . '/includes/components/footer.php'; ?>
