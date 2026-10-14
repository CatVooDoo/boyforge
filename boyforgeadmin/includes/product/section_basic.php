<?php
declare(strict_types=1);

$allCategories = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$dbTags = $pdo->query("SELECT name FROM tags ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$allAvailableTags = array_values(array_unique(array_merge($dbTags, $currentTags)));
?>
<!-- 1. Основные параметры -->
<div class="form-card">
  <div class="form-card-title">
    <?= renderSvgIcon('box') ?>
    <span>1. Основные параметры</span>
  </div>

  <div class="form-group">
    <label for="name" class="form-label">Название товара *</label>
    <input type="text" id="name" name="name" class="form-input" value="<?= $nameVal ?>" placeholder="Например: Футболка «Братья Святославичи»" required>
  </div>

  <div class="form-group">
    <label for="slug" class="form-label">URL (slug)</label>
    <input type="text" id="slug" name="slug" value="<?= $slugVal ?>" 
           placeholder="auto-generated-from-name" class="form-input">
    <small class="form-help">
        Оставьте пустым для автогенерации из названия. Допустимы только латинские буквы, цифры и дефисы.
    </small>
  </div>

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

  <div class="form-group" style="margin-top: 6px;">
    <label class="form-label">Бейджи / Теги</label>
    <div class="tags-badge-container" id="tagsContainer">
      <?php foreach ($allAvailableTags as $pt): ?>
        <?php $checked = in_array($pt, $currentTags, true); ?>
        <label class="badge-checkbox-item" data-tag="<?= htmlspecialchars($pt) ?>">
          <input type="checkbox" name="tags[]" value="<?= htmlspecialchars($pt) ?>" <?= $checked ? 'checked' : '' ?>>
          <span class="badge-tag-text"><?= htmlspecialchars($pt) ?></span>
          <button type="button" class="badge-delete-btn" title="Удалить бейдж из общего списка" data-tag="<?= htmlspecialchars($pt) ?>" aria-label="Удалить">&times;</button>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="tag-add-wrapper">
      <input type="text" id="custom_tag" name="custom_tag" class="form-input" placeholder="Название нового бейджа...">
      <button type="button" class="btn btn-secondary" id="btnAddCustomTag">
        <?= renderSvgIcon('plus') ?>
        <span>Добавить бейдж</span>
      </button>
    </div>
    <div class="form-help">Выбранные бейджи будут отображаться на карточке товара. Добавленный бейдж сохранится в базе и будет доступен для выбора у всех товаров.</div>
  </div>

  <div class="form-group" style="margin-top: 10px;">
    <label style="display: inline-flex; align-items: center; gap: 10px; cursor: pointer;">
      <input type="checkbox" name="is_active" value="1" <?= $isActiveVal ? 'checked' : '' ?> style="width: 18px; height: 18px;">
      <span style="font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;">Опубликован на сайте (активен)</span>
    </label>
  </div>

  <div class="form-group" style="margin-top: 16px;">
    <label class="form-label">Недоступные размеры (Мужская сетка)</label>
    <div class="tags-badge-container" id="sizesContainerMale" style="display:flex; flex-wrap:wrap; gap:10px;">
      <?php foreach (allSizes() as $sz): ?>
        <?php $sizeChecked = in_array($sz, $unavMale, true); ?>
        <label class="badge-checkbox-item size-check-item<?= $sizeChecked ? ' is-out' : '' ?>" data-size="<?= htmlspecialchars($sz) ?>"
               style="display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border:1.5px solid var(--border-color); border-radius:var(--radius-sm); cursor:pointer;">
          <input type="checkbox" name="sizes_unavailable_male[]" value="<?= htmlspecialchars($sz) ?>" <?= $sizeChecked ? 'checked' : '' ?>>
          <span class="size-check-label" style="font-weight:600; font-size:13px; position:relative;"><?= htmlspecialchars($sz) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    
    <label class="form-label" style="margin-top: 16px;">Недоступные размеры (Женская сетка)</label>
    <div class="tags-badge-container" id="sizesContainerFemale" style="display:flex; flex-wrap:wrap; gap:10px;">
      <?php foreach (allSizes() as $sz): ?>
        <?php $sizeChecked = in_array($sz, $unavFemale, true); ?>
        <label class="badge-checkbox-item size-check-item<?= $sizeChecked ? ' is-out' : '' ?>" data-size="<?= htmlspecialchars($sz) ?>"
               style="display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border:1.5px solid var(--border-color); border-radius:var(--radius-sm); cursor:pointer;">
          <input type="checkbox" name="sizes_unavailable_female[]" value="<?= htmlspecialchars($sz) ?>" <?= $sizeChecked ? 'checked' : '' ?>>
          <span class="size-check-label" style="font-weight:600; font-size:13px; position:relative;"><?= htmlspecialchars($sz) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <div class="form-help" style="margin-top: 8px;">Отметьте галочкой размеры, которых сейчас нет в наличии — на сайте они будут перечёркнуты и недоступны для заказа.</div>
  </div>
</div>
