<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

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
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Недействительный токен безопасности. Повторите попытку.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $cat = trim((string)($_POST['cat'] ?? 'Футболка'));
        $catId = trim((string)($_POST['cat_id'] ?? ''));
        if ($catId === '') {
            $catId = match (mb_strtolower($cat)) {
                'футболка', 'футболки' => 'tshirt',
                'свитшот', 'свитшоты' => 'sweatshirt',
                'худи' => 'hoodie',
                'кепка', 'кепки', 'бейсболка' => 'cap',
                default => 'other'
            };
        }

        $rawPrice = trim((string)($_POST['price'] ?? '0 ₽'));
        $priceNumeric = extractPriceNumeric($rawPrice);
        if ($rawPrice === '') {
            $price = number_format($priceNumeric, 0, '', ' ') . ' ₽';
        } else {
            $price = $rawPrice;
            if (!str_contains($price, '₽') && !str_contains($price, 'руб')) {
                $price .= ' ₽';
            }
        }

        $sub = trim((string)($_POST['sub'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        // Tags
        $selectedTags = $_POST['tags'] ?? [];
        if (!is_array($selectedTags)) $selectedTags = [];
        $customTag = trim((string)($_POST['custom_tag'] ?? ''));
        if ($customTag !== '' && !in_array($customTag, $selectedTags, true)) {
            $selectedTags[] = $customTag;
        }
        $tagsJson = json_encode(array_values($selectedTags), JSON_UNESCAPED_UNICODE);

        // Main image
        $img = trim((string)($_POST['img'] ?? ''));
        if (isset($_FILES['main_image_file']) && $_FILES['main_image_file']['error'] === UPLOAD_ERR_OK) {
            $up = handleImageUpload($_FILES['main_image_file']);
            if ($up['success']) {
                $img = $up['path'];
            } else {
                $error = $up['error'];
            }
        }

        // Gallery images
        $galleryImgs = $_POST['gallery_imgs'] ?? [];
        if (!is_array($galleryImgs)) $galleryImgs = [];
        $galleryImgs = array_filter(array_map('trim', $galleryImgs));

        if (isset($_FILES['additional_gallery_files']) && !empty($_FILES['additional_gallery_files']['name'][0])) {
            $filesCount = count($_FILES['additional_gallery_files']['name']);
            for ($i = 0; $i < $filesCount; $i++) {
                if ($_FILES['additional_gallery_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $singleFile = [
                        'name' => $_FILES['additional_gallery_files']['name'][$i],
                        'type' => $_FILES['additional_gallery_files']['type'][$i],
                        'tmp_name' => $_FILES['additional_gallery_files']['tmp_name'][$i],
                        'error' => $_FILES['additional_gallery_files']['error'][$i],
                        'size' => $_FILES['additional_gallery_files']['size'][$i]
                    ];
                    $up = handleImageUpload($singleFile);
                    if ($up['success']) {
                        $galleryImgs[] = $up['path'];
                    }
                }
            }
        }

        if ($img !== '' && !in_array($img, $galleryImgs, true)) {
            array_unshift($galleryImgs, $img);
        }
        $imgsJson = json_encode(array_values(array_unique($galleryImgs)), JSON_UNESCAPED_UNICODE);

        // Specs
        $specsJsonInput = trim((string)($_POST['specs_json'] ?? ''));
        $specs = json_decode($specsJsonInput, true);
        if (!is_array($specs)) {
            $specs = [];
        }
        $specsJson = json_encode($specs, JSON_UNESCAPED_UNICODE);

        // TG link
        $tgLink = trim((string)($_POST['tg_link'] ?? ''));
        if ($tgLink === '') {
            $tgLink = 'https://telegram.me/theboyforge?text=' . rawurlencode('Здравствуйте! Хочу заказать: ' . $name);
        }

        if ($name === '') {
            $error = 'Укажите название товара.';
        }

        if ($error === '') {
            if ($isEdit) {
                $sql = "UPDATE products SET
                    cat_id = :cat_id,
                    cat = :cat,
                    name = :name,
                    price = :price,
                    price_numeric = :price_numeric,
                    img = :img,
                    imgs = :imgs,
                    sub = :sub,
                    tags = :tags,
                    description = :description,
                    specs = :specs,
                    tg_link = :tg_link,
                    is_active = :is_active,
                    sort_order = :sort_order
                    WHERE id = :id";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':cat_id' => $catId,
                    ':cat' => $cat,
                    ':name' => $name,
                    ':price' => $price,
                    ':price_numeric' => $priceNumeric,
                    ':img' => $img,
                    ':imgs' => $imgsJson,
                    ':sub' => $sub,
                    ':tags' => $tagsJson,
                    ':description' => $description,
                    ':specs' => $specsJson,
                    ':tg_link' => $tgLink,
                    ':is_active' => $isActive,
                    ':sort_order' => $sortOrder,
                    ':id' => $id
                ]);
            } else {
                $sql = "INSERT INTO products 
                    (cat_id, cat, name, price, price_numeric, img, imgs, sub, tags, description, specs, tg_link, is_active, sort_order)
                    VALUES 
                    (:cat_id, :cat, :name, :price, :price_numeric, :img, :imgs, :sub, :tags, :description, :specs, :tg_link, :is_active, :sort_order)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':cat_id' => $catId,
                    ':cat' => $cat,
                    ':name' => $name,
                    ':price' => $price,
                    ':price_numeric' => $priceNumeric,
                    ':img' => $img,
                    ':imgs' => $imgsJson,
                    ':sub' => $sub,
                    ':tags' => $tagsJson,
                    ':description' => $description,
                    ':specs' => $specsJson,
                    ':tg_link' => $tgLink,
                    ':is_active' => $isActive,
                    ':sort_order' => $sortOrder
                ]);
                $id = (int)$pdo->lastInsertId();
            }

            syncProductsJs($pdo);

            $saveAction = $_POST['save_action'] ?? 'save_close';
            if ($saveAction === 'save_close') {
                header('Location: index.php?msg=saved');
                exit;
            } else {
                header("Location: edit.php?id={$id}&msg=saved");
                exit;
            }
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
    $success = 'Изменения успешно сохранены и синхронизированы с сайтом!';
}

