<?php
declare(strict_types=1);
?>
<!-- Модальное окно подтверждения удаления категории -->
<div class="modal-overlay" id="deleteCategoryModal">
  <div class="modal-box">
    <div class="modal-title">Удаление категории</div>
    <div class="modal-text" id="deleteCategoryModalText">
      Вы действительно хотите удалить эту категорию?
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" id="btnCancelDeleteCategory">Отмена</button>
      <button type="button" class="btn btn-danger" id="btnConfirmDeleteCategory">Удалить категорию</button>
    </div>
  </div>
</div>
