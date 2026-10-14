<?php
declare(strict_types=1);
?>
<!-- 5. Ссылка для быстрого заказа в Telegram -->
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
