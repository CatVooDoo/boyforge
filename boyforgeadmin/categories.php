<?php
declare(strict_types=1);

$pageTitle = 'Управление категориями';
require_once __DIR__ . '/includes/header.php';

$sql = "SELECT c.*, COUNT(p.id) AS products_count 
        FROM categories c 
        LEFT JOIN products p ON (p.cat_id = c.slug OR p.cat = c.name) 
        GROUP BY c.id 
        ORDER BY c.sort_order ASC, c.id ASC";
$categories = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$totalCats = count($categories);
$activeCats = 0;
$withProductsCount = 0;

foreach ($categories as $c) {
    if ((int)$c['is_active'] === 1) {
        $activeCats++;
    }
    if ((int)$c['products_count'] > 0) {
        $withProductsCount++;
    }
}
?>

<div class="page-head">
  <div>
    <h1 class="page-title">Категории товаров</h1>
    <div class="page-subtitle">Создание, редактирование и порядок категорий для каталога и карточек товаров</div>
  </div>

  <div>
    <button type="button" class="btn btn-primary" id="btnOpenAddCategoryModal">
      <?= renderSvgIcon('plus') ?>
      <span>Добавить категорию</span>
    </button>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon-wrapper">
      <?= renderSvgIcon('tag') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value"><?= $totalCats ?></span>
      <span class="stat-label">Всего категорий</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrapper" style="color: var(--accent-success);">
      <?= renderSvgIcon('eye') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value"><?= $activeCats ?></span>
      <span class="stat-label">Активно на сайте</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrapper">
      <?= renderSvgIcon('box') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value"><?= $withProductsCount ?></span>
      <span class="stat-label">С товарами</span>
    </div>
  </div>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width: 50px;">ID</th>
          <th>Название категории</th>
          <th>Системный код (Slug)</th>
          <th style="text-align: center; width: 150px;">Товаров в категории</th>
          <th style="text-align: center; width: 110px;">Порядок</th>
          <th style="text-align: center; width: 90px;">Статус</th>
          <th style="text-align: right; width: 110px;">Действия</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($categories)): ?>
          <tr>
            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
              Категорий пока нет. Нажмите «Добавить категорию», чтобы создать первую.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($categories as $cat): ?>
            <?php
              $cId = (int)$cat['id'];
              $cName = htmlspecialchars($cat['name']);
              $cSlug = htmlspecialchars($cat['slug']);
              $cOrder = (int)$cat['sort_order'];
              $cCount = (int)$cat['products_count'];
              $cActive = (int)$cat['is_active'] === 1;
            ?>
            <tr class="category-row" data-id="<?= $cId ?>" data-name="<?= $cName ?>" data-slug="<?= $cSlug ?>" data-order="<?= $cOrder ?>" data-active="<?= $cActive ? '1' : '0' ?>" data-count="<?= $cCount ?>">
              <td style="color: var(--text-dim); font-size: 11px;">#<?= $cId ?></td>

              <td>
                <span style="font-weight: 700; font-size: 14px; color: #000000;"><?= $cName ?></span>
              </td>

              <td>
                <code style="background: var(--bg-card-muted); padding: 3px 8px; border-radius: 4px; font-size: 11px; border: 1px solid var(--border-color); color: #333333;"><?= $cSlug ?></code>
              </td>

              <td style="text-align: center;">
                <span class="category-badge" style="background: <?= $cCount > 0 ? 'var(--bg-card-muted)' : '#fafaf8' ?>;">
                  <?= $cCount ?> поз.
                </span>
              </td>

              <td style="text-align: center; font-weight: 600;">
                <?= $cOrder ?>
              </td>

              <td style="text-align: center;">
                <label class="switch-label">
                  <input type="checkbox" class="cat-status-switch-input" data-id="<?= $cId ?>" <?= $cActive ? 'checked' : '' ?>>
                  <span class="slider"></span>
                </label>
              </td>

              <td class="actions-cell">
                <div class="action-btns">
                  <button type="button" class="btn-icon btn-edit-category" data-id="<?= $cId ?>" data-name="<?= $cName ?>" data-slug="<?= $cSlug ?>" data-order="<?= $cOrder ?>" data-active="<?= $cActive ? '1' : '0' ?>" title="Редактировать">
                    <?= renderSvgIcon('edit') ?>
                  </button>

                  <button type="button" class="btn-icon btn-icon-danger btn-delete-category" data-id="<?= $cId ?>" data-name="<?= $cName ?>" data-count="<?= $cCount ?>" title="Удалить">
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

<?php require __DIR__ . '/includes/categories/modal_edit.php'; ?>
<?php require __DIR__ . '/includes/categories/modal_delete.php'; ?>

<script src="assets/js/categories.js?v=<?= time() ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

