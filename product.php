<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND is_active = 1 LIMIT 1");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();
}

$found = ($product !== false && $product !== null);

if ($found) {
    $pName = htmlspecialchars($product['name']);
    $pPrice = htmlspecialchars($product['price']);
    $pSub = htmlspecialchars($product['sub'] ?: 'Футболка · авторский принт');
    $pDesc = htmlspecialchars($product['description'] ?: 'Плотный хлопок, DTF-печать — мягкий стойкий принт, который не трескается со временем. Уход: стирка при 30°, без отбеливателя.');
    $pImg = htmlspecialchars($product['img'] ?: '');

    $imgs = json_decode($product['imgs'] ?? '[]', true);
    if (!is_array($imgs) || empty($imgs)) {
        $imgs = array_values(array_filter([$product['img'] ?: '']));
    } else {
        $imgs = array_values(array_filter($imgs));
    }
    if (empty($imgs) && !empty($product['img'])) {
        $imgs = [$product['img']];
    }

    $specs = json_decode($product['specs'] ?? '[]', true);
    if (!is_array($specs)) {
        $specs = [];
    }

    $tgBase = $product['tg_link'] ?: ('https://telegram.me/theboyforge?text=' . rawurlencode('Здравствуйте! Хочу заказать: ' . $product['name']));

    // Получаем похожие товары из базы данных
    $relStmt = $pdo->prepare("SELECT * FROM products WHERE is_active = 1 AND cat_id = :cat_id AND id != :id ORDER BY sort_order ASC, id ASC LIMIT 4");
    $relStmt->execute([
        ':cat_id' => $product['cat_id'],
        ':id' => $id
    ]);
    $relatedProducts = $relStmt->fetchAll();

    // Подготавливаем объект для клиента (галерея, выбор размера и заказ)
    $clientProduct = [
        'id' => (int)$product['id'],
        'catId' => $product['cat_id'],
        'cat' => $product['cat'],
        'name' => $product['name'],
        'price' => $product['price'],
        'img' => $product['img'],
        'imgs' => $imgs,
        'sub' => $product['sub'],
        'tags' => json_decode($product['tags'] ?? '[]', true) ?: [],
        'desc' => $product['description'],
        'specs' => $specs,
        'tg' => $tgBase
    ];
}

