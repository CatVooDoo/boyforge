<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
requireAdminAuth();
$pageTitle = 'Контент · Слайдер';
$allSlides = getSliderSlidesByDevice($pdo, false);
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/boyforgeadmin/assets/css/content.css?v=<?= filemtime(__DIR__ . '/assets/css/content.css') ?>">
<div class="content-editor">
  <div class="page-head">
    <div><div class="content-kicker">Витрина / Контент</div><h1 class="page-title">Главный слайдер</h1>
      <div class="page-subtitle">Два формата. Своя история для каждого экрана.</div></div>
    <a href="/" target="_blank" rel="noopener" class="btn btn-secondary"><?= renderSvgIcon('external') ?> Открыть сайт</a>
  </div>
  <nav class="content-tabs-nav" aria-label="Разделы контента"><a href="content.php" class="content-tab-btn active" aria-current="page"><?= renderSvgIcon('layers') ?> Слайдер</a></nav>
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-icon-wrapper"><?= renderSvgIcon('image') ?></div><div class="stat-info"><span class="stat-value" id="statTotalCount"><?= count($allSlides['desktop']) + count($allSlides['mobile']) ?></span><span class="stat-label">Всего слайдов</span></div></div>
    <?php foreach (['desktop' => ['monitor', 'Десктоп'], 'mobile' => ['smartphone', 'Мобильная версия']] as $device => [$icon, $label]): ?>
    <div class="stat-card"><div class="stat-icon-wrapper"><?= renderSvgIcon($icon) ?></div><div class="stat-info"><span class="stat-value" id="stat<?= ucfirst($device) ?>Count"><?= count($allSlides[$device]) ?></span><span class="stat-label"><?= $label ?> · <span id="active-<?= $device ?>"><?= count(array_filter($allSlides[$device], static fn($s) => (int)$s['is_active'] === 1)) ?></span> на сайте</span></div></div>
    <?php endforeach; ?>
  </div>
  <div class="device-switcher" role="tablist" aria-label="Формат слайдера">
    <?php foreach (['desktop' => ['monitor', 'Десктоп'], 'mobile' => ['smartphone', 'Мобильная версия']] as $device => [$icon, $label]): ?>
    <button type="button" id="tab-<?= $device ?>" class="device-btn <?= $device === 'desktop' ? 'active' : '' ?>" role="tab" aria-selected="<?= $device === 'desktop' ? 'true' : 'false' ?>" aria-controls="panel-<?= $device ?>" tabindex="<?= $device === 'desktop' ? '0' : '-1' ?>" data-device="<?= $device ?>"><?= renderSvgIcon($icon) ?><span><?= $label ?></span><span class="device-badge" id="badge<?= ucfirst($device) ?>"><?= count($allSlides[$device]) ?></span></button>
    <?php endforeach; ?>
  </div>
  <?php foreach (['desktop', 'mobile'] as $device): $mobile = $device === 'mobile'; ?>
  <section class="device-panel <?= $mobile ? '' : 'active' ?>" id="panel-<?= $device ?>" role="tabpanel" aria-labelledby="tab-<?= $device ?>" <?= $mobile ? 'hidden' : '' ?>>
    <div class="recommendation-banner">
      <div class="rec-icon"><?= renderSvgIcon($mobile ? 'smartphone' : 'monitor') ?></div>
      <div class="rec-content"><div class="rec-title"><?= $mobile ? 'Вертикальный баннер' : 'Широкоформатный баннер' ?></div>
        <div class="rec-size"><?= $mobile ? '1080 × 1920' : '1920 × 1080' ?> <span>px / <?= $mobile ? '9:16' : '16:9' ?></span></div>
        <p class="rec-desc">Баннер заполняет экран; края могут обрезаться. Держите важные детали ближе к центру, оставьте место для меню сверху и заголовка с кнопкой снизу. <?= $mobile ? 'Мобильные изображения показываются при ширине экрана до 767 px.' : 'Для экранов высокой плотности можно использовать 2560 × 1440 px.' ?></p>
      </div>
    </div>
    <div class="upload-dropzone" data-device="<?= $device ?>">
      <input type="file" aria-label="Выбрать <?= $mobile ? 'мобильные' : 'десктопные' ?> баннеры" class="slider-file-input" multiple accept="image/jpeg,image/png,image/webp,image/gif">
      <div class="dropzone-content"><div class="dropzone-icon"><?= renderSvgIcon('upload') ?></div><div class="dropzone-text"><strong>Перетащите изображения</strong> или <span>выберите файлы</span></div><div class="dropzone-hint">JPG, PNG, WebP, GIF · до 15 МБ на файл · количество не ограничено</div></div>
    </div>
    <div class="upload-queue" id="queue-<?= $device ?>" hidden>
      <div class="queue-head"><div><strong>Готовы к загрузке</strong><p class="rec-desc">Проверьте изображения перед публикацией.</p></div><div class="queue-actions"><button type="button" class="btn btn-secondary btn-clear-queue">Очистить</button><button type="button" class="btn btn-primary btn-upload-queue">Загрузить на сайт</button></div></div>
      <div class="queue-grid"></div><p class="queue-progress" role="status" aria-live="polite"></p>
    </div>
    <div class="slides-section-head"><h2>Слайды на сайте</h2><span>Стрелки меняют порядок показа</span></div>
    <div class="slides-grid <?= $mobile ? 'slides-grid--mobile' : '' ?>" id="grid-<?= $device ?>">
      <?php foreach ($allSlides[$device] as $slide) { renderSlideCard($slide, $device); } ?>
    </div>
    <div class="slides-empty" id="empty-<?= $device ?>" <?= $allSlides[$device] ? 'hidden' : '' ?>><?= renderSvgIcon('image') ?><h3>Здесь начинается ваша история</h3><p>Добавьте <?= $mobile ? 'мобильный' : 'десктопный' ?> баннер выше.<br>Пока слайдов нет, на сайте отображается пустой фон с заголовком и кнопкой.</p></div>
  </section>
  <?php endforeach; ?>
  <p class="content-footnote">Изменения применяются сразу. Десктопный и мобильный слайдеры независимы.</p>