// Prepare values for form
$nameVal = htmlspecialchars($product['name'] ?? '');
$catVal = htmlspecialchars($product['cat'] ?? 'Футболка');
$catIdVal = htmlspecialchars($product['cat_id'] ?? 'tshirt');
$priceVal = htmlspecialchars($product['price'] ?? '3 200 ₽');
$subVal = htmlspecialchars($product['sub'] ?? '');
$descVal = htmlspecialchars($product['description'] ?? '');
$isActiveVal = (int)($product['is_active'] ?? 1) === 1;
$sortOrderVal = (int)($product['sort_order'] ?? 0);
$tgLinkVal = htmlspecialchars($product['tg_link'] ?? '');
$imgVal = htmlspecialchars($product['img'] ?? '');

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
    <a href="../product.php?id=<?= $id ?>" target="_blank" class="btn btn-secondary">
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

  <!-- 1. Основные сведения -->
  <div class="form-card">
    <div class="form-card-title">
      <?= renderSvgIcon('box') ?>
      <span>1. Основные параметры</span>
    </div>

    <div class="form-group">
      <label for="name" class="form-label">Название товара *</label>
      <input type="text" id="name" name="name" class="form-input" value="<?= $nameVal ?>" placeholder="Например: Футболка «Братья Святославичи»" required>
    </div>