$env = [];
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $envLine) {
        $envLine = trim($envLine);
        if ($envLine === '' || $envLine[0] === '#') continue;
        if (strpos($envLine, '=') !== false) {
            list($k, $v) = explode('=', $envLine, 2);
            $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }
}
$fivepostApiKey = $env['5POST_API_KEY'] ?? '5cdc4b25-4fd6-40ac-b7f7-d4d55cbfcc6a';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title id="metaTitle"><?= $found ? $pName . ' · BOYFORGE' : 'Товар не найден · BOYFORGE' ?></title>
  <link rel="icon" href="images/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
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
    <?php if (!$found): ?>
      <!-- Товар не найден -->
      <div class="empty-state" style="padding:120px 20px">
        <div class="empty-title">Товар не найден</div>
        <p class="empty-sub">Возможно, позиция была снята с публикации или ссылка устарела. Загляните в каталог — там актуальные позиции.</p>
        <a class="btn-outline empty-back" href="catalog.php">В каталог</a>
      </div>
    <?php else: ?>

      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="index.php">Главная</a> <span>/</span>
        <a href="catalog.php">Каталог</a> <span>/</span>
        <span id="crumbName"><?= $pName ?></span>
      </nav>

      <div class="product-layout">

        <!-- ГАЛЕРЕЯ (вывод из БД) -->
        <?php $hasMultipleImgs = count($imgs) > 1; ?>
        <div class="product-gallery <?= !$hasMultipleImgs ? 'no-thumbs' : '' ?>">
          <div class="thumbs" id="thumbs" <?= !$hasMultipleImgs ? 'style="display:none;"' : '' ?>>
            <?php if ($hasMultipleImgs): ?>
              <?php foreach ($imgs as $i => $src): ?>
                <img src="<?= htmlspecialchars($src) ?>" 
                     alt="<?= $pName ?> — фото <?= $i + 1 ?>" 
                     class="<?= $i === 0 ? 'active' : '' ?>" 
                     data-i="<?= $i ?>">
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="main-photo">
            <img src="<?= htmlspecialchars($imgs[0] ?? $pImg) ?>" alt="<?= $pName ?> — фото 1" id="galleryMain">
          </div>

          <div class="gallery-dots" id="galleryDots" <?= !$hasMultipleImgs ? 'style="display:none;"' : '' ?>>
            <?php if ($hasMultipleImgs): ?>
              <?php foreach ($imgs as $i => $src): ?>
                <button type="button" aria-label="Фото <?= $i + 1 ?>" class="<?= $i === 0 ? 'active' : '' ?>" data-i="<?= $i ?>"></button>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- ИНФО О ТОВАРЕ (вывод из БД) -->
        <div class="product-info">
          <h1 id="prodName"><?= $pName ?></h1>
          <div class="product-art" id="prodArt"><?= $pSub ?></div>

          <div class="product-price">
            <span class="new" id="prodPrice"><?= $pPrice ?></span>
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
            <button type="button">S</button>
            <button type="button" class="active">M</button>
            <button type="button">L</button>
            <button type="button">XL</button>
            <button type="button">2XL</button>
          </div>

          <div class="product-actions">
            <button type="button" class="btn-primary btn-block" id="orderModalBtn">
              Оформить заказ с доставкой
            </button>
            <a href="<?= htmlspecialchars($tgBase) ?>" class="btn-outline btn-block" id="orderBtn" target="_blank" rel="noopener">
              Заказать в Telegram
            </a>
          </div>

          <div class="product-accordion">
            <div class="accordion-item open">
              <button class="accordion-head" type="button">Описание <span class="plus">+</span></button>
              <div class="accordion-body"><div class="accordion-body-inner">
                <p id="prodDesc"><?= nl2br($pDesc) ?></p>
              </div></div>
            </div>

            <div class="accordion-item">
              <button class="accordion-head" type="button">Характеристики <span class="plus">+</span></button>
              <div class="accordion-body"><div class="accordion-body-inner">
                <ul class="specs" id="prodSpecs">
                  <?php if (!empty($specs)): ?>
                    <?php foreach ($specs as $row): ?>
                      <li>
                        <span><?= htmlspecialchars((string)($row[0] ?? '')) ?></span>
                        <?= htmlspecialchars((string)($row[1] ?? '')) ?>
                      </li>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </ul>
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

      <!-- ПОХОЖИЕ ТОВАРЫ (вывод из БД) -->
      <?php if (!empty($relatedProducts)): ?>
        <div style="margin-top: 70px; padding-top: 40px; border-top: 1px solid var(--border);">
          <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 24px;">Похожие товары</h2>
          <div class="grid-4" id="relatedGrid">
            <?php foreach ($relatedProducts as $r): ?>
              <?php
                $rTags = json_decode($r['tags'] ?? '[]', true) ?: [];
                $rBadges = [];
                foreach ($rTags as $t) {
                    $t = trim((string)$t);
                    if ($t === '' || preg_match('/^[A-Za-z0-9]+[–\-][A-Za-z0-9]+$/u', $t)) continue;
                    $rBadges[] = $t;
                }
                $rName = htmlspecialchars($r['name']);
              ?>
              <a href="product.php?id=<?= (int)$r['id'] ?>" class="card">
                <div class="card-img">
                  <img src="<?= htmlspecialchars($r['img']) ?>" alt="<?= $rName ?>" loading="lazy"
                       onerror="this.style.display='none';this.parentElement.classList.add('ph--empty');this.parentElement.setAttribute('data-label','<?= addslashes($rName) ?>');">
                  <?php if (!empty($rBadges)): ?>
                    <div class="card-badges">
                      <?php foreach (array_slice($rBadges, 0, 2) as $badgeItem): ?>
                        <span class="card-badge"><?= htmlspecialchars($badgeItem) ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="card-info">
                  <div class="card-title"><?= $rName ?></div>
                  <div class="card-price"><?= htmlspecialchars($r['price']) ?></div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    <?php endif; ?>
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

  <!-- модалка увеличенного фото товара -->
  <div class="pv-modal" id="pvModal" aria-hidden="true">
    <button class="pv-modal-close" id="pvClose" aria-label="Закрыть">&times;</button>
    <button class="pv-modal-arrow pv-prev" id="pvPrev" aria-label="Предыдущее">&#8249;</button>
    <img class="pv-modal-img" id="pvImg" src="" alt="">
    <button class="pv-modal-arrow pv-next" id="pvNext" aria-label="Следующее">&#8250;</button>
  </div>

  <!-- ===== МОДАЛЬНОЕ ОКНО ОФОРМЛЕНИЯ ЗАКАЗА ===== -->
  <div class="order-modal ozon-modal" id="orderModal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="order-modal-dialog ozon-modal-dialog">
      <div class="order-modal-header ozon-modal-header">
        <h3 class="order-modal-title ozon-modal-title">Оформление заказа</h3>
        <button type="button" class="order-modal-close ozon-modal-close" id="orderModalClose" aria-label="Закрыть">&times;</button>
      </div>

      <div class="order-modal-body ozon-modal-body">
        <!-- Мини-превью выбранного товара -->
        <div class="ozon-prod-summary">
          <img class="ozon-prod-thumb" id="orderModalProdImg" src="" alt="Товар">
          <div class="ozon-prod-info">
            <div class="ozon-prod-name" id="orderModalProdName">Товар BOYFORGE</div>
            <div class="ozon-prod-price" id="orderModalProdPrice">—</div>
            <div class="ozon-prod-tags">
              <span class="ozon-pill" id="orderModalProdGender">Мужской</span>
              <span class="ozon-pill">Размер: <strong id="orderModalProdSize">M</strong></span>
            </div>
          </div>
        </div>

        <form id="orderForm" novalidate>
          <!-- 1. Контактные данные -->
          <div class="ozon-section">
            <div class="ozon-section-title">1. Контактные данные покупателя</div>
            <div class="ozon-field">
              <label for="orderFio">ФИО получателя <span style="color:#ef4444;">*</span></label>
              <input type="text" id="orderFio" class="ozon-input" placeholder="Иванов Иван Иванович" required autocomplete="name">
            </div>
            <div class="ozon-fields-grid">
              <div class="ozon-field">
                <label for="orderTg">Ваш Telegram (@username) <span style="color:#ef4444;">*</span></label>
                <input type="text" id="orderTg" class="ozon-input" placeholder="@username" required autocomplete="off">
              </div>
              <div class="ozon-field">
                <label for="orderPhone">Номер телефона <span style="color:#ef4444;">*</span></label>
                <input type="tel" id="orderPhone" class="ozon-input" placeholder="+7 (999) 000-00-00" required autocomplete="tel">
              </div>
            </div>
          </div>

          <!-- 2. Пункт выдачи 5Post -->
          <div class="ozon-section" style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between;">
              <div class="ozon-section-title">2. Доставка 5Post (Пятёрочка / Перекрёсток)</div>
            </div>

            <!-- Скрытые поля для данных выбранной точки 5Post -->
            <input type="hidden" id="fivepostPointId" name="fivepostPointId" value="">
            <input type="hidden" id="fivepostPointName" name="fivepostPointName" value="">
            <input type="hidden" id="fivepostPointAddress" name="fivepostPointAddress" value="">
            <input type="hidden" id="fivepostPointType" name="fivepostPointType" value="">
            <input type="hidden" id="fivepostPointDetails" name="fivepostPointDetails" value="">

            <!-- Карточка уже выбранного пункта выдачи -->
            <div class="fivepost-selected-card" id="fivepostSelectedCard" style="display:none;">
              <div class="fivepost-selected-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
              </div>
              <div class="fivepost-selected-details">
                <div class="fivepost-selected-header">
                  <span class="fivepost-selected-badge" id="fivepostPointTypeBadge">Постамат</span>
                  <strong class="fivepost-selected-name" id="fivepostPointNameDisplay">Пятёрочка</strong>
                </div>
                <div class="fivepost-selected-address" id="fivepostPointAddressDisplay">г. Пенза, ул. Примерная, д. 1</div>
                <div class="fivepost-selected-extra" id="fivepostPointExtraDisplay">Выдача заказа 5Post</div>
              </div>
              <button type="button" class="fivepost-change-btn" id="fivepostChangeBtn">Изменить</button>
            </div>

            <!-- Блок карты и поиска по городам -->
            <div class="fivepost-map-wrapper" id="fivepostMapWrapper">
              <!-- Поиск по городам и улицам -->
              <div class="bf-city-search-wrap">
                <div class="bf-city-search-box">
                  <svg class="bf-search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                  <input type="text" id="fivepostCitySearch" class="ozon-input bf-city-search-input" placeholder="Поиск по городу или улице (например, Пенза, Победы…)" autocomplete="off">
                  <button type="button" id="fivepostCityClear" class="bf-clear-btn" style="display:none;" aria-label="Очистить">&times;</button>
                </div>
                <div class="bf-city-chips" id="fivepostCityChips">
                  <span class="bf-city-chip active" data-city="Пенза">г. Пенза</span>
                </div>
              </div>

              <!-- Контейнер интерактивной Яндекс.Карты -->
              <div id="fivepostCustomMap" class="bf-custom-map">
                <div class="fivepost-map-loader" id="fivepostMapLoader">
                  <div class="fivepost-spinner"></div>
                  <span>Загрузка карты и точек выдачи…</span>
                </div>
              </div>
            </div>
          </div>

          <div style="margin-top:16px;">
            <p class="ozon-status-msg order-status-msg" id="orderFormStatus"></p>
            <button type="submit" class="btn-primary btn-block" id="orderConfirmBtn" style="margin-top:8px;">
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

  <?php if ($found): ?>
    <script>
      window.PRODUCT_DATA = <?= json_encode($clientProduct, JSON_UNESCAPED_UNICODE) ?>;
    </script>
  <?php endif; ?>

  <?php
    $yandexApiKey = $env['YANDEX_MAPS_API_KEY'] ?? getenv('YANDEX_MAPS_API_KEY') ?: '3612542e-8832-4f60-879c-72b492b06944';
  ?>
  <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=<?= htmlspecialchars($yandexApiKey) ?>"></script>
  <script src="js/product.js?v=<?= filemtime(__DIR__ . '/js/product.js') ?>"></script>
  <script src="js/main.js?v=23"></script>
  <script src="https://widget.cloudpayments.ru/bundles/cloudpayments.js"></script>
  <script src="js/checkout.js?v=<?= filemtime(__DIR__ . '/js/checkout.js') ?>"></script>
</body>
</html>
