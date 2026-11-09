<?php
declare(strict_types=1);

$pageTitle = 'Управление категориями';
require_once __DIR__ . '/includes/header.php';

$sql = "SELECT c.*, COUNT(p.id) AS products_count 
        FROM categories c 
        LEFT JOIN products p ON (p.cat_id = c.slug OR p.cat = c.name) 
        GROUP BY c.id 
        ORDER BY c.sort_order ASC, c.id ASC";
$categories = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$totalCats = count($categories);
$activeCats = 0;
$withProductsCount = 0;

foreach ($categories as $c) {
    if ((int)$c['is_active'] === 1) {
        $activeCats++;
    }
    if ((int)$c['products_count'] > 0) {
        $withProductsCount++;
    }
}
?>

<div class="page-head">
  <div>
    <h1 class="page-title">Категории товаров</h1>
    <div class="page-subtitle">Создание, редактирование и порядок категорий для каталога и карточек товаров</div>
  </div>

  <div>
    <button type="button" class="btn btn-primary" id="btnOpenAddCategoryModal">
      <?= renderSvgIcon('plus') ?>
      <span>Добавить категорию</span>
    </button>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon-wrapper">
      <?= renderSvgIcon('tag') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value"><?= $totalCats ?></span>
      <span class="stat-label">Всего категорий</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrapper" style="color: var(--accent-success);">
      <?= renderSvgIcon('eye') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value"><?= $activeCats ?></span>
      <span class="stat-label">Активно на сайте</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrapper">
      <?= renderSvgIcon('box') ?>
    </div>
    <div class="stat-info">
      <span class="stat-value"><?= $withProductsCount ?></span>
      <span class="stat-label">С товарами</span>
    </div>
  </div>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width: 50px;">ID</th>
          <th>Название категории</th>
          <th>Системный код (Slug)</th>
          <th>Размерная сетка</th>
          <th style="text-align: center; width: 150px;">Товаров в категории</th>
          <th style="text-align: center; width: 110px;">Порядок</th>
          <th style="text-align: center; width: 90px;">Статус</th>
          <th style="text-align: right; width: 110px;">Действия</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($categories)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
              Категорий пока нет. Нажмите «Добавить категорию», чтобы создать первую.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($categories as $cat): ?>
            <?php
              $cId = (int)$cat['id'];
              $cName = htmlspecialchars($cat['name']);
              $cSlug = htmlspecialchars($cat['slug']);
              $cOrder = (int)$cat['sort_order'];
              $cCount = (int)$cat['products_count'];
              $cActive = (int)$cat['is_active'] === 1;
            ?>
            <tr class="category-row" data-id="<?= $cId ?>" data-name="<?= $cName ?>" data-slug="<?= $cSlug ?>" data-order="<?= $cOrder ?>" data-active="<?= $cActive ? '1' : '0' ?>" data-count="<?= $cCount ?>">
              <td style="color: var(--text-dim); font-size: 11px;">#<?= $cId ?></td>

              <td>
                <span style="font-weight: 700; font-size: 14px; color: #000000;"><?= $cName ?></span>
              </td>

              <td>
                <code style="background: var(--bg-card-muted); padding: 3px 8px; border-radius: 4px; font-size: 11px; border: 1px solid var(--border-color); color: #333333;"><?= $cSlug ?></code>
              </td>

              <td>
                <select class="form-select cat-size-chart" data-id="<?= $cId ?>" data-mode="<?= htmlspecialchars($cat['size_chart_mode']) ?>" aria-label="Размерная сетка: <?= $cName ?>">
                  <option value="single" <?= $cat['size_chart_mode'] !== 'dual' ? 'selected' : '' ?>>Одна общая (S–2XL)</option>
                  <option value="dual" <?= $cat['size_chart_mode'] === 'dual' ? 'selected' : '' ?>>Две: мужская и женская</option>
                </select>
                <button type="button" class="btn btn-secondary cat-size-open" data-target="categorySizes<?= $cId ?>" style="margin-top: 10px;">Настроить размеры</button>
              </td>
              <td style="text-align: center;">
                <span class="category-badge" style="background: <?= $cCount > 0 ? 'var(--bg-card-muted)' : '#fafaf8' ?>;">
                  <?= $cCount ?> поз.
                </span>
              </td>

              <td style="text-align: center; font-weight: 600;">
                <?= $cOrder ?>
              </td>

              <td style="text-align: center;">
                <label class="switch-label">
                  <input type="checkbox" class="cat-status-switch-input" data-id="<?= $cId ?>" <?= $cActive ? 'checked' : '' ?>>
                  <span class="slider"></span>
                </label>
              </td>

              <td class="actions-cell">
                <div class="action-btns">
                  <button type="button" class="btn-icon btn-edit-category" data-id="<?= $cId ?>" data-name="<?= $cName ?>" data-slug="<?= $cSlug ?>" data-order="<?= $cOrder ?>" data-active="<?= $cActive ? '1' : '0' ?>" title="Редактировать">
                    <?= renderSvgIcon('edit') ?>
                  </button>

                  <button type="button" class="btn-icon btn-icon-danger btn-delete-category" data-id="<?= $cId ?>" data-name="<?= $cName ?>" data-count="<?= $cCount ?>" title="Удалить">
                    <?= renderSvgIcon('trash') ?>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
