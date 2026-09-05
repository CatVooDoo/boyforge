<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title id="metaTitle">Товар · BOYFORGE</title>
  <link rel="icon" href="images/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=22">
</head>
<body class="page-product">

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

  <main class="container">

    <nav class="breadcrumbs" aria-label="Хлебные крошки">
      <a href="index.php">Главная</a> <span>/</span>
      <a href="catalog.php">Каталог</a> <span>/</span>
      <span id="crumbName">Товар</span>
    </nav>

    <div class="product-layout">

      <!-- ГАЛЕРЕЯ -->
      <div class="product-gallery">
        <div class="thumbs" id="thumbs"><!-- миниатюры добавит JS (десктоп) --></div>
        <div class="main-photo">
          <img src="" alt="" id="galleryMain">
        </div>
        <div class="gallery-dots" id="galleryDots"><!-- точки добавит JS (мобайл) --></div>
      </div>

      <!-- ИНФО -->
      <div class="product-info">
        <h1 id="prodName">Товар</h1>
        <div class="product-art" id="prodArt">Футболка · авторский принт</div>

        <div class="product-price">
          <span class="new" id="prodPrice">—</span>
        </div>

        <div class="gender-title">
          <span>Пол</span>
        </div>
        <div class="genders" id="genders">
          <button type="button" data-gender="Мужской">Мужской</button>
          <button type="button" data-gender="Женский">Женский</button>
        </div>

        <div class="size-title">
          <span>Размер</span>
        </div>
        <div class="sizes">
          <button>S</button>
          <button class="active">M</button>
          <button>L</button>
          <button>XL</button>
          <button>2XL</button>
        </div>


        <div class="product-actions">
          <button type="button" class="btn-ozon btn-block" id="ozonOrderBtn">
            <svg class="ozon-btn-icon" viewBox="0 0 24 24" width="20" height="20" fill="none">
              <rect width="24" height="24" rx="5" fill="#005BFF"/>
              <text x="12" y="16" fill="#fff" font-size="8.5" font-family="system-ui, -apple-system, sans-serif" font-weight="900" text-anchor="middle" letter-spacing="0.5">OZON</text>
            </svg>
            Заказать с OZON доставкой
          </button>
          <a href="#" class="btn-primary btn-block" id="orderBtn" target="_blank" rel="noopener">
            Заказать в Telegram
          </a>
        </div>

        <div class="product-accordion">
          <div class="accordion-item open">
            <button class="accordion-head" type="button">Описание <span class="plus">+</span></button>
            <div class="accordion-body"><div class="accordion-body-inner">
              <p id="prodDesc"></p>
            </div></div>
          </div>

          <div class="accordion-item">
            <button class="accordion-head" type="button">Характеристики <span class="plus">+</span></button>
            <div class="accordion-body"><div class="accordion-body-inner">
              <ul class="specs" id="prodSpecs"></ul>
            </div></div>
          </div>

          <div class="accordion-item">
            <button class="accordion-head" type="button">Доставка и оплата <span class="plus">+</span></button>
            <div class="accordion-body"><div class="accordion-body-inner">
              <p>Доставка через Ozon (по номеру телефона) или CDEK там, где Ozon недоступен.
              Для стран СНГ доставка через CDEK оплачивается покупателем. Оплата — по счёту
              на email, чек приходит автоматически. Отправка обычно 7–10 дней.</p>
            </div></div>
          </div>
        </div>

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
          <a href="https://t.me/theboyforge" target="_blank" rel="noopener">Telegram</a>
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

  <script src="js/products.js?v=20"></script>
  <script src="js/product.js?v=20"></script>
  <script src="js/main.js?v=20"></script>


  <!-- модалка увеличенного фото товара -->
<div class="pv-modal" id="pvModal" aria-hidden="true">
  <button class="pv-modal-close" id="pvClose" aria-label="Закрыть">&times;</button>
  <button class="pv-modal-arrow pv-prev" id="pvPrev" aria-label="Предыдущее">&#8249;</button>
  <img class="pv-modal-img" id="pvImg" src="" alt="">
  <button class="pv-modal-arrow pv-next" id="pvNext" aria-label="Следующее">&#8250;</button>
