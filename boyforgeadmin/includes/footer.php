</main>

<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-title">Подтверждение удаления</div>
    <div class="modal-text">
      Вы действительно хотите удалить товар <strong id="deleteProductName" style="color: #000;"></strong>? Данное действие нельзя отменить.
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">Отмена</button>
      <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Удалить товар</button>
    </div>
  </div>
</div>

<div id="toastContainer"></div>

<script src="assets/js/admin.js?v=<?= time() ?>"></script>
</body>
</html>
