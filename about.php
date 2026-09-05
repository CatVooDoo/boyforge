<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>О бренде — BOYFORGE</title>
  <meta name="description" content="История BOYFORGE — кузницы стильной одежды. От предка-кузнеца Алексея (1891) до бренда Тимофея Боярова. Труд, характер и уважение.">
  <link rel="icon" href="images/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=14">
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

    <!-- ===== ПРЕДИСЛОВИЕ (тёмный hero) ===== -->
    <section class="story-hero theme-dark">
      <div class="container">
        <div class="story-eyebrow reveal">Предисловие</div>
        <h1 class="story-hero-title reveal">Любое дело<br>начинается с тишины</h1>
        <p class="story-hero-lead reveal">…и без лишних слов. Так когда-то делал мой предок — Алексей.<br>Об этом мне рассказала бабушка пару лет назад.</p>
        <div class="story-scroll reveal" aria-hidden="true">
          <span>История</span>
          <span class="story-scroll-line"></span>
        </div>
      </div>
    </section>

    <!-- хлебные крошки -->
    <div class="container">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="index.php">Главная</a> <span>/</span> <span>О бренде</span>
      </nav>
    </div>

    <!-- ===== ГЛАВА 1 — тяжёлый год ===== -->
    <section class="section story-chapter">
      <div class="container">
        <div class="story-media reveal">
          <img src="images/story-1891.jpg" alt="Тяжёлый год, 1891" loading="lazy"
               onerror="this.style.opacity='0'">
        </div>
        <div class="story-block reveal">
          <span class="story-year">1891</span>
          <h2 class="story-h">Был тяжёлый год</h2>
          <p>Земля истощилась и не дала урожая, а плуги ломались быстрее, чем успевали появляться новые.</p>
          <p>Люди собирались во дворе, кричали, требовали ответов и ждали слов главного по деревне.</p>
        </div>
      </div>
    </section>

    <!-- ===== ГЛАВА 2 — пахать нечем (тёмная) ===== -->
    <section class="section theme-dark story-chapter">
      <div class="container">
        <div class="story-media reveal">
          <img src="images/story-plows.jpg" alt="Сломанные плуги" loading="lazy"
               onerror="this.style.opacity='0'">
        </div>
        <div class="story-block reveal">
          <h2 class="story-h">Пахать нечем</h2>
          <p>Это стало понятно каждому.</p>
          <p>Алексей пошёл по деревне и заходил в каждый двор — поднимал из грязи сломанные плуги и изношенные лемеха.</p>
        </div>

        <div class="story-divider reveal" aria-hidden="true"></div>

        <div class="story-block reveal">
          <h2 class="story-h">Зима в кузнице</h2>
          <p>Мой предок день за днём разогревал металл и возвращал ему форму — чтобы весной земля снова могла быть засеяна.</p>
        </div>
        <div class="story-media story-media--after reveal">
          <img src="images/story-forge.jpg" alt="Зима в кузнице" loading="lazy"
               onerror="this.style.opacity='0'">
        </div>
      </div>
    </section>

    <!-- ===== ГЛАВА 3 — новая весна ===== -->
    <section class="section story-chapter">
      <div class="container">
        <div class="story-media reveal">
          <img src="images/story-1892.jpg" alt="Новая весна, 1892" loading="lazy"
               onerror="this.style.opacity='0'">
        </div>
        <div class="story-block reveal">
          <span class="story-year">1892</span>
          <h2 class="story-h">Новая весна</h2>
          <p>Работа пошла своим чередом. Никто не говорил громких слов — просто стало ясно, что зима была прожита не зря.</p>
          <p>Он смотрел на поля и знал: плуги прослужат долго.</p>
        </div>
      </div>
    </section>

    <!-- ===== ПЕРЕХОД К ОСНОВАТЕЛЮ (тёмная, цитата) ===== -->
    <section class="section theme-dark story-founder">
      <div class="container">
        <div class="story-media reveal">
          <img src="images/story-founder.jpg" alt="Тимофей Бояров, основатель BOYFORGE" loading="lazy"
               onerror="this.style.opacity='0'">
        </div>
        <div class="story-block reveal">
          <span class="story-year">2025</span>
          <p class="story-quote">«Поработав в разных сферах, работая с клиентами, я по большей части излучал только позитив, поднимая настроение людям. В один момент я понял, что устал работать на кого-то — и, вдохновившись историей моего предка, решил воплотить давнюю мечту: бренд одежды.</p>
          <p class="story-quote">Тоже кузницу — но кузницу стильной одежды для твоего гардероба».</p>
          <div class="story-signature">
            <span class="story-sign-name">Тимофей Бояров</span>
            <span class="story-sign-role">Founder «BOYFORGE»</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== НАШЕ НАЧАЛО (светлая) ===== -->
    <section class="section story-chapter">
      <div class="container">
        <div class="story-block reveal">
          <h2 class="story-h">Это наше начало</h2>
          <p>Как и тогда, важны труд, характер и уважение к тому, для кого это создаётся. Чтобы с первого взгляда вещь становилась любимой в гардеробе.</p>
          <p>Хочется создавать здесь семейную обстановку — чтобы в любой момент мы были тем местом, куда можно вернуться, если что-то не так.</p>
          <p>Мы будем затрагивать разные направления товаров и постараемся радовать как можно чаще розыгрышами и другими активностями.</p>
        </div>

        <div class="story-love reveal">
          <span class="story-love-text">С любовью,</span>
          <span class="story-love-brand">BOYFORGE</span>
        </div>
      </div>
    </section>

    <!-- ===== ФИНАЛЬНЫЙ CTA (тёмная) ===== -->
    <section class="section theme-dark">
      <div class="container">
        <div class="brand-intro reveal">
          <div class="mini-head"><h2>Загляните в кузницу</h2></div>
          <p>Выберите вещь, которая станет любимой.</p>
          <a href="catalog.php" class="btn-outline">Перейти в каталог</a>
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

  <script src="js/main.js?v=14"></script>
</body>
</html>
