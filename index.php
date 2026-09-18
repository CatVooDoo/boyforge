<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';

try {
    $popStmt = $pdo->query("SELECT * FROM products WHERE is_active = 1 AND is_popular = 1 ORDER BY sort_order ASC, id ASC");
    $popularProducts = $popStmt->fetchAll();
} catch (Exception $e) {
    $popularProducts = [];
}
$pageTitle = 'BOYFORGE — кузница стильной одежды';
$bodyClass = 'page-home';

require __DIR__ . '/includes/components/header.php';
?>

<main>

    <!-- ===== 1. HERO — видео-слайдер (3 слайда × 2 формата) ===== -->
    <section class="hero-slider" aria-label="BOYFORGE — кузница стильной одежды">
      <div class="hero-track">

                <!-- Слайд 3 -->
        <a href="product.php?id=3" class="hero-slide" aria-label="Смотреть товар">
          <video class="hero-video hero-video--desktop" autoplay muted playsinline preload="auto" poster="images/hero-3.jpg">
            <source src="videos/hero-3-desktop.mp4" type="video/mp4">
          </video>
          <video class="hero-video hero-video--mobile" autoplay muted playsinline preload="auto" poster="images/hero-3-mobile.jpg">
            <source src="videos/hero-3-mobile.mp4" type="video/mp4">
          </video>
        </a>

        <!-- Слайд 2 -->
        <a href="product.php?id=2" class="hero-slide" aria-label="Смотреть товар">
          <video class="hero-video hero-video--desktop" autoplay muted playsinline preload="auto" poster="images/hero-2.jpg">
            <source src="videos/hero-2-desktop.mp4" type="video/mp4">
          </video>
          <video class="hero-video hero-video--mobile" autoplay muted playsinline preload="auto" poster="images/hero-2-mobile.jpg">
            <source src="videos/hero-2-mobile.mp4" type="video/mp4">
          </video>
        </a>

        <!-- Слайд 1 -->
        <a href="product.php?id=1" class="hero-slide is-active" aria-label="Смотреть товар">
          <video class="hero-video hero-video--desktop" autoplay muted playsinline preload="auto" poster="images/hero-1.jpg">
            <source src="videos/hero-1-desktop.mp4" type="video/mp4">
          </video>
          <video class="hero-video hero-video--mobile" autoplay muted playsinline preload="auto" poster="images/hero-1-mobile.jpg">
            <source src="videos/hero-1-mobile.mp4" type="video/mp4">
          </video>
        </a>

      </div>

      <div class="hero-slider-content container">
        <div class="eyebrow">КУЗНИЦА ТВОЕГО СТИЛЯ</div>
        <a href="catalog.php" class="hero-btn">Перейти в каталог</a>
      </div>

      <button class="hero-arrow hero-prev" aria-label="Предыдущий слайд">&#8249;</button>
      <button class="hero-arrow hero-next" aria-label="Следующий слайд">&#8250;</button>
      <div class="hero-dots" role="tablist" aria-label="Слайды"></div>
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
            $pImg = htmlspecialchars($p['img']);
            $pTags = json_decode($p['tags'] ?? '[]', true) ?: [];
            $cardBadges = [];
            foreach ($pTags as $t) {
                $t = trim((string)$t);
                if ($t === '' || preg_match('/^[A-Za-z0-9]+[–\-][A-Za-z0-9]+$/u', $t)) continue;
                $cardBadges[] = $t;
            }
          ?>
          <a href="product.php?id=<?= $pId ?>" class="card reveal">
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

      <a href="catalog.php" class="btn-outline reveal">Весь каталог</a>
    </section>

    <!-- ===== 3. О БРЕНДЕ — короткий блок ===== -->
    <section class="section theme-dark">
      <div class="container">
        <div class="mini-head reveal">
          <h2>Любое дело начинается с тишины</h2>
        </div>
        <div class="brand-intro reveal">
          <p>Как и раньше важны труд, характер и уважение к тому, для кого это создаётся. Чтобы с первого взгляда вещь становилась любимой в гардеробе.</p>
          <a href="about.php" class="btn-outline">О бренде</a>
        </div>
      </div>
    </section>

    <!-- ===== 4. НАВИГАЦИОННЫЕ ПЛИТКИ ===== -->
    <section class="container section">
      <div class="cat-grid">
        <a href="about.php" class="cat-tile reveal">
          <img src="images/tile-about.jpg" alt="О бренде" loading="lazy">
          <div class="cat-tile-label"><span>О бренде</span></div>
        </a>
        <a href="delivery.php" class="cat-tile reveal">
          <img src="images/tile-delivery.jpg" alt="Доставка" loading="lazy">
          <div class="cat-tile-label"><span>Доставка</span></div>
        </a>
        <a href="reviews.php" class="cat-tile reveal">
          <img src="images/tile-reviews.jpg" alt="Отзывы" loading="lazy">
          <div class="cat-tile-label"><span>Отзывы</span></div>
        </a>
        <a href="returns.php" class="cat-tile reveal">
          <img src="images/tile-returns.jpg" alt="Возврат" loading="lazy">
          <div class="cat-tile-label"><span>Возврат</span></div>
        </a>
      </div>
    </section>

    <!-- ===== 5. ГАЛЕРЕЯ ===== -->
    <section class="container section">
      <div class="mini-head reveal">
        <h2>Наши покупатели</h2>
        <div class="sub">Оставив отзыв, ты можешь попасть сюда</div>
      </div>

      <div class="gallery-marquee reveal" id="galleryMarquee">
        <div class="gallery-track" id="galleryTrack">
          <button class="gallery-item" data-src="images/g1.jpg"><img src="images/g1.jpg" alt="Фото 1" loading="lazy"></button>
          <button class="gallery-item" data-src="images/g2.jpg"><img src="images/g2.jpg" alt="Фото 2" loading="lazy"></button>
          <button class="gallery-item" data-src="images/g3.jpg"><img src="images/g3.jpg" alt="Фото 3" loading="lazy"></button>
          <button class="gallery-item" data-src="images/g4.jpg"><img src="images/g4.jpg" alt="Фото 4" loading="lazy"></button>
          <button class="gallery-item" data-src="images/g5.jpg"><img src="images/g5.jpg" alt="Фото 5" loading="lazy"></button>
          <button class="gallery-item" data-src="images/g6.jpg"><img src="images/g6.jpg" alt="Фото 6" loading="lazy"></button>
          <button class="gallery-item" data-src="images/g7.jpg"><img src="images/g7.jpg" alt="Фото 7" loading="lazy"></button>
          <button class="gallery-item" data-src="images/g8.jpg"><img src="images/g8.jpg" alt="Фото 8" loading="lazy"></button>
          <button class="gallery-item" data-src="images/g9.jpg"><img src="images/g9.jpg" alt="Фото 9" loading="lazy"></button>
        </div>
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
