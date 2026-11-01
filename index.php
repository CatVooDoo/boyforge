<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/router.php';
require_once __DIR__ . '/includes/slider.php';

try {
    $popStmt = $pdo->query("SELECT * FROM products WHERE is_active = 1 AND is_popular = 1 ORDER BY sort_order ASC, id ASC");
    $popularProducts = $popStmt->fetchAll();
} catch (Exception $e) {
    $popularProducts = [];
}

$sliderSlides = getSliderSlidesByDevice($pdo, true);

$pageTitle = 'BOYFORGE — кузница стильной одежды';
$bodyClass = 'page-home';

require __DIR__ . '/includes/components/header.php';
?>

<main>

    <!-- ===== 1. HERO — слайдер (Десктоп + Мобильная версия) ===== -->
    <section class="hero-slider" aria-label="BOYFORGE — кузница стильной одежды">
      <div class="hero-track">
        <?php foreach ($sliderSlides as $device => $deviceSlides): ?>
          <?php foreach ($deviceSlides as $idx => $slide):
            $sImg = htmlspecialchars('/' . ltrim((string)$slide['image_path'], '/'), ENT_QUOTES, 'UTF-8');
            $sAlt = htmlspecialchars((string)($slide['alt_text'] ?: ('Слайд ' . ($idx + 1))), ENT_QUOTES, 'UTF-8');
          ?>
          <div class="hero-slide hero-slide--<?= $device ?><?= $idx === 0 ? ' is-active' : '' ?>" data-device="<?= $device ?>" aria-label="<?= $sAlt ?>">
            <picture>
              <source media="<?= $device === 'mobile' ? '(max-width: 767px)' : '(min-width: 768px)' ?>" srcset="<?= $sImg ?>">
              <img class="hero-image" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="<?= $sAlt ?>" decoding="async"<?= $idx === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
            </picture>
          </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>

      <div class="hero-slider-content container">
        <div class="eyebrow">КУЗНИЦА ТВОЕГО СТИЛЯ</div>
        <a href="/catalog" class="hero-btn">Перейти в каталог</a>
      </div>

      <button class="hero-arrow hero-prev" hidden aria-label="Предыдущий слайд">&#8249;</button>
      <button class="hero-arrow hero-next" hidden aria-label="Следующий слайд">&#8250;</button>
      <div class="hero-dots" hidden role="group" aria-label="Слайды"></div>
    </section>

     <!-- ===== 2. ПОПУЛЯРНЫЕ ПОЗИЦИИ ===== -->
    <section class="container section">
      <div class="mini-head reveal">
        <h2>Популярные позиции</h2>
        <div class="sub">То, что разбирают первым</div>
      </div>

      <div class="grid-4" id="popularGrid">
        <?php foreach ($popularProducts as $p): ?>
          <?php
            $pId = (int)$p['id'];
            $pName = htmlspecialchars($p['name']);
            $pPrice = htmlspecialchars($p['price']);
            $pImgRaw = $p['img'] ?: '';
            $pImg = htmlspecialchars($pImgRaw !== '' ? '/' . ltrim($pImgRaw, '/') : '');
            $pTags = json_decode($p['tags'] ?? '[]', true) ?: [];
            $cardBadges = [];
            foreach ($pTags as $t) {
                $t = trim((string)$t);
                if ($t === '' || preg_match('/^[A-Za-z0-9]+[–\-][A-Za-z0-9]+$/u', $t)) continue;
                $cardBadges[] = $t;
            }
          ?>
          <a href="<?= productUrl($pId, $p['slug'] ?? null) ?>" class="card reveal">
            <div class="card-img">
              <img src="<?= $pImg ?>" alt="<?= $pName ?>" loading="lazy"
                   onerror="this.style.display='none';this.parentElement.classList.add('ph--empty');this.parentElement.setAttribute('data-label','<?= addslashes($pName) ?>');">
              <?php if (!empty($cardBadges)): ?>
                <div class="card-badges">
                  <?php foreach (array_slice($cardBadges, 0, 2) as $badgeItem): ?>
                    <span class="card-badge"><?= htmlspecialchars($badgeItem) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="card-info">
              <div class="card-title"><?= $pName ?></div>
              <div class="card-price"><?= $pPrice ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <a href="/catalog" class="btn-outline reveal">Весь каталог</a>
    </section>

    <!-- ===== 4. НАВИГАЦИОННЫЕ ПЛИТКИ ===== -->
    <section class="container section">
      <div class="cat-grid">
        <a href="/about" class="cat-tile reveal">
          <img src="/images/tile-about.jpg" alt="О бренде" loading="lazy">
          <div class="cat-tile-label"><span>О бренде</span></div>
        </a>
        <a href="/delivery" class="cat-tile reveal">
          <img src="/images/tile-delivery.jpg" alt="Доставка" loading="lazy">
          <div class="cat-tile-label"><span>Доставка</span></div>
        </a>
        <a href="/reviews" class="cat-tile reveal">
          <img src="/images/tile-reviews.jpg" alt="Отзывы" loading="lazy">
          <div class="cat-tile-label"><span>Отзывы</span></div>
        </a>
        <a href="/returns" class="cat-tile reveal">
          <img src="/images/tile-returns.jpg" alt="Возврат" loading="lazy">
          <div class="cat-tile-label"><span>Возврат</span></div>
        </a>
      </div>
    </section>

    <!-- ===== 6. ФОРМА ===== -->
    <section class="ask-section reveal">
      <div class="ask-inner">
        <h2>Ждём ваших вопросов!</h2>
        <p>Напишите ваш ник в Telegram и вопрос.</p>

        <form class="ask-form" id="contactForm" novalidate>
          <label for="tgName">Ваш ник в Telegram</label>
          <input type="text" id="tgName" name="tg" placeholder="@username" autocomplete="off">

          <label for="question">Ваш вопрос</label>
          <textarea id="question" name="question" placeholder="Например: какой размер выбрать при росте 180?"></textarea>

          <button type="submit">Отправить</button>
          <p class="form-status" id="formStatus" role="status" aria-live="polite"></p>
        </form>
      </div>
    </section>

  </main>

  <!-- ===== МОДАЛКА ГАЛЕРЕИ ===== -->
  <div class="gallery-modal" id="galleryModal" aria-hidden="true" role="dialog" aria-modal="true">
    <button class="gallery-modal-close" aria-label="Закрыть">&times;</button>
    <button class="gallery-modal-arrow gm-prev" aria-label="Предыдущее">&#8249;</button>
    <img class="gallery-modal-img" id="galleryModalImg" src="" alt="Фото">
    <button class="gallery-modal-arrow gm-next" aria-label="Следующее">&#8250;</button>
  </div>

  <?php require __DIR__ . '/includes/components/footer.php'; ?>
