"use strict";

document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById("categoryModal");
  const modalTitle = document.getElementById("categoryModalTitle");
  const form = document.getElementById("categoryForm");
  const idInput = document.getElementById("catFormId");
  const nameInput = document.getElementById("catFormName");
  const slugInput = document.getElementById("catFormSlug");
  const orderInput = document.getElementById("catFormOrder");
  const activeInput = document.getElementById("catFormActive");

  function slugify(text) {
    const a = "абвгдеёжзийклмнопрстуфхцчшщъыьэюя";
    const b = "a|b|v|g|d|e|zh|z|i|y|k|l|m|n|o|p|r|s|t|u|f|h|ts|ch|sh|shch||y||e|yu|ya".split("|");
    let res = "";
    for (let i = 0; i < text.length; i++) {
      let ch = text[i].toLowerCase();
      let idx = a.indexOf(ch);
      if (idx !== -1) res += b[idx];
      else if (/[a-z0-9]/.test(ch)) res += ch;
      else if (ch === ' ' || ch === '-') res += '-';
    }
    return res.replace(/-+/g, '-').replace(/^-|-$/g, '');
  }

  if (nameInput && idInput && slugInput) {
    nameInput.addEventListener("input", function () {
      if (idInput.value === "0") {
        slugInput.value = slugify(this.value);
      }
    });
  }

  const btnOpenAdd = document.getElementById("btnOpenAddCategoryModal");
  if (btnOpenAdd && modal && modalTitle && idInput && nameInput && slugInput && orderInput && activeInput) {
    btnOpenAdd.addEventListener("click", function () {
      modalTitle.textContent = "Новая категория";
      idInput.value = "0";
      nameInput.value = "";
      slugInput.value = "";
      orderInput.value = "0";
      activeInput.checked = true;
      modal.classList.add("active");
      setTimeout(() => nameInput.focus(), 100);
    });
  }

  document.querySelectorAll(".btn-edit-category").forEach((btn) => {
    btn.addEventListener("click", function () {
      if (!modal || !modalTitle || !idInput || !nameInput || !slugInput || !orderInput || !activeInput) return;
      modalTitle.textContent = "Редактирование категории";
      idInput.value = this.getAttribute("data-id") || "0";
      nameInput.value = this.getAttribute("data-name") || "";
      slugInput.value = this.getAttribute("data-slug") || "";
      orderInput.value = this.getAttribute("data-order") || "0";
      activeInput.checked = this.getAttribute("data-active") === "1";
      modal.classList.add("active");
      setTimeout(() => nameInput.focus(), 100);
    });
  });

  const btnCancelCat = document.getElementById("btnCancelCategoryModal");
  if (btnCancelCat && modal) {
    btnCancelCat.addEventListener("click", function () {
      modal.classList.remove("active");
    });
  }

  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      const btnSave = document.getElementById("btnSaveCategory");
      if (btnSave) btnSave.disabled = true;

      const data = new FormData(form);

      fetch("api.php?action=save_category", {
        method: "POST",
        body: data
      })
        .then((res) => res.json())
        .then((res) => {
          if (btnSave) btnSave.disabled = false;
          if (res.success) {
            if (typeof showToast === 'function') {
              showToast(res.message || "Категория сохранена", "success");
            }
            if (modal) modal.classList.remove("active");
            setTimeout(() => location.reload(), 600);
          } else {
            if (typeof showToast === 'function') {
              showToast(res.error || "Ошибка сохранения", "error");
            }
          }
        })
        .catch(() => {
          if (btnSave) btnSave.disabled = false;
          if (typeof showToast === 'function') {
            showToast("Ошибка сети", "error");
          }
        });
    });
  }

  document.querySelectorAll(".cat-status-switch-input").forEach((toggle) => {
    toggle.addEventListener("change", function () {
      const id = this.getAttribute("data-id");
      const isChecked = this.checked ? 1 : 0;
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

      fetch("api.php?action=toggle_category_status", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `id=${encodeURIComponent(id)}&status=${isChecked}&csrf_token=${encodeURIComponent(csrf)}`
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            if (typeof showToast === 'function') {
              showToast(data.message || "Статус категории изменен", "success");
            }
          } else {
            if (typeof showToast === 'function') {
              showToast(data.error || "Ошибка при изменении статуса", "error");
            }
            this.checked = !this.checked;
          }
        })
        .catch(() => {
          if (typeof showToast === 'function') {
            showToast("Ошибка сети", "error");
          }
          this.checked = !this.checked;
        });
    });
  });

  const deleteModal = document.getElementById("deleteCategoryModal");
  const deleteText = document.getElementById("deleteCategoryModalText");
  const confirmDeleteBtn = document.getElementById("btnConfirmDeleteCategory");
  let categoryToDeleteId = null;

  document.querySelectorAll(".btn-delete-category").forEach((btn) => {
    btn.addEventListener("click", function () {
      categoryToDeleteId = this.getAttribute("data-id");
      const name = this.getAttribute("data-name") || "";
      const count = parseInt(this.getAttribute("data-count"), 10) || 0;

      if (deleteText) {
        if (count > 0) {
          deleteText.innerHTML = `В категории <strong>«${name}»</strong> находится <strong>${count}</strong> товаров.<br><br>Удаление категории оставит эти товары без категории. Вы уверены, что хотите удалить?`;
        } else {
          deleteText.innerHTML = `Вы действительно хотите удалить категорию <strong>«${name}»</strong>? Действие нельзя отменить.`;
        }
      }

      if (deleteModal) deleteModal.classList.add("active");
    });
  });

  const btnCancelDelete = document.getElementById("btnCancelDeleteCategory");
  if (btnCancelDelete && deleteModal) {
    btnCancelDelete.addEventListener("click", function () {
      deleteModal.classList.remove("active");
    });
  }

  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener("click", function () {
      if (!categoryToDeleteId) return;
      this.disabled = true;
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

      fetch("api.php?action=delete_category", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `id=${encodeURIComponent(categoryToDeleteId)}&csrf_token=${encodeURIComponent(csrf)}`
      })
        .then((res) => res.json())
        .then((data) => {
          confirmDeleteBtn.disabled = false;
          if (data.success) {
            if (typeof showToast === 'function') {
              showToast("Категория удалена", "success");
            }
            if (deleteModal) deleteModal.classList.remove("active");
            const row = document.querySelector(`.category-row[data-id="${categoryToDeleteId}"]`);
            if (row) row.remove();
          } else {
            if (typeof showToast === 'function') {
              showToast(data.error || "Ошибка при удалении", "error");
            }
          }
        })
        .catch(() => {
          confirmDeleteBtn.disabled = false;
          if (typeof showToast === 'function') {
            showToast("Ошибка сети", "error");
          }
        });
    });
  }
});