<?php
$allCategories = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

    <div class="form-grid-3">
      <div class="form-group">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <label for="cat" class="form-label" style="margin-bottom: 0;">Категория</label>
          <a href="categories.php" target="_blank" style="font-size: 11px; text-transform: none; color: var(--text-muted);">+ Настроить</a>
        </div>
        <input type="hidden" name="cat_id" id="catIdInput" value="<?= $catIdVal ?>">
        <select id="cat" name="cat" class="form-select" onchange="const opt = this.options[this.selectedIndex]; if (opt) document.getElementById('catIdInput').value = opt.getAttribute('data-slug') || '';">
          <?php if (empty($allCategories)): ?>
            <option value="Футболка" data-slug="tshirt">Футболка</option>
          <?php else: ?>
            <?php foreach ($allCategories as $catRow): ?>
              <?php
                $isSelected = ($catVal === $catRow['name'] || $catIdVal === $catRow['slug']);
              ?>
              <option value="<?= htmlspecialchars($catRow['name']) ?>" data-slug="<?= htmlspecialchars($catRow['slug']) ?>" <?= $isSelected ? 'selected' : '' ?>>
                <?= htmlspecialchars($catRow['name']) ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="price" class="form-label">Цена</label>
        <input type="text" id="price" name="price" class="form-input" value="<?= $priceVal ?>" placeholder="3 200 ₽">
      </div>

      <div class="form-group">
        <label for="sort_order" class="form-label">Порядок сортировки</label>
        <input type="number" id="sort_order" name="sort_order" class="form-input" value="<?= $sortOrderVal ?>">
        <div class="form-help">Меньше значение — выше в каталоге</div>
      </div>
    </div>

    <div class="form-group">
      <label for="sub" class="form-label">Краткий подзаголовок</label>
      <input type="text" id="sub" name="sub" class="form-input" value="<?= $subVal ?>" placeholder="Футболка · 95% хлопок / 5% эластан">
    </div>

    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label">Бейджи / Теги</label>
        <div style="display: flex; gap: 14px; flex-wrap: wrap; margin-top: 6px;">
          <?php
            $presetTags = ['Новая коллекция', 'Хит', 'S–3XL', 'Оверсайз', 'Лимитированный тираж'];
            foreach ($presetTags as $pt):
              $checked = in_array($pt, $currentTags, true);
          ?>
            <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12px;">
              <input type="checkbox" name="tags[]" value="<?= htmlspecialchars($pt) ?>" <?= $checked ? 'checked' : '' ?>>
              <span><?= htmlspecialchars($pt) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group">
        <label for="custom_tag" class="form-label">Свой бейдж (опционально)</label>
        <input type="text" id="custom_tag" name="custom_tag" class="form-input" placeholder="Добавить свой бейдж...">
      </div>
    </div>

    <div class="form-group" style="margin-top: 10px;">
      <label style="display: inline-flex; align-items: center; gap: 10px; cursor: pointer;">
        <input type="checkbox" name="is_active" value="1" <?= $isActiveVal ? 'checked' : '' ?> style="width: 18px; height: 18px;">
        <span style="font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;">Опубликован на сайте (активен)</span>
      </label>
    </div>
  </div>

  <!-- 2. Изображения -->
  <div class="form-card">
    <div class="form-card-title">
      <?= renderSvgIcon('image') ?>
      <span>2. Фотографии товара</span>
    </div>

    <div class="form-grid-2">
      <div>
        <div class="form-group">
          <label for="mainImageInput" class="form-label">Главное фото (путь или URL)</label>
          <input type="text" id="mainImageInput" name="img" class="form-input" value="<?= $imgVal ?>" placeholder="images/bratya/p-bratya.jpg">
          <div class="form-help">Используется как основная обложка в карточке каталога</div>
        </div>

        <div class="form-group">
          <label class="form-label">Или загрузите файл с компьютера</label>
          <input type="file" id="mainImageFile" name="main_image_file" accept="image/jpeg,image/png,image/webp,image/gif" class="form-input" style="padding: 8px;">
        </div>
      </div>

      <div>
        <label class="form-label">Предпросмотр главного фото</label>
        <div style="width: 130px; height: 160px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); overflow: hidden; background: var(--bg-input); display: flex; align-items: center; justify-content: center;">
          <?php
            $previewSrc = '';
            if ($imgVal !== '') {
                $previewSrc = str_starts_with($imgVal, 'http') ? $imgVal : ('../' . ltrim($imgVal, '/'));
            }
          ?>
          <img id="mainImagePreview" src="<?= htmlspecialchars($previewSrc) ?>" alt="Обложка" style="width: 100%; height: 100%; object-fit: cover; display: <?= $previewSrc !== '' ? 'block' : 'none' ?>;" onerror="this.style.display='none';">
        </div>
      </div>
    </div>

    <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color);">
      <label class="form-label">Дополнительные фото для галереи товара</label>
      <div class="form-help" style="margin-bottom: 12px;">Отображаются в слайдере на странице товара (product.php)</div>

      <div class="form-group">
        <input type="file" id="galleryFiles" name="additional_gallery_files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="form-input" style="padding: 8px;">
        <div class="form-help">Можно выбрать сразу несколько изображений</div>
      </div>

      <div class="gallery-manager" id="galleryContainer">
        <?php foreach ($currentImgs as $gImg): ?>
          <?php
            $gSrc = str_starts_with($gImg, 'http') ? $gImg : ('../' . ltrim($gImg, '/'));
          ?>
          <div class="gallery-card">
            <img src="<?= htmlspecialchars($gSrc) ?>" alt="Галерея" onerror="this.onerror=null; this.src=''; this.alt='Ошибка загрузки';">
            <input type="hidden" name="gallery_imgs[]" value="<?= htmlspecialchars($gImg) ?>">
            <div class="gallery-card-actions">
              <button type="button" class="gallery-remove-btn" title="Удалить фото">
                <?= renderSvgIcon('x') ?>
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- 3. Описание -->
  <div class="form-card">
    <div class="form-card-title">
      <?= renderSvgIcon('edit') ?>
      <span>3. Описание товара</span>
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label for="description" class="form-label">Полный текст описания</label>
      <textarea id="description" name="description" class="form-textarea" placeholder="Подробное описание ткани, принта, посадки и особенностей..."><?= $descVal ?></textarea>
    </div>
  </div>

  <!-- 4. Характеристики -->
  <div class="form-card">
    <div class="form-card-title" style="justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <?= renderSvgIcon('filter') ?>
        <span>4. Характеристики (Таблица спецификации)</span>
      </div>

      <div style="display: flex; gap: 8px;">
        <button type="button" class="btn btn-secondary btn-sm" id="btnPresetTshirt">Шаблон футболки</button>
        <button type="button" class="btn btn-secondary btn-sm" id="btnPresetSweat">Шаблон свитшота</button>
      </div>
    </div>

    <table class="specs-table">
      <thead>
        <tr>
          <th style="width: 35%;">Параметр</th>
          <th style="width: 55%;">Значение</th>
          <th style="width: 10%; text-align: right;">Удалить</th>
        </tr>
      </thead>
      <tbody id="specsTableBody">
        <?php foreach ($currentSpecs as $row): ?>
          <?php
            $sKey = htmlspecialchars($row[0] ?? '');
            $sVal = htmlspecialchars($row[1] ?? '');
          ?>
          <tr class="specs-row">
            <td><input type="text" class="form-input spec-key" value="<?= $sKey ?>" placeholder="Параметр"></td>
            <td><input type="text" class="form-input spec-val" value="<?= $sVal ?>" placeholder="Значение"></td>
            <td style="text-align: right;">
              <button type="button" class="btn-icon btn-icon-danger btn-remove-spec" title="Удалить">
                <?= renderSvgIcon('x') ?>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <button type="button" class="btn btn-secondary btn-sm" id="btnAddSpec">
      <?= renderSvgIcon('plus') ?>
      <span>Добавить строку</span>
    </button>
  </div>

  <!-- 5. Telegram ссылка -->
  <div class="form-card">
    <div class="form-card-title">
      <?= renderSvgIcon('external') ?>
      <span>5. Ссылка для быстрого заказа в Telegram</span>
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label for="tg_link" class="form-label">URL Telegram (оставьте пустым для автогенерации)</label>
      <input type="text" id="tg_link" name="tg_link" class="form-input" value="<?= $tgLinkVal ?>" placeholder="https://telegram.me/theboyforge?text=...">
    </div>
  </div>

  <!-- Кнопки сохранения -->
  <div style="display: flex; gap: 14px; margin-top: 30px; flex-wrap: wrap;">
    <button type="submit" name="save_action" value="save_close" class="btn btn-primary" style="padding: 12px 24px;">
      <?= renderSvgIcon('check') ?>
      <span>Сохранить и вернуться к списку</span>
    </button>

    <button type="submit" name="save_action" value="save_stay" class="btn btn-secondary" style="padding: 12px 24px;">
      <span>Сохранить и остаться</span>
    </button>

    <a href="index.php" class="btn btn-secondary" style="padding: 12px 24px;">
      <span>Отмена</span>
    </a>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
