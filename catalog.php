<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/router.php';

// Обрабатываем старые URL (301 редирект если нужно)
handleLegacyUrls();

$route = parseRoute();
if ($route['type'] === '404') {
    show404();
}

$catFilter = trim((string)($_GET['cat'] ?? 'all'));
$sort = trim((string)($_GET['sort'] ?? 'pop'));

// Получаем активные категории из таблицы categories
$catStmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
$categoriesList = $catStmt->fetchAll();

// Формируем запрос на получение товаров
$sql = "SELECT * FROM products WHERE is_active = 1";
$params = [];

if ($catFilter !== '' && $catFilter !== 'all') {
    $sql .= " AND (cat_id = :cat_slug OR cat = :cat_name)";
    $params[':cat_slug'] = $catFilter;
    $params[':cat_name'] = $catFilter;
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
$pageTitle = 'Каталог — BOYFORGE';
$bodyClass = 'page-catalog';

require __DIR__ . '/includes/components/header.php';
?>

<main>
    <div class="container">

      <!-- хлебные крошки -->
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="/">Главная</a> <span>/</span> <span>Каталог</span>
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
        <a href="/catalog<?= $sort !== 'pop' ? '?sort=' . urlencode($sort) : '' ?>" 
           class="chip <?= ($catFilter === 'all' || $catFilter === '') ? 'is-active' : '' ?>">Все</a>
        <?php foreach ($categoriesList as $c): ?>
          <?php
            $catSlug = $c['slug'];
            $isActiveChip = ($catFilter === $catSlug || $catFilter === $c['name']);
          ?>
          <a href="<?= categoryUrl($catSlug, $sort) ?>" 
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
              $cardBadges = [];
              foreach ($tags as $t) {
                  $t = trim((string)$t);
                  if ($t === '' || preg_match('/^[A-Za-z0-9]+[–\-][A-Za-z0-9]+$/u', $t)) {
                      continue;
                  }
                  $cardBadges[] = $t;
              }
            ?>
            <a href="<?= productUrl($pId, $p['slug'] ?? null) ?>" class="card card-in">
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
      <?php else: ?>
        <!-- пустое состояние -->
        <div class="empty-state" id="emptyState">
          <div class="empty-title">Ничего не найдено</div>
          <p class="empty-sub">В этой категории пока нет товаров. Загляните в другую или напишите нам — подберём под вас.</p>
          <a href="/catalog" class="btn-outline empty-back">Сбросить фильтр</a>
        </div>
      <?php endif; ?>

    </div>
  </main>

  <?php require __DIR__ . '/includes/components/footer.php'; ?>