.cat-sizes-modal[hidden], .cat-sizes-modal [hidden] { display: none !important; }
.cat-sizes-modal .modal-box { max-width: 650px; max-height: 90vh; overflow-y: auto; }
.cat-sizes-modal .cat-sizes-heading { display: flex; justify-content: space-between; align-items: start; gap: 16px; }
.cat-sizes-modal .cat-sizes-chart { margin: 20px 0; }
.cat-sizes-modal .cat-sizes-toolbar { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0; }
.cat-sizes-modal .cat-sizes-toolbar button { font-size: 11px; padding: 6px 10px; }
.cat-sizes-modal .cat-size-row { display: grid; grid-template-columns: 44px 1fr; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--border-color); }
.cat-sizes-modal .cat-size-options { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; }
.cat-sizes-modal .cat-size-choice { position: relative; cursor: pointer; }
.cat-sizes-modal .cat-size-choice input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.cat-sizes-modal .cat-size-choice span { display: block; text-align: center; padding: 9px 5px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 12px; color: var(--text-muted); }
.cat-sizes-modal .cat-size-choice input:checked + span { border-color: #166534; background: #f0fdf4; color: #166534; font-weight: 600; }
.cat-sizes-modal .cat-size-choice input[value="hidden"]:checked + span { border-color: #64748b; background: #f1f5f9; color: #475569; }
.cat-sizes-modal .cat-size-choice input[value="blocked"]:checked + span { border-color: #b91c1c; background: #fef2f2; color: #b91c1c; }
.cat-sizes-modal .cat-size-choice input:focus-visible + span { outline: 2px solid #111; outline-offset: 2px; }
.cat-sizes-modal .modal-actions { position: sticky; bottom: 0; padding: 14px 0; background: #fff; border-top: 1px solid var(--border-color); }
.cat-sizes-modal .cat-sizes-status { min-height: 20px; margin: 12px 0; font-size: 12px; color: var(--text-muted); }
@media (max-width: 480px) {
  .cat-sizes-modal { padding: 10px; }
  .cat-sizes-modal .modal-box { padding: 18px; }
  .cat-sizes-modal .cat-size-row { grid-template-columns: 30px 1fr; gap: 4px; }
  .cat-sizes-modal .cat-size-choice span { font-size: 10px; padding: 10px 2px; }
}
</style>

<?php foreach ($categories as $cat): ?>
  <?php $sizeRules = categorySizeRules($cat['size_chart_rules'] ?? null); $sizeCatId = (int)$cat['id']; ?>
  <div class="modal-overlay cat-sizes-modal" id="categorySizes<?= $sizeCatId ?>" hidden>
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="categorySizesTitle<?= $sizeCatId ?>">
      <div class="cat-sizes-heading">
        <div>
          <h2 class="modal-title" id="categorySizesTitle<?= $sizeCatId ?>">Размеры · <?= htmlspecialchars($cat['name']) ?></h2>
          <p class="form-help">Настройки применяются ко всем товарам категории.</p>
        </div>
        <button type="button" class="btn-icon cat-size-close" aria-label="Закрыть">✕</button>
      </div>
      <form class="cat-size-rules" data-id="<?= $sizeCatId ?>">
        <p class="form-help">«Скрыт» — не виден покупателю. «Нет в наличии» — виден, но заказать нельзя. «Доступен» — учитывается наличие у товара.</p>
        <?php foreach (['Общая' => commonSizes()] + sizesByGender() as $chart => $chartSizes): ?>
          <section class="cat-sizes-chart" data-size-rules-chart="<?= $chart === 'Общая' ? 'single' : 'dual' ?>" <?= (($cat['size_chart_mode'] === 'dual') !== ($chart !== 'Общая')) ? 'hidden' : '' ?>>
            <strong><?= ['Общая' => 'Общая сетка', 'Мужской' => 'Мужская сетка', 'Женский' => 'Женская сетка'][$chart] ?></strong>
            <div class="cat-sizes-toolbar" aria-label="Массовое изменение: <?= htmlspecialchars($chart) ?>">
              <button type="button" class="btn btn-secondary" data-size-bulk="available">Все доступны</button>
              <button type="button" class="btn btn-secondary" data-size-bulk="hidden">Скрыть все</button>
              <button type="button" class="btn btn-secondary" data-size-bulk="blocked">Все недоступны</button>
            </div>
            <?php foreach ($chartSizes as $size): ?>
              <?php $sizeState = in_array($size, $sizeRules[$chart]['hidden'], true) ? 'hidden' : (in_array($size, $sizeRules[$chart]['blocked'], true) ? 'blocked' : 'available'); ?>
              <div class="cat-size-row" role="group" aria-label="<?= htmlspecialchars($chart . ': ' . $size) ?>">
                <strong><?= htmlspecialchars($size) ?></strong>
                <div class="cat-size-options">
                  <?php foreach (['available' => 'Доступен', 'hidden' => 'Скрыт', 'blocked' => 'Нет в наличии'] as $state => $label): ?>
                    <label class="cat-size-choice">
                      <input type="radio" name="size_rules[<?= htmlspecialchars($chart) ?>][<?= htmlspecialchars($size) ?>]" value="<?= $state ?>" <?= $sizeState === $state ? 'checked' : '' ?>>
                      <span><?= $label ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
        <p class="cat-sizes-status" role="status" aria-live="polite">Изменений нет</p>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary cat-size-close">Закрыть</button>
          <button type="submit" class="btn btn-primary" disabled>Сохранить размеры</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<!-- Модальное окно создания / редактирования категории -->
<div class="modal-overlay" id="categoryModal">
  <div class="modal-box">
    <div class="modal-title" id="categoryModalTitle">Новая категория</div>
    <form id="categoryForm" autocomplete="off">
      <input type="hidden" name="id" id="catFormId" value="0">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

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

<script>
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

  nameInput.addEventListener("input", function () {
    if (idInput.value === "0") {
      slugInput.value = slugify(this.value);
    }
  });

  document.getElementById("btnOpenAddCategoryModal").addEventListener("click", function () {
    modalTitle.textContent = "Новая категория";
    idInput.value = "0";
    nameInput.value = "";
    slugInput.value = "";
    orderInput.value = "0";
    activeInput.checked = true;
    modal.classList.add("active");
    setTimeout(() => nameInput.focus(), 100);
  });

  document.querySelectorAll(".btn-edit-category").forEach((btn) => {
    btn.addEventListener("click", function () {
      modalTitle.textContent = "Редактирование категории";
      idInput.value = this.getAttribute("data-id");
      nameInput.value = this.getAttribute("data-name");
      slugInput.value = this.getAttribute("data-slug");
      orderInput.value = this.getAttribute("data-order") || "0";
      activeInput.checked = this.getAttribute("data-active") === "1";
      modal.classList.add("active");
      setTimeout(() => nameInput.focus(), 100);
    });
  });

  document.getElementById("btnCancelCategoryModal").addEventListener("click", function () {
    modal.classList.remove("active");
  });

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    const btnSave = document.getElementById("btnSaveCategory");
    btnSave.disabled = true;

    const data = new FormData(form);

    fetch("api.php?action=save_category", {
      method: "POST",
      body: data
    })
      .then((res) => res.json())
      .then((res) => {
        btnSave.disabled = false;
        if (res.success) {
          showToast(res.message || "Категория сохранена", "success");
          modal.classList.remove("active");
          setTimeout(() => location.reload(), 600);
        } else {
          showToast(res.error || "Ошибка сохранения", "error");
        }
      })
      .catch(() => {
        btnSave.disabled = false;
        showToast("Ошибка сети", "error");
      });
  });

  document.querySelectorAll(".cat-size-open").forEach((button) => {
    button.addEventListener("click", () => {
      const overlay = document.getElementById(button.dataset.target);
      overlay.hidden = false;
      overlay.classList.add("active");
      overlay.querySelector(".cat-size-close").focus();
    });
  });

  document.querySelectorAll(".cat-size-rules").forEach((form) => {
    const overlay = form.closest(".cat-sizes-modal");
    const saveButton = form.querySelector('button[type="submit"]');
    const status = form.querySelector(".cat-sizes-status");
    const radios = Array.from(form.querySelectorAll('input[type="radio"]'));
    let saving = false;
    function updateStatus() {
      const changed = radios.some((radio) => radio.checked !== radio.defaultChecked);
      saveButton.disabled = saving || !changed;
      status.textContent = changed ? "Есть несохранённые изменения" : "Изменений нет";
    }
    function closeSizes() {
      if (saving) return;
      form.reset();
      updateStatus();
      overlay.classList.remove("active");
      overlay.hidden = true;
      document.querySelector('.cat-size-open[data-target="' + overlay.id + '"]').focus();
    }
    overlay.querySelectorAll(".cat-size-close").forEach((button) => button.addEventListener("click", closeSizes));
    overlay.addEventListener("click", (event) => { if (event.target === overlay) closeSizes(); });
    overlay.addEventListener("keydown", (event) => {
      if (event.key === "Escape") { event.preventDefault(); closeSizes(); }
      if (event.key === "Tab") {
        const focusable = Array.from(overlay.querySelectorAll('button:not([disabled]), input:not([disabled])')).filter((element) => element.getClientRects().length);
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      }
    });
    form.addEventListener("change", updateStatus);
    form.querySelectorAll("[data-size-bulk]").forEach((button) => {
      button.addEventListener("click", () => {
        button.closest("[data-size-rules-chart]").querySelectorAll('input[type="radio"]').forEach((radio) => {
          radio.checked = radio.value === button.dataset.sizeBulk;
        });
        updateStatus();
      });
    });
    form.addEventListener("submit", async function (event) {
      event.preventDefault();
      if (saving || saveButton.disabled) return;
      const data = new FormData(this);
      data.set("id", this.dataset.id);
      data.set("csrf_token", document.querySelector('meta[name="csrf-token"]')?.content || "");
      const submitted = radios.map((radio) => radio.checked);
      saving = true;
      form.querySelectorAll("input, button").forEach((control) => { control.disabled = true; });
      status.textContent = "Сохраняем…";
      try {
        const response = await fetch("api.php?action=set_category_size_rules", { method: "POST", body: data });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || "Ошибка сохранения размеров");
        radios.forEach((radio, index) => { radio.defaultChecked = submitted[index]; });
        status.textContent = "Размеры сохранены для всех товаров категории";
        showToast(status.textContent, "success");
      } catch (error) {
        status.textContent = error.message + ". Изменения не сохранены — попробуйте ещё раз.";
        showToast(error.message, "error");
      } finally {
        saving = false;
        form.querySelectorAll("input, button").forEach((control) => { control.disabled = false; });
        saveButton.disabled = !radios.some((radio) => radio.checked !== radio.defaultChecked);
      }
    });
  });

  document.querySelectorAll(".cat-size-chart").forEach((select) => {
    select.addEventListener("change", async function () {
      const previous = this.dataset.mode;
      this.disabled = true;
      const data = new FormData();
      data.set("id", this.dataset.id);
      data.set("mode", this.value);
      data.set("csrf_token", document.querySelector('meta[name="csrf-token"]')?.content || "");
      try {
        const response = await fetch("api.php?action=set_category_size_chart", { method: "POST", body: data });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || "Ошибка сохранения сетки");
        this.dataset.mode = this.value;
        document.getElementById("categorySizes" + this.dataset.id).querySelectorAll("[data-size-rules-chart]").forEach((group) => {
          group.hidden = group.dataset.sizeRulesChart !== this.value;
        });
        showToast("Размерная сетка сохранена", "success");
      } catch (error) {
        this.value = previous;
        showToast(error.message, "error");
      } finally {
        this.disabled = false;
      }
    });
  });

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
            showToast(data.message || "Статус категории изменен", "success");
          } else {
            showToast(data.error || "Ошибка при изменении статуса", "error");
            this.checked = !this.checked;
          }
        })
        .catch(() => {
          showToast("Ошибка сети", "error");
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
      const name = this.getAttribute("data-name");
      const count = parseInt(this.getAttribute("data-count"), 10) || 0;

      if (count > 0) {
        deleteText.innerHTML = `В категории <strong>«${name}»</strong> находится <strong>${count}</strong> товаров.<br><br>Удаление категории оставит эти товары без категории. Вы уверены, что хотите удалить?`;
      } else {
        deleteText.innerHTML = `Вы действительно хотите удалить категорию <strong>«${name}»</strong>? Действие нельзя отменить.`;
      }

      deleteModal.classList.add("active");
    });
  });

  document.getElementById("btnCancelDeleteCategory").addEventListener("click", function () {
    deleteModal.classList.remove("active");
  });

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
          showToast("Категория удалена", "success");
          deleteModal.classList.remove("active");
          const row = document.querySelector(`.category-row[data-id="${categoryToDeleteId}"]`);
          if (row) row.remove();
        } else {
          showToast(data.error || "Ошибка при удалении", "error");
        }
      })
      .catch(() => {
        confirmDeleteBtn.disabled = false;
        showToast("Ошибка сети", "error");
      });
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