</div>
<dialog class="content-dialog preview-dialog" id="previewModal" aria-label="Предпросмотр баннера"><button type="button" class="preview-modal-close" id="closePreviewBtn" aria-label="Закрыть предпросмотр">×</button><img id="previewModalImg" alt="Предпросмотр баннера"><p id="previewModalCaption"></p></dialog>
<dialog class="content-dialog" id="deleteSlideModal" aria-labelledby="deleteSlideTitle"><h2 class="modal-title" id="deleteSlideTitle">Удалить слайд?</h2><p class="modal-text">Он исчезнет с сайта, а изображение будет удалено. Это действие нельзя отменить.</p><div class="modal-actions"><button type="button" class="btn btn-secondary" id="cancelDeleteSlideBtn">Отмена</button><button type="button" class="btn btn-danger" id="confirmDeleteSlideBtn">Удалить</button></div><p class="delete-error" role="alert"></p></dialog>
<template id="slideCardTemplate"><?php renderSlideCard(['id' => 0], 'desktop'); ?></template>
<?php
function renderSlideCard(array $slide, string $device): void {
    $id = (int)$slide['id'];
    $src = empty($slide['image_path']) ? '' : '/' . ltrim($slide['image_path'], '/');
    $alt = htmlspecialchars((string)($slide['alt_text'] ?? ''), ENT_QUOTES, 'UTF-8');
    $active = (int)($slide['is_active'] ?? 1) === 1;
?>
<article class="slide-card <?= $device === 'mobile' ? 'slide-card--mobile' : '' ?>" data-id="<?= $id ?>" data-device="<?= $device ?>" data-order="<?= (int)($slide['sort_order'] ?? 0) ?>">
  <button type="button" class="slide-card-img-wrap" aria-label="Предпросмотр баннера"><img <?= $src ? 'src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"' : '' ?> alt="<?= $alt ?>" loading="lazy" class="slide-card-img"><span class="slide-card-overlay"><?= renderSvgIcon('search') ?> Предпросмотр</span><span class="slide-order-badge">#<?= (int)($slide['sort_order'] ?? 0) ?></span></button>
  <div class="slide-card-body"><div class="slide-card-row"><label class="switch-label"><input type="checkbox" aria-label="Показывать слайд на сайте" class="slide-status-toggle" <?= $active ? 'checked' : '' ?>><span class="slider"></span></label><span class="slide-status-text"><?= $active ? 'Активен' : 'Скрыт' ?></span><div class="slide-card-actions"><button type="button" class="btn-icon btn-move-up" title="Раньше" aria-label="Переместить слайд раньше"><?= renderSvgIcon('arrow-left') ?></button><button type="button" class="btn-icon btn-move-down" title="Позже" aria-label="Переместить слайд позже"><?= renderSvgIcon('arrow-left', 'icon icon-rotate-180') ?></button><button type="button" class="btn-icon btn-icon-danger btn-delete-slide" title="Удалить" aria-label="Удалить слайд"><?= renderSvgIcon('trash') ?></button></div></div>
  <div class="slide-alt-wrap"><input type="text" class="form-input slide-alt-input" value="<?= $alt ?>" maxlength="255" placeholder="Описание изображения" aria-label="Описание изображения"><button type="button" class="btn-icon btn-save-alt" title="Сохранить описание" aria-label="Сохранить описание"><?= renderSvgIcon('check') ?></button></div>
  <label class="replace-image">Заменить изображение<input type="file" class="slide-replace-input" accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Заменить изображение слайда"></label></div>
</article>
<?php } ?>
<script src="/boyforgeadmin/assets/js/content.js?v=<?= filemtime(__DIR__ . '/assets/js/content.js') ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
