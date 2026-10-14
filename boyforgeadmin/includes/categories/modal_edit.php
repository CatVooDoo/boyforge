<?php
declare(strict_types=1);
?>
<!-- Модальное окно создания / редактирования категории -->
<div class="modal-overlay" id="categoryModal">
  <div class="modal-box">
    <div class="modal-title" id="categoryModalTitle">Новая категория</div>
    <form id="categoryForm" autocomplete="off">
      <input type="hidden" name="id" id="catFormId" value="0">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

      <div class="form-group" style="margin-bottom: 16px;">
        <label for="catFormName" class="form-label">Название категории *</label>
        <input type="text" id="catFormName" name="name" class="form-input" placeholder="Например: Худи или Кепка" required>
      </div>

      <div class="form-group" style="margin-bottom: 16px;">
        <label for="catFormSlug" class="form-label">Системный код (Slug) *</label>
        <input type="text" id="catFormSlug" name="slug" class="form-input" placeholder="Например: hoodie или cap" required>
        <div class="form-help">Используется в URL каталога (только латинские буквы, цифры и дефис)</div>
      </div>

      <div class="form-grid-2" style="margin-bottom: 20px;">
        <div class="form-group" style="margin-bottom: 0;">
          <label for="catFormOrder" class="form-label">Порядок вывода</label>
          <input type="number" id="catFormOrder" name="sort_order" class="form-input" value="0">
        </div>

        <div class="form-group" style="margin-bottom: 0; display: flex; align-items: flex-end; padding-bottom: 10px;">
          <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
            <input type="checkbox" id="catFormActive" name="is_active" value="1" checked style="width: 18px; height: 18px;">
            <span style="font-size: 12px; font-weight: 600; text-transform: uppercase;">Активна</span>
          </label>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="btnCancelCategoryModal">Отмена</button>
        <button type="submit" class="btn btn-primary" id="btnSaveCategory">Сохранить</button>
      </div>
    </form>
  </div>
</div>
