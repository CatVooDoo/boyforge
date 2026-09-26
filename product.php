<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/router.php';

// Обрабатываем старые URL (301 редирект если нужно)
handleLegacyUrls();

$route = parseRoute();
if ($route['type'] === '404') {
    show404();
}

$slug = $_GET['slug'] ?? null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Если есть slug, получаем ID товара
if ($slug && !$id) {
    $stmt = $pdo->prepare("SELECT id FROM products WHERE slug = :slug AND is_active = 1 LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $productRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $id = $productRow ? (int)$productRow['id'] : 0;
}
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
    $pImgRaw = $product['img'] ?: '';
    $pImg = htmlspecialchars($pImgRaw !== '' ? '/' . ltrim($pImgRaw, '/') : '');

    $imgs = json_decode($product['imgs'] ?? '[]', true);
    if (!is_array($imgs) || empty($imgs)) {
        $imgs = array_values(array_filter([$product['img'] ?: '']));
    } else {
        $imgs = array_values(array_filter($imgs));
    }
    if (empty($imgs) && !empty($product['img'])) {
        $imgs = [$product['img']];
    }
    
    // Add leading slash for absolute path resolution from /product/...
    $imgs = array_map(function($src) { return '/' . ltrim($src, '/'); }, $imgs);

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
    // (Убрано в рамках рефакторинга: передаем через data-* атрибуты)
}

$pageTitle = $found ? $pName . ' · BOYFORGE' : 'Товар не найден · BOYFORGE';
$bodyClass = 'page-product';

require __DIR__ . '/includes/components/header.php';
?>

  <main class="container"
    <?php if ($found): ?>
      data-product-id="<?= (int)$product['id'] ?>"
      data-product-name="<?= htmlspecialchars($product['name']) ?>"
      data-product-price="<?= htmlspecialchars($product['price']) ?>"
      data-product-tg-base="<?= htmlspecialchars($tgBase) ?>"
    <?php endif; ?>>
    <?php if (!$found): ?>
      <!-- Товар не найден -->
      <div class="empty-state" style="padding:120px 20px">
        <div class="empty-title">Товар не найден</div>
        <p class="empty-sub">Возможно, позиция была снята с публикации или ссылка устарела. Загляните в каталог — там актуальные позиции.</p>
        <a class="btn-outline empty-back" href="/catalog">В каталог</a>
      </div>
    <?php else: ?>

      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a> <span>/</span>
        <a href="/catalog">Каталог</a> <span>/</span>
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
          <?php $unavailableSizes = productUnavailableSizes($product['sizes'] ?? null); ?>
          <div class="sizes" data-unavailable="<?= htmlspecialchars(implode(',', $unavailableSizes)) ?>">
            <?php foreach (allSizes() as $sz): ?>
              <?php $isOut = in_array($sz, $unavailableSizes, true); ?>
              <button type="button"<?= $isOut ? ' class="size-out" disabled aria-disabled="true" title="Нет в наличии"' : '' ?>><?= htmlspecialchars($sz) ?></button>
            <?php endforeach; ?>
          </div>

          <div class="product-actions">
            <button type="button" class="btn-primary btn-block" id="orderModalBtn">
              Оформить заказ с доставкой
            </button>
            <!--
            <a href="<?= htmlspecialchars($tgBase) ?>" 
               class="btn-outline btn-block" id="orderBtn" 
               data-tg-base="<?= htmlspecialchars($tgBase) ?>"
               target="_blank" rel="noopener">
              Заказать в Telegram
            </a>
            -->
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
        <section class="container related-section">
          <div class="related-head">
            <h2 class="related-title">Похожие товары</h2>
          </div>
          <div class="grid-4 related-grid" id="relatedGrid">
            <?php foreach ($relatedProducts as $r): ?>
              <?php
                $rId = (int)$r['id'];
                $rName = htmlspecialchars($r['name']);
                $rPrice = htmlspecialchars($r['price']);
                $rImgRaw = $r['img'] ?: '';
                $rImg = htmlspecialchars($rImgRaw !== '' ? '/' . ltrim($rImgRaw, '/') : '');
                $rTags = json_decode($r['tags'] ?? '[]', true) ?: [];
                $rBadges = [];
                foreach ($rTags as $t) {
                    $t = trim((string)$t);
                    if ($t === '' || preg_match('/^[A-Za-z0-9]+[–\-][A-Za-z0-9]+$/u', $t)) continue;
                    $rBadges[] = $t;
                }
              ?>
              <a href="<?= productUrl($rId, $r['slug'] ?? null) ?>" class="card card-in related-card">
                <div class="card-img">
                  <img src="<?= $rImg ?>" alt="<?= $rName ?>" loading="lazy"
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
                  <div class="card-price"><?= $rPrice ?></div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

    <?php endif; ?>
  </main>

  

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
                <label for="orderEmail">Email <span style="color:#ef4444;">*</span></label>
                <input type="email" id="orderEmail" class="ozon-input" placeholder="mail@example.com" required autocomplete="email">
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
              <div class="fivepost-selected-details">
                <div class="fivepost-selected-header">
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
            <button type="submit" class="btn-primary btn-block" id="orderConfirmBtn">
              Оплатить онлайн картой / СБП
            </button>

            <div class="order-agree-wrap">
              <label class="order-agree-label" for="orderPolicyAgree">
                <input type="checkbox" id="orderPolicyAgree" class="order-agree-checkbox" required>
                <span class="order-agree-text">
                  Нажимая кнопку, Вы соглашаетесь с <a href="/terms" target="_blank" rel="noopener">Правилами</a> и <a href="/policy" target="_blank" rel="noopener">политикой конфиденциальности</a> Компании.
                </span>
              </label>
            </div>

            <div style="display:flex; align-items:center; justify-content:center; gap:6px; margin-top:10px; font-size:11px; color:#9ca3af;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
              <span>Безопасная оплата через <strong>ЮKassa</strong> · Карты РФ, СБП, Mir Pay, SberPay, Tinkoff</span>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php
    $yandexApiKey = env_get('YANDEX_MAPS_API_KEY') ?: '';
  ?>
  <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=<?= htmlspecialchars($yandexApiKey) ?>" defer></script>
  <script src="https://yookassa.ru/checkout-widget/v1/checkout-widget.js"></script>
  <?php require __DIR__ . '/includes/components/footer.php'; ?>

