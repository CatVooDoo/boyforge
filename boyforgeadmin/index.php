<?php
declare(strict_types=1);

$pageTitle = 'Список товаров';
require_once __DIR__ . '/includes/header.php';

$stmt = $pdo->query("SELECT * FROM products ORDER BY sort_order ASC, id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCount = count($products);
$activeCount = 0;
$hiddenCount = 0;
$categories = [];

foreach ($products as $p) {
    if ((int)$p['is_active'] === 1) {
        $activeCount++;
    } else {
        $hiddenCount++;
    }
    $cat = trim($p['cat'] ?: 'Без категории');
    if (!in_array($cat, $categories, true)) {
        $categories[] = $cat;
    }
}
?>

<div class="page-head">
  <div>
    <h1 class="page-title">Каталог товаров</h1>
    <div class="page-subtitle">Управление позициями на сайте, ценами и характеристиками</div>
  </div>

  <div style="display: flex; gap: 12px;">
    <a href="edit.php" class="btn btn-primary">
      <?= renderSvgIcon('plus') ?>
      <span>Добавить товар</span>
    </a>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon-wrapper">
      <?= renderSvgIcon('box') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value" id="statTotalCount"><?= $totalCount ?></span>
      <span class="stat-label">Всего товаров</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrapper" style="color: var(--accent-success);">
      <?= renderSvgIcon('eye') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value" id="statActiveCount"><?= $activeCount ?></span>
      <span class="stat-label">Активно на сайте</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrapper" style="color: var(--accent-danger);">
      <?= renderSvgIcon('eye-off') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value" id="statHiddenCount"><?= $hiddenCount ?></span>
      <span class="stat-label">Скрыто</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrapper">
      <?= renderSvgIcon('tag') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value"><?= count($categories) ?></span>
      <span class="stat-label">Категорий</span>
    </div>
  </div>
</div>

<div class="filter-card">
  <div class="search-box">
    <?= renderSvgIcon('search') ?>
    <input type="text" id="searchInput" class="search-input" placeholder="Поиск товара по названию...">
  </div>

  <div class="filter-group">
    <select id="filterCat" class="filter-select">
      <option value="all">Все категории</option>
      <?php foreach ($categories as $catItem): ?>
        <option value="<?= htmlspecialchars($catItem) ?>"><?= htmlspecialchars($catItem) ?></option>
      <?php endforeach; ?>
    </select>

    <select id="filterStatus" class="filter-select">
      <option value="all">Любой статус</option>
      <option value="active">Активные</option>
      <option value="inactive">Скрытые</option>
    </select>
  </div>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width: 70px;">Фото</th>
          <th>Название и описание</th>
          <th>Категория</th>
          <th>Цена</th>
          <th>Бейджи</th>
          <th style="text-align: center; width: 90px;">На сайте</th>
          <th style="text-align: right; width: 140px;">Действия</th>
        </tr>
      </thead>
      <tbody id="productsTableBody">
        <?php if (empty($products)): ?>
          <tr>
            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
              Товары ещё не добавлены. Нажмите «Добавить товар», чтобы создать первую позицию.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($products as $p): ?>
            <?php
              $id = (int)$p['id'];
              $name = htmlspecialchars($p['name']);
              $sub = htmlspecialchars($p['sub'] ?? '');
              $cat = htmlspecialchars($p['cat'] ?: 'Футболка');
              $price = htmlspecialchars($p['price'] ?: '0 ₽');
              $isActive = (int)$p['is_active'] === 1;
              $statusAttr = $isActive ? 'active' : 'inactive';
              
              $img = $p['img'] ?? '';
              $imgSrc = '';
              if (!empty($img)) {
                  $imgSrc = str_starts_with($img, 'http') ? $img : ('../' . ltrim($img, '/'));
              }

              $tags = json_decode($p['tags'] ?? '[]', true);
              if (!is_array($tags)) $tags = [];
            ?>
            <tr class="product-row" data-id="<?= $id ?>" data-name="<?= $name ?>" data-cat="<?= $cat ?>" data-status="<?= $statusAttr ?>">
              <td>
                <?php if ($imgSrc !== ''): ?>
                  <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= $name ?>" class="product-thumb" loading="lazy" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'product-thumb-placeholder\'>IMG</div>';">
                <?php else: ?>
                  <div class="product-thumb-placeholder"><?= renderSvgIcon('image') ?></div>
                <?php endif; ?>
              </td>

              <td class="product-title-cell">
                <a href="edit.php?id=<?= $id ?>" class="product-name-link"><?= $name ?></a>
                <?php if ($sub !== ''): ?>
                  <div class="product-sub-text"><?= $sub ?></div>
                <?php endif; ?>
              </td>

              <td>
                <span class="category-badge"><?= $cat ?></span>
              </td>

              <td class="price-cell">
                <?= $price ?>
              </td>

              <td>
                <div class="tags-list">
                  <?php if (!empty($tags)): ?>
                    <?php foreach ($tags as $t): ?>
                      <span class="tag-item"><?= htmlspecialchars((string)$t) ?></span>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span style="color: var(--text-dim); font-size: 11px;">—</span>
                  <?php endif; ?>
                </div>
              </td>

              <td style="text-align: center;">
                <label class="switch-label">
                  <input type="checkbox" class="status-switch-input" data-id="<?= $id ?>" <?= $isActive ? 'checked' : '' ?>>
                  <span class="slider"></span>
                </label>
              </td>

              <td class="actions-cell">
                <div class="action-btns">
                  <a href="../product.php?id=<?= $id ?>" target="_blank" class="btn-icon" title="Открыть на сайте">
                    <?= renderSvgIcon('external') ?>
                  </a>

                  <button type="button" class="btn-icon btn-duplicate-product" data-id="<?= $id ?>" title="Дублировать">
                    <?= renderSvgIcon('copy') ?>
                  </button>

                  <a href="edit.php?id=<?= $id ?>" class="btn-icon" title="Редактировать">
                    <?= renderSvgIcon('edit') ?>
                  </a>

                  <button type="button" class="btn-icon btn-icon-danger btn-delete-product" data-id="<?= $id ?>" data-name="<?= $name ?>" title="Удалить">
                    <?= renderSvgIcon('trash') ?>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
