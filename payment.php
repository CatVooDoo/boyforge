<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Оплата — BOYFORGE</title>
  <meta name="description" content="Оплата заказов BOYFORGE: банковской картой онлайн с автоматическим кассовым чеком. Безопасно и прозрачно, без скрытых платежей.">
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
        <a href="index.php">Главная</a> <span>/</span> <span>Оплата</span>
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
          <a href="#" target="_blank" rel="noopener">Вконтакте</a>
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
