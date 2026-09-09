"use strict";

document.addEventListener("DOMContentLoaded", function () {
  // Toast notifications
  window.showToast = function (message, type = "success") {
    let container = document.getElementById("toastContainer");
    if (!container) {
      container = document.createElement("div");
      container.id = "toastContainer";
      document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = "0";
      toast.style.transform = "translateY(10px)";
      toast.style.transition = "all 0.3s ease";
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  };

  // CSRF token
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

  // Table Search and Filter
  const searchInput = document.getElementById("searchInput");
  const filterCat = document.getElementById("filterCat");
  const filterStatus = document.getElementById("filterStatus");
  const tableRows = document.querySelectorAll(".product-row");

  function filterTable() {
    const query = (searchInput?.value || "").toLowerCase().trim();
    const cat = filterCat?.value || "all";
    const status = filterStatus?.value || "all";

    tableRows.forEach((row) => {
      const name = (row.getAttribute("data-name") || "").toLowerCase();
      const rowCat = row.getAttribute("data-cat") || "";
      const rowStatus = row.getAttribute("data-status") || "";

      const matchesQuery = !query || name.includes(query);
      const matchesCat = cat === "all" || rowCat === cat;
      const matchesStatus = status === "all" || rowStatus === status;

      if (matchesQuery && matchesCat && matchesStatus) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    });
  }

  if (searchInput) searchInput.addEventListener("input", filterTable);
  if (filterCat) filterCat.addEventListener("change", filterTable);
  if (filterStatus) filterStatus.addEventListener("change", filterTable);

  // Status Toggle
  document.querySelectorAll(".status-switch-input").forEach((toggle) => {
    toggle.addEventListener("change", function () {
      const id = this.getAttribute("data-id");
      const isChecked = this.checked ? 1 : 0;
      const row = this.closest(".product-row");

      fetch("api.php?action=toggle_status", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `id=${encodeURIComponent(id)}&status=${isChecked}&csrf_token=${encodeURIComponent(csrfToken)}`,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            if (row) {
              row.setAttribute("data-status", isChecked ? "active" : "inactive");
            }
            showToast(data.message || "Статус товара обновлен", "success");
          } else {
            showToast(data.error || "Ошибка при обновлении статуса", "error");
            this.checked = !this.checked;
          }
        })
        .catch(() => {
          showToast("Ошибка сети при отправке запроса", "error");
          this.checked = !this.checked;
        });
    });
  });

  // Modal Delete logic
  const deleteModal = document.getElementById("deleteModal");
  let productToDeleteId = null;

  document.querySelectorAll(".btn-delete-product").forEach((btn) => {
    btn.addEventListener("click", function () {
      productToDeleteId = this.getAttribute("data-id");
      const name = this.getAttribute("data-name") || "этот товар";
      const nameEl = document.getElementById("deleteProductName");
      if (nameEl) nameEl.textContent = name;
      if (deleteModal) deleteModal.classList.add("active");
    });
  });

  const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener("click", function () {
      if (!productToDeleteId) return;

      fetch("api.php?action=delete", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `id=${encodeURIComponent(productToDeleteId)}&csrf_token=${encodeURIComponent(csrfToken)}`,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            const row = document.querySelector(`.product-row[data-id="${productToDeleteId}"]`);
            if (row) row.remove();
            showToast("Товар удален", "success");
            if (deleteModal) deleteModal.classList.remove("active");
            updateStatsCounts();
          } else {
            showToast(data.error || "Ошибка при удалении", "error");
          }
        })
        .catch(() => {
          showToast("Ошибка сети", "error");
        });
    });
  }

  const cancelDeleteBtn = document.getElementById("cancelDeleteBtn");
  if (cancelDeleteBtn && deleteModal) {
    cancelDeleteBtn.addEventListener("click", function () {
      deleteModal.classList.remove("active");
    });
  }

  // Duplicate product
  document.querySelectorAll(".btn-duplicate-product").forEach((btn) => {
    btn.addEventListener("click", function () {
      const id = this.getAttribute("data-id");

      fetch("api.php?action=duplicate", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(csrfToken)}`,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            showToast("Товар продублирован. Обновление страницы...", "success");
            setTimeout(() => location.reload(), 800);
          } else {
            showToast(data.error || "Ошибка при дублировании", "error");
          }
        })
        .catch(() => {
          showToast("Ошибка сети", "error");
        });
    });
  });



  function updateStatsCounts() {
    const totalEl = document.getElementById("statTotalCount");
    const activeEl = document.getElementById("statActiveCount");
    const hiddenEl = document.getElementById("statHiddenCount");

    const visibleRows = document.querySelectorAll(".product-row");
    const activeRows = document.querySelectorAll('.product-row[data-status="active"]');
    const hiddenRows = document.querySelectorAll('.product-row[data-status="inactive"]');

    if (totalEl) totalEl.textContent = visibleRows.length;
    if (activeEl) activeEl.textContent = activeRows.length;
    if (hiddenEl) hiddenEl.textContent = hiddenRows.length;
  }

  // Specs Editor logic (in edit.php)
  const specsTableBody = document.getElementById("specsTableBody");
  const btnAddSpec = document.getElementById("btnAddSpec");
  const btnPresetTshirt = document.getElementById("btnPresetTshirt");
  const btnPresetSweat = document.getElementById("btnPresetSweat");

  function createSpecRow(key = "", val = "") {
    const tr = document.createElement("tr");
    tr.className = "specs-row";
    tr.innerHTML = `
      <td><input type="text" class="form-input spec-key" value="${escapeHtml(key)}" placeholder="Например, Материал"></td>
      <td><input type="text" class="form-input spec-val" value="${escapeHtml(val)}" placeholder="Например, Хлопок 100%"></td>
      <td style="text-align: right; width: 44px;">
        <button type="button" class="btn-icon btn-icon-danger btn-remove-spec" title="Удалить">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </td>
    `;
    tr.querySelector(".btn-remove-spec").addEventListener("click", () => tr.remove());
    return tr;
  }

  if (btnAddSpec && specsTableBody) {
    btnAddSpec.addEventListener("click", () => {
      specsTableBody.appendChild(createSpecRow());
    });
  }

  document.querySelectorAll(".btn-remove-spec").forEach((btn) => {
    btn.addEventListener("click", function () {
      this.closest(".specs-row")?.remove();
    });
  });

  const BASE_SPECS = [
    ["Материал", "Футер 2-нитка, 95% хлопок / 5% эластан"],
    ["Плотность", "240 г/м²"],
    ["Печать", "DTF"],
    ["Размеры", "S–3XL"],
    ["Пошив", "Россия"],
  ];

  const SWEAT_SPECS = [
    ["Материал", "Футер 3-нитка, хлопок"],
    ["Плотность", "330 г/м²"],
    ["Печать", "DTF"],
    ["Размеры", "S–3XL"],
    ["Пошив", "Россия"],
  ];

  if (btnPresetTshirt && specsTableBody) {
    btnPresetTshirt.addEventListener("click", () => {
      specsTableBody.innerHTML = "";
      BASE_SPECS.forEach(([k, v]) => specsTableBody.appendChild(createSpecRow(k, v)));
      showToast("Заполнен шаблон футболки", "success");
    });
  }

  if (btnPresetSweat && specsTableBody) {
    btnPresetSweat.addEventListener("click", () => {
      specsTableBody.innerHTML = "";
      SWEAT_SPECS.forEach(([k, v]) => specsTableBody.appendChild(createSpecRow(k, v)));
      showToast("Заполнен шаблон свитшота", "success");
    });
  }

  // Gallery uploads and management (in edit.php)
  const mainImageInput = document.getElementById("mainImageInput");
  const mainImageFile = document.getElementById("mainImageFile");
  const mainImagePreview = document.getElementById("mainImagePreview");

  if (mainImageFile) {
    mainImageFile.addEventListener("change", function () {
      if (!this.files || !this.files[0]) return;
      uploadSingleImage(this.files[0], (uploadedPath) => {
        if (mainImageInput) mainImageInput.value = uploadedPath;
        if (mainImagePreview) {
          mainImagePreview.src = "../" + uploadedPath;
          mainImagePreview.style.display = "block";
        }
        showToast("Главное фото загружено", "success");
      });
    });
  }

  if (mainImageInput) {
    mainImageInput.addEventListener("input", function () {
      if (mainImagePreview) {
        const val = this.value.trim();
        if (val) {
          mainImagePreview.src = val.startsWith("http") ? val : "../" + val;
          mainImagePreview.style.display = "block";
        } else {
          mainImagePreview.style.display = "none";
        }
      }
    });
  }

  const galleryFiles = document.getElementById("galleryFiles");
  const galleryContainer = document.getElementById("galleryContainer");

  if (galleryFiles && galleryContainer) {
    galleryFiles.addEventListener("change", function () {
      if (!this.files || !this.files.length) return;
      Array.from(this.files).forEach((file) => {
        uploadSingleImage(file, (uploadedPath) => {
          addGalleryItem(uploadedPath);
          showToast("Фото добавлено в галерею", "success");
        });
      });
    });
  }

  function addGalleryItem(path) {
    const div = document.createElement("div");
    div.className = "gallery-card";
    const displaySrc = path.startsWith("http") ? path : "../" + path;
    div.innerHTML = `
      <img src="${escapeHtml(displaySrc)}" alt="Фото товара">
      <input type="hidden" name="gallery_imgs[]" value="${escapeHtml(path)}">
      <div class="gallery-card-actions">
        <button type="button" class="gallery-remove-btn" title="Удалить фото">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </div>
    `;
    div.querySelector(".gallery-remove-btn").addEventListener("click", () => div.remove());
    galleryContainer.appendChild(div);
  }

  document.querySelectorAll(".gallery-remove-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      this.closest(".gallery-card")?.remove();
    });
  });

  function uploadSingleImage(file, callback) {
    const formData = new FormData();
    formData.append("image", file);
    formData.append("csrf_token", csrfToken);

    fetch("api.php?action=upload_image", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success && data.path) {
          callback(data.path);
        } else {
          showToast(data.error || "Ошибка при загрузке фото", "error");
        }
      })
      .catch(() => {
        showToast("Сетевая ошибка при загрузке", "error");
      });
  }

  // Before Form Submit in edit.php: compile specs to hidden json
  const productForm = document.getElementById("productForm");
  if (productForm) {
    productForm.addEventListener("submit", function () {
      const specs = [];
      document.querySelectorAll(".specs-row").forEach((row) => {
        const k = row.querySelector(".spec-key")?.value.trim();
        const v = row.querySelector(".spec-val")?.value.trim();
        if (k || v) {
          specs.push([k || "", v || ""]);
        }
      });
      const specsHidden = document.getElementById("specsHiddenInput");
      if (specsHidden) {
        specsHidden.value = JSON.stringify(specs);
      }
    });
  }

  // Badges / Tags Management (edit.php)
  const tagsContainer = document.getElementById("tagsContainer");
  const customTagInput = document.getElementById("custom_tag");
  const btnAddCustomTag = document.getElementById("btnAddCustomTag");

  function addBadgeItem(tagName, checked = true) {
    tagName = tagName.trim();
    if (!tagName || !tagsContainer) return;

    // Check if tag already exists in the container (case-insensitive)
    const existing = Array.from(tagsContainer.querySelectorAll(".badge-checkbox-item")).find(
      (item) => (item.getAttribute("data-tag") || "").toLowerCase() === tagName.toLowerCase()
    );

    if (existing) {
      const cb = existing.querySelector('input[type="checkbox"]');
      if (cb) {
        cb.checked = true;
      }
      existing.style.outline = "2px solid #000";
      setTimeout(() => { existing.style.outline = ""; }, 1000);
      return;
    }

    // Create new badge element
    const label = document.createElement("label");
    label.className = "badge-checkbox-item";
    label.setAttribute("data-tag", tagName);
    label.innerHTML = `
      <input type="checkbox" name="tags[]" value="${escapeHtml(tagName)}" ${checked ? "checked" : ""}>
      <span class="badge-tag-text">${escapeHtml(tagName)}</span>
      <button type="button" class="badge-delete-btn" title="Удалить бейдж из общего списка" data-tag="${escapeHtml(tagName)}" aria-label="Удалить">&times;</button>
    `;

    bindBadgeDelete(label.querySelector(".badge-delete-btn"));
    tagsContainer.appendChild(label);

    // Save to database via API
    const formData = new FormData();
    formData.append("csrf_token", csrfToken);
    formData.append("name", tagName);

    fetch("api.php?action=save_tag", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          showToast(`Бейдж «${tagName}» сохранен в базе`, "success");
        }
      })
      .catch(() => {});
  }

  function bindBadgeDelete(btn) {
    if (!btn) return;
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const tagName = this.getAttribute("data-tag") || "";
      if (!tagName) return;

      if (!confirm(`Удалить бейдж «${tagName}» из общего списка? Он перестанет отображаться для выбора у всех товаров.`)) {
        return;
      }

      const itemLabel = this.closest(".badge-checkbox-item");

      const formData = new FormData();
      formData.append("csrf_token", csrfToken);
      formData.append("name", tagName);

      fetch("api.php?action=delete_tag", {
        method: "POST",
        body: formData,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            itemLabel?.remove();
            showToast(data.message || `Бейдж «${tagName}» удален`, "success");
          } else {
            showToast(data.error || "Ошибка при удалении бейджа", "error");
          }
        })
        .catch(() => {
          showToast("Сетевая ошибка при удалении", "error");
        });
    });
  }

  if (tagsContainer) {
    tagsContainer.querySelectorAll(".badge-delete-btn").forEach(bindBadgeDelete);
  }

  if (btnAddCustomTag && customTagInput) {
    btnAddCustomTag.addEventListener("click", function (e) {
      e.preventDefault();
      const val = customTagInput.value.trim();
      if (val) {
        addBadgeItem(val, true);
        customTagInput.value = "";
      }
    });

    customTagInput.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        const val = this.value.trim();
        if (val) {
          addBadgeItem(val, true);
          this.value = "";
        }
      }
    });
  }

  function escapeHtml(str) {
    if (!str) return "";
    return str
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
});