</div>

  <!-- ===== МОДАЛЬНОЕ ОКНО OZON ДОСТАВКИ ===== -->
  <div class="ozon-modal" id="ozonModal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="ozon-modal-dialog">
      <div class="ozon-modal-header">
        <div class="ozon-brand-wrap">
          <span class="ozon-logo-pill">OZON</span>
          <h3 class="ozon-modal-title">Оформление с OZON Доставкой</h3>
        </div>
        <button type="button" class="ozon-modal-close" id="ozonModalClose" aria-label="Закрыть">&times;</button>
      </div>

      <div class="ozon-modal-body">
        <!-- Мини-карточка товара -->
        <div class="ozon-prod-summary">
          <img class="ozon-prod-thumb" id="ozonModalProdImg" src="" alt="Товар">
          <div class="ozon-prod-info">
            <div class="ozon-prod-name" id="ozonModalProdName">Товар BOYFORGE</div>
            <div class="ozon-prod-price" id="ozonModalProdPrice">—</div>
            <div class="ozon-prod-tags">
              <span class="ozon-pill" id="ozonModalProdGender">Мужской</span>
              <span class="ozon-pill">Размер: <strong id="ozonModalProdSize">M</strong></span>
            </div>
          </div>
        </div>

        <form id="ozonOrderForm" novalidate>
          <!-- 1. Данные покупателя -->
          <div class="ozon-section">
            <div class="ozon-section-title">1. Контактные данные покупателя</div>
            <div class="ozon-fields-grid">
              <div class="ozon-field">
                <label for="ozonTg">Ваш Telegram (@username)</label>
                <input type="text" id="ozonTg" class="ozon-input" placeholder="@username" required autocomplete="off">
              </div>
              <div class="ozon-field">
                <label for="ozonPhone">Номер телефона</label>
                <input type="tel" id="ozonPhone" class="ozon-input" placeholder="+7 (999) 000-00-00" required autocomplete="tel">
              </div>
            </div>
          </div>

          <!-- 2. Выбор города и ПВЗ -->
          <div class="ozon-section" style="margin-top:16px;">
            <div class="ozon-section-title" style="display:flex; align-items:center; justify-content:space-between;">
              <span>2. Пункт выдачи OZON</span>
              <span style="font-size:11px; font-weight:normal; color:#9ca3af;">(необязательно)</span>
            </div>
            <p style="font-size:12px; color:#888; margin:2px 0 10px 0;">
              Вы можете выбрать удобный ПВЗ на карте или согласовать адрес позже в Telegram.
            </p>

            <!-- Города -->
            <div class="ozon-city-chips">
              <button type="button" class="ozon-city-chip active" data-city="Москва">Москва</button>
              <button type="button" class="ozon-city-chip" data-city="Санкт-Петербург">Санкт-Петербург</button>
              <button type="button" class="ozon-city-chip" data-city="Екатеринбург">Екатеринбург</button>
              <button type="button" class="ozon-city-chip" data-city="Казань">Казань</button>
              <button type="button" class="ozon-city-chip" data-city="Новосибирск">Новосибирск</button>
              <button type="button" class="ozon-city-chip" data-city="Краснодар">Краснодар</button>
            </div>

            <!-- Поиск по городу / улице и кнопка геолокации -->
            <div class="ozon-search-row" style="margin-top:8px;">
              <input type="text" id="ozonCitySearch" class="ozon-input" placeholder="Поиск по городу, метро или улице (например, Арбат)...">
              <button type="button" class="ozon-geo-btn" id="ozonGeoBtn" title="Найти ближайший ПВЗ рядом со мной">
                Рядом
              </button>
            </div>

            <!-- Переключатель Виджет: Карта / Список -->
            <div class="ozon-view-tabs">
              <span style="font-size:11px; font-weight:600; color:#6b7280;">Способ выбора:</span>
              <div class="ozon-tab-btns">
                <button type="button" class="ozon-tab-btn active" id="ozonTabMap">Карта ПВЗ</button>
                <button type="button" class="ozon-tab-btn" id="ozonTabList">Списком</button>
              </div>
            </div>

            <!-- Контейнер интерактивной карты -->
            <div class="ozon-map-wrap" id="ozonMapWrap">
              <div id="ozonMap"></div>
            </div>

            <!-- Контейнер списка -->
            <div class="ozon-list-wrap" id="ozonListWrap" style="display:none;">
              <div class="ozon-pvz-list" id="ozonPvzList"></div>
            </div>

            <!-- Выбранный ПВЗ -->
            <div class="ozon-selected-box" id="ozonSelectedPvzBox" style="display:none; margin-top:10px;">
              <div id="ozonSelectedPvzText"></div>
            </div>
            <input type="hidden" id="ozonSelectedPvzInput" name="pvzAddress">
            <input type="hidden" id="ozonSelectedPvzIdInput" name="pvzId">
          </div>

          <!-- Кнопка оплаты через CloudPayments -->
          <div style="margin-top:20px;">
            <p class="ozon-status-msg" id="ozonFormStatus"></p>
            <button type="submit" class="btn-ozon btn-block" id="ozonConfirmBtn" style="margin-top:8px;">
              Оплатить онлайн картой / СБП
            </button>
            <div style="display:flex; align-items:center; justify-content:center; gap:6px; margin-top:8px; font-size:11px; color:#9ca3af;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
              <span>Безопасная оплата через <strong>CloudPayments</strong> · Карты РФ, СБП, Mir Pay</span>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://widget.cloudpayments.ru/bundles/cloudpayments.js"></script>
  <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU" type="text/javascript"></script>
  <script src="js/ozon-delivery.js?v=5"></script>
</body>
</html>
