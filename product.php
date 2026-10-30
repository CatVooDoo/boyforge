<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/router.php';

handleLegacyUrls();

$route = parseRoute();
if ($route['type'] === '404') {
    show404();
}

$slug = $_GET['slug'] ?? null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
    
    $imgs = array_map(function($src) { return '/' . ltrim($src, '/'); }, $imgs);

    $specs = json_decode($product['specs'] ?? '[]', true);
    if (!is_array($specs)) {
        $specs = [];
    }

    $tgBase = $product['tg_link'] ?: ('https://telegram.me/theboyforge?text=' . rawurlencode('Здравствуйте! Хочу заказать: ' . $product['name']));

    $relStmt = $pdo->prepare("SELECT * FROM products WHERE is_active = 1 AND cat_id = :cat_id AND id != :id ORDER BY sort_order ASC, id ASC LIMIT 4");
    $relStmt->execute([
        ':cat_id' => $product['cat_id'],
        ':id' => $id
    ]);
    $relatedProducts = $relStmt->fetchAll();

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
      data-product-price-numeric="<?= (int)($product['price_numeric'] ?? 0) ?>"
      data-product-url="<?= htmlspecialchars(productUrl((int)$product['id'], $product['slug'] ?? null)) ?>"
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
          <div class="sizes" data-unavailable-male="<?= htmlspecialchars(implode(',', $unavailableSizes['Мужской'] ?? [])) ?>" data-unavailable-female="<?= htmlspecialchars(implode(',', $unavailableSizes['Женский'] ?? [])) ?>">
            <?php foreach (allSizes() as $sz): ?>
              <?php $isOut = in_array($sz, $unavailableSizes['Мужской'] ?? [], true) && in_array($sz, $unavailableSizes['Женский'] ?? [], true); ?>
              <button type="button"<?= $isOut ? ' class="size-out" disabled aria-disabled="true" title="Нет в наличии"' : '' ?>><?= htmlspecialchars($sz) ?></button>
            <?php endforeach; ?>
          </div>

          <div class="product-actions">
            <button type="button" class="btn-primary btn-block" id="addToCartBtn">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; flex-shrink:0;" aria-hidden="true">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <path d="M16 10a4 4 0 0 1-8 0"></path>
              </svg>
              <span id="addToCartBtnText">Добавить в корзину</span>
            </button>
            <button type="button" class="btn-outline btn-block" id="orderModalBtn">
              Купить сразу с доставкой
            </button>
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
                <p>Выберите 5Post или СДЭК и удобный пункт выдачи на карте при оформлении заказа.
                Стоимость и срок доставки СДЭК рассчитываются после выбора пункта; доставка оплачивается вместе с товаром.
                Оплата — онлайн картой или СБП, электронный чек придёт на email. Доставку за пределы России согласуем в Telegram.</p>
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

  <?php require __DIR__ . '/includes/components/footer.php'; ?>

