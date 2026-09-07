<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

$catFilter = trim((string)($_GET['cat'] ?? 'all'));
$sort = trim((string)($_GET['sort'] ?? 'pop'));

// Получаем активные категории из таблицы categories
$catStmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
$categoriesList = $catStmt->fetchAll();

// Формируем запрос на получение товаров
$sql = "SELECT * FROM products WHERE is_active = 1";
$params = [];

if ($catFilter !== '' && $catFilter !== 'all') {
    $sql .= " AND (cat_id = :cat OR cat = :cat)";
    $params[':cat'] = $catFilter;
}

switch ($sort) {
    case 'cheap':
        $sql .= " ORDER BY price_numeric ASC, id ASC";
        break;
    case 'exp':
        $sql .= " ORDER BY price_numeric DESC, id ASC";
        break;
    case 'new':
        $sql .= " ORDER BY id DESC";
        break;
    default:
        $sql .= " ORDER BY sort_order ASC, id ASC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$totalCount = count($products);

function pluralizeGoods(int $n): string {
    $m10 = $n % 10;
    $m100 = $n % 100;
    if ($m10 === 1 && $m100 !== 11) return 'товар';
    if ($m10 >= 2 && $m10 <= 4 && ($m100 < 10 || $m100 >= 20)) return 'товара';
    return 'товаров';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Каталог — BOYFORGE</title>
  <meta name="description" content="Каталог BOYFORGE: одежда с авторскими принтами. Пошив в России, DTF-печать. Заказ через Telegram.">
  <link rel="icon" href="images/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=12">
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
          <span class="catalog-count" id="catalogCount"><?= $totalCount ?> <?= pluralizeGoods($totalCount) ?></span>
          <div class="sort-field">
            <span>Сортировка</span>
            <select id="sortSelect" aria-label="Сортировка" onchange="applyFilter('sort', this.value)">
              <option value="pop" <?= $sort === 'pop' ? 'selected' : '' ?>>По умолчанию</option>
              <option value="cheap" <?= $sort === 'cheap' ? 'selected' : '' ?>>Сначала дешевле</option>
              <option value="exp" <?= $sort === 'exp' ? 'selected' : '' ?>>Сначала дороже</option>
              <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Сначала новинки</option>
            </select>
          </div>
        </div>
      </div>

      <!-- фильтры-чипы по категориям -->
      <div class="chips" role="group" aria-label="Категории">
        <a href="catalog.php?cat=all&sort=<?= urlencode($sort) ?>" 
           class="chip <?= ($catFilter === 'all' || $catFilter === '') ? 'is-active' : '' ?>">Все</a>
        <?php foreach ($categoriesList as $c): ?>
          <?php
            $catSlug = $c['slug'];
            $isActiveChip = ($catFilter === $catSlug || $catFilter === $c['name']);
          ?>
          <a href="catalog.php?cat=<?= urlencode($catSlug) ?>&sort=<?= urlencode($sort) ?>" 
             class="chip <?= $isActiveChip ? 'is-active' : '' ?>">
             <?= htmlspecialchars($c['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- сетка товаров (вывод напрямую из базы данных MariaDB) -->
      <?php if (!empty($products)): ?>
        <div class="catalog-grid" id="catalogGrid">
          <?php foreach ($products as $p): ?>
            <?php
              $pId = (int)$p['id'];
              $pName = htmlspecialchars($p['name']);
              $pPrice = htmlspecialchars($p['price']);
              $pImg = htmlspecialchars($p['img'] ?: '');
              $tags = json_decode($p['tags'] ?? '[]', true);
              if (!is_array($tags)) $tags = [];
            ?>
            <a href="product.php?id=<?= $pId ?>" class="card card-in">
              <div class="card-img">
                <img src="<?= $pImg ?>" alt="<?= $pName ?>" loading="lazy"
                     onerror="this.style.display='none';this.parentElement.classList.add('ph--empty');this.parentElement.setAttribute('data-label','<?= addslashes($pName) ?>');">
                <?php if (in_array('Хит', $tags, true)): ?>
                  <span class="badge-hit">хит</span>
                <?php endif; ?>
                <?php if (in_array('Новая коллекция', $tags, true)): ?>
                  <span class="badge-new-collection">новая коллекция</span>
                <?php elseif (in_array('Новинка', $tags, true) || in_array('Новая', $tags, true)): ?>
                  <span class="badge-new">новинка</span>
                <?php endif; ?>
              </div>
              <div class="card-info">
                <div class="card-title"><?= $pName ?></div>
                <div class="card-price"><?= $pPrice ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <!-- пустое состояние -->
        <div class="empty-state" id="emptyState">
          <div class="empty-title">Ничего не найдено</div>
          <p class="empty-sub">В этой категории пока нет товаров. Загляните в другую или напишите нам — подберём под вас.</p>
          <a href="catalog.php" class="btn-outline empty-back">Сбросить фильтр</a>
        </div>
      <?php endif; ?>

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

  <script>
    function applyFilter(key, val) {
      const url = new URL(window.location.href);
      url.searchParams.set(key, val);
      window.location.href = url.toString();
    }
  </script>
  <script src="js/main.js?v=12"></script>
</body>
</html>
