<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once __DIR__ . '/includes/product_save.php';

requireAdminAuth();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$product = null;

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header('Location: index.php');
        exit;
    }
}

$pageTitle = $isEdit ? 'Редактирование товара' : 'Добавление товара';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = handleProductSave($pdo, $isEdit, $id);
}

if (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
    $success = 'Изменения успешно сохранены и синхронизированы с сайтом!';
}

$nameVal = htmlspecialchars($product['name'] ?? '');
$slugVal = htmlspecialchars($product['slug'] ?? '');
$catVal = htmlspecialchars($product['cat'] ?? 'Футболка');
$catIdVal = htmlspecialchars($product['cat_id'] ?? 'tshirt');
$priceVal = htmlspecialchars($product['price'] ?? '3 200 ₽');
$subVal = htmlspecialchars($product['sub'] ?? '');
$descVal = htmlspecialchars($product['description'] ?? '');
$isActiveVal = (int)($product['is_active'] ?? 1) === 1;
$sortOrderVal = (int)($product['sort_order'] ?? 0);
$tgLinkVal = htmlspecialchars($product['tg_link'] ?? '');
$imgVal = htmlspecialchars($product['img'] ?? '');

$currentUnavailableSizes = productUnavailableSizes($product['sizes'] ?? null);
$unavMale = $currentUnavailableSizes['Мужской'] ?? [];
$unavFemale = $currentUnavailableSizes['Женский'] ?? [];

$currentTags = [];
if (!empty($product['tags'])) {
    $currentTags = json_decode($product['tags'], true) ?: [];
}

$currentImgs = [];
if (!empty($product['imgs'])) {
    $currentImgs = json_decode($product['imgs'], true) ?: [];
}

$currentSpecs = [];
if (!empty($product['specs'])) {
    $currentSpecs = json_decode($product['specs'], true) ?: [];
}
if (empty($currentSpecs) && !$isEdit) {
    $currentSpecs = [
        ["Материал", "Футер 2-нитка, 95% хлопок / 5% эластан"],
        ["Плотность", "240 г/м²"],
        ["Печать", "DTF"],
        ["Размеры", "S–3XL"],
        ["Пошив", "Россия"]
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; margin-bottom: 6px;">
      <a href="index.php" style="display: inline-flex; align-items: center; gap: 4px;">
        <?= renderSvgIcon('arrow-left') ?>
        <span>Назад к списку товаров</span>
      </a>
    </div>
    <h1 class="page-title"><?= $isEdit ? 'Редактирование товара' : 'Новый товар' ?></h1>
  </div>

  <?php if ($isEdit): ?>
    <a href="/product/<?= htmlspecialchars($product['slug'] ?? (string)$id) ?>" target="_blank" class="btn btn-secondary">
      <?= renderSvgIcon('external') ?>
      <span>Просмотр на сайте</span>
    </a>
  <?php endif; ?>
</div>

<?php if ($error !== ''): ?>
  <div class="alert-error" style="margin-bottom: 24px;">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<?php if ($success !== ''): ?>
  <div style="background: var(--accent-success-bg); border: 1px solid rgba(46, 125, 50, 0.4); color: var(--accent-success); padding: 12px 16px; border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 24px;">
    <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>

<form method="POST" action="edit.php<?= $isEdit ? '?id=' . $id : '' ?>" enctype="multipart/form-data" id="productForm">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
  <input type="hidden" name="specs_json" id="specsHiddenInput" value="">

  <!-- 1. Основные параметры -->
  <?php require __DIR__ . '/includes/product/section_basic.php'; ?>

  <!-- 2. Фотографии товара -->
  <?php require __DIR__ . '/includes/product/section_images.php'; ?>

  <!-- 3. Описание и 4. Характеристики -->
  <?php require __DIR__ . '/includes/product/section_specs.php'; ?>

  <!-- 5. Ссылка Telegram и кнопки действий -->
  <?php require __DIR__ . '/includes/product/section_actions.php'; ?>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
