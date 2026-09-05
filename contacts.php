<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Контакты — BOYFORGE</title>
  <meta name="description" content="Контакты BOYFORGE: Telegram и почта. Напишите нам — ответим в течение рабочего дня.">
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
        <a href="index.php">Главная</a> <span>/</span> <span>Контакты</span>
      </nav>
    </div>

    <!-- ===== SPLIT HERO ===== -->
    <section class="container ct-hero">
      <div class="ct-hero-left reveal">
        <div class="ct-eyebrow">Контакты</div>
        <h1>Мы всегда<br>на связи</h1>
        <p>Напишите удобным способом — ответим в течение рабочего дня. Быстрее всего в Telegram.</p>
      </div>

      <div class="ct-hero-right">
        <a href="https://t.me/theboyforge" target="_blank" rel="noopener" class="ct-primary reveal">
          <div class="ct-primary-tag">Быстрее всего</div>
          <h3>Telegram</h3>
          <span class="ct-primary-value">@theboyforge</span>
          <span class="ct-primary-arrow">Написать →</span>
        </a>
        <a href="mailto:boyforge@bk.ru" class="ct-secondary reveal">
          <h3>Почта</h3>
          <span class="ct-secondary-value">boyforge@bk.ru</span>
          <p>Сотрудничество</p>
        </a>
      </div>
    </section>

    <!-- ===== ФОРМА (светлая) ===== -->
    <section class="container ct-form-section">
      <div class="ct-form-card reveal">
        <div class="ct-form-head">
          <h2>Написать нам</h2>
          <p>Укажите ник в Telegram и вопрос — поможем с размером, оплатой и доставкой.</p>
        </div>

        <form class="ct-form" id="contactForm" novalidate>
          <div class="ct-field">
            <label for="tgName">Ваш ник в Telegram</label>
            <input type="text" id="tgName" name="tg" placeholder="@username" autocomplete="off">
          </div>
          <div class="ct-field">
            <label for="question">Ваш вопрос</label>
            <textarea id="question" name="question" placeholder="Опишите ваш вопрос"></textarea>
          </div>
          <button type="submit">Отправить</button>
          <p class="form-status" id="formStatus" role="status" aria-live="polite"></p>
        </form>
      </div>
    </section>

    <!-- ===== РЕКВИЗИТЫ ===== -->
    <section class="container ct-req-section">
      <div class="ct-req reveal">
        <h2>Реквизиты</h2>
        <div class="ct-req-grid">
          <div class="ct-req-item">
            <span class="ct-req-label">Продавец</span>
            <span class="ct-req-value">ИП Бояров Тимофей Петрович</span>
          </div>
          <div class="ct-req-item">
            <span class="ct-req-label">ИНН</span>
            <span class="ct-req-value">580319807587</span>
          </div>
          <div class="ct-req-item">
            <span class="ct-req-label">Адрес</span>
            <span class="ct-req-value">г. Пенза, ул. Галетная, 22А</span>
          </div>
          <div class="ct-req-item">
            <span class="ct-req-label">Почта</span>
            <span class="ct-req-value">boyforge@bk.ru</span>
          </div>
          <div class="ct-req-item">
            <span class="ct-req-label">Банк</span>
            <span class="ct-req-value">АО «ТБанк», БИК 044525974</span>
          </div>
          <div class="ct-req-item">
            <span class="ct-req-label">Расчётный счёт</span>
            <span class="ct-req-value">40802810700009414372</span>
          </div>
        </div>
      </div>
    </section>

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
