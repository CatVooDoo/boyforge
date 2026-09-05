<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Каталог — BOYFORGE</title>
  <meta name="description" content="Каталог BOYFORGE: футболки с авторскими принтами. Пошив в России, DTF-печать. Заказ через Telegram.">
  <link rel="icon" href="images/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=11">
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

      <!-- хлебные крошки -->
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="index.php">Главная</a> <span>/</span> <span>Каталог</span>
      </nav>

      <!-- заголовок + счётчик + сортировка -->
      <div class="catalog-head">
        <h1 class="catalog-title">Каталог</h1>
        <div class="catalog-head-right">
          <span class="catalog-count" id="catalogCount"></span>
          <div class="sort-field">
            <span>Сортировка</span>
            <select id="sortSelect" aria-label="Сортировка">
              <option value="pop">По умолчанию</option>
              <option value="cheap">Сначала дешевле</option>
              <option value="exp">Сначала дороже</option>
              <option value="new">Сначала новинки</option>
            </select>
          </div>
        </div>
      </div>

      <!-- фильтры-чипы -->
      <div class="chips" role="group" aria-label="Категории">
        <button class="chip" type="button" data-cat="all">Все</button>
        <button class="chip" type="button" data-cat="tshirt">Футболки</button>
        <button class="chip" type="button" data-cat="sweatshirt">Свитшоты</button>
      </div>

      <!-- сетка товаров (заполняется из catalog.js) -->
      <div class="catalog-grid" id="catalogGrid"></div>

      <!-- пустое состояние -->
      <div class="empty-state" id="emptyState" hidden>
        <div class="empty-title">Ничего не найдено</div>
        <p class="empty-sub">В этой категории пока нет товаров. Загляните в другую или напишите нам — подберём под вас.</p>
        <button type="button" class="btn-outline empty-back" id="emptyBack">Сбросить фильтр</button>
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

  <script src="js/products.js?v=11"></script>
  <script src="js/catalog.js?v=11"></script>
  <script src="js/main.js?v=11"></script>
</body>
</html>
