<?php
declare(strict_types=1);

$previewSrc = '';
if (!empty($imgVal)) {
    $previewSrc = str_starts_with($imgVal, 'http') ? $imgVal : ('../' . ltrim($imgVal, '/'));
}
?>
<!-- 2. Фотографии товара -->
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
