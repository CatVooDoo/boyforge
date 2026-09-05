<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Возврат и обмен — BOYFORGE</title>
  <meta name="description" content="Возврат и обмен товаров BOYFORGE: условия, сроки и порядок. Если размер не подошёл — поможем обменять или вернуть.">
  <link rel="icon" href="images/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=17">
</head>
<body>

  <!-- ===== HEADER ===== -->
  <header class="site-header">
    <div class="container header-inner">
      <a href="index.php" class="logo-photo" aria-label="BOYFORGE — на главную">
        <img class="logo-dark" src="images/logo.png" alt="BOYFORGE"
             onerror="this.replaceWith(document.createTextNode('BOYFORGE'))">
      </a>
      <button class="burger" aria-label="Открыть меню" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <!-- ===== SIDE MENU ===== -->
  <div class="menu-overlay overlay" hidden></div>
  <nav class="mobile-menu" aria-hidden="true" aria-label="Основное меню">
    <button class="menu-close" aria-label="Закрыть меню">&times;</button>

    <div class="menu-group">
      <a href="index.php">Главная</a>
      <a href="catalog.php">Каталог</a>
    </div>

    <span class="menu-divider" aria-hidden="true"></span>

    <div class="menu-group">
      <a href="delivery.php">Доставка</a>
      <a href="payment.php">Оплата</a>
      <a href="returns.php">Возврат</a>
    </div>

    <span class="menu-divider" aria-hidden="true"></span>

    <div class="menu-group">
      <a href="about.php">О бренде</a>
      <a href="reviews.php">Отзывы</a>
      <a href="contacts.php">Контакты</a>
    </div>

  </nav>

  <main>
    <div class="container">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="index.php">Главная</a> <span>/</span> <span>Возврат</span>
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

  <!-- ===== FOOTER ===== -->
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-col">
          <h4>Компания</h4>
          <a href="about.php">О бренде</a>
          <a href="contacts.php">Контакты</a>
        </div>
        <div class="footer-col">
          <h4>Помощь</h4>
          <a href="delivery.php">Доставка</a>
          <a href="payment.php">Оплата</a>
          <a href="returns.php">Возврат товара</a>
        </div>
        <div class="footer-col">
          <h4>Мы в соцсетях</h4>
          <a href="https://t.me/boyforge" target="_blank" rel="noopener">Telegram</a>
        </div>
      </div>
      <div class="footer-bottom">
        <span>© <span id="year">2026</span> BOYFORGE. Все права защищены</span>
        <a href="privacy.php">Политика конфиденциальности</a>
        <a href="terms.php">Пользовательское соглашение</a>
        <a href="offer.php">Публичная оферта</a>
      </div>
    </div>
  </footer>

  <script src="js/main.js?v=17"></script>
</body>
</html>
