"use strict";
document.addEventListener("DOMContentLoaded", () => {
  const token = document.querySelector('meta[name="csrf-token"]').content;
  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];
  const queues = { desktop: [], mobile: [] };
  let busy = false;
  let deleteCard = null;
  const preview = $("#previewModal");
  const deletion = $("#deleteSlideModal");
  const toast = (message, type = "success") => window.showToast(message, type);

  async function request(action, values) {
    const body = values instanceof FormData ? values : new URLSearchParams(values);
    body.set("csrf_token", token);
    let response;
    try { response = await fetch(`api.php?action=slider_${action}`, { method: "POST", body }); }
    catch { throw Object.assign(Error("Связь с сервером прервана. Обновите страницу и проверьте результат перед повтором."), { uncertain: true }); }
    let data;
    try { data = await response.json(); }
    catch { throw Object.assign(Error(response.status === 413 ? "Файл превышает лимит сервера." : "Сервер вернул некорректный ответ. Обновите страницу и проверьте результат."), { uncertain: response.status !== 413 }); }
    if (!response.ok || !data.success) throw Error(data.error || "Не удалось сохранить изменения.");
    return data;
  }

  async function mutation(fn) {
    if (busy) return;
    busy = true;
    $$(".content-editor input, .content-editor button:not(.device-btn), #confirmDeleteSlideBtn, #cancelDeleteSlideBtn").forEach(el => { el.disabled = true; });
    deletion.dataset.busy = "true";
    try { await fn(); }
    catch (error) { toast(error.message || "Ошибка сети. Обновите страницу и проверьте результат.", "error"); }
    finally {
      busy = false;
      deletion.dataset.busy = "false";
      $$(".content-editor input, .content-editor button, #confirmDeleteSlideBtn, #cancelDeleteSlideBtn").forEach(el => { el.disabled = false; });
      refresh();
    }
  }

  function selectDevice(device, focus = false) {
    $$(".device-btn").forEach(button => {
      const active = button.dataset.device === device;
      button.classList.toggle("active", active);
      button.setAttribute("aria-selected", String(active));
      button.tabIndex = active ? 0 : -1;
      if (active && focus) button.focus();
    });
    $$(".device-panel").forEach(panel => {
      const active = panel.id === `panel-${device}`;
      panel.hidden = !active;
      panel.classList.toggle("active", active);
    });
    try { sessionStorage.setItem("slider-device", device); } catch {}
  }
  $$(".device-btn").forEach(button => {
    button.addEventListener("click", () => selectDevice(button.dataset.device));
    button.addEventListener("keydown", event => {
      if (["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) {
        event.preventDefault();
        selectDevice(event.key === "Home" ? "desktop" : event.key === "End" ? "mobile" : button.dataset.device === "desktop" ? "mobile" : "desktop", true);
      }
    });
  });
  try { if (sessionStorage.getItem("slider-device") === "mobile") selectDevice("mobile"); } catch {}

  function openPreview(src, caption) {
    $("#previewModalImg").src = src;
    $("#previewModalCaption").textContent = caption || "Предпросмотр изображения";
    preview.showModal();
  }
  $("#closePreviewBtn").addEventListener("click", () => preview.close());
  preview.addEventListener("close", () => $("#previewModalImg").removeAttribute("src"));
  [preview, deletion].forEach(dialog => {
    dialog.addEventListener("click", event => {
      const box = dialog.getBoundingClientRect();
      if (event.target === dialog && dialog.dataset.busy !== "true" && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) dialog.close();
    });
  });
  deletion.addEventListener("cancel", event => { if (busy) event.preventDefault(); });
  $("#cancelDeleteSlideBtn").addEventListener("click", () => deletion.close());
  deletion.addEventListener("close", () => { deleteCard = null; });
  $("#confirmDeleteSlideBtn").addEventListener("click", () => mutation(async () => {
    if (!deleteCard) return;
    try {
      await request("delete", { id: deleteCard.dataset.id });
      deleteCard.remove();
      deletion.close();
      toast("Слайд удалён");
    } catch (error) { $(".delete-error", deletion).textContent = error.message; }
  }));

  function validFile(file) {
    if (!["image/jpeg", "image/png", "image/webp", "image/gif"].includes(file.type)) throw Error(`${file.name}: выберите JPG, PNG, WebP или GIF.`);
    if (!file.size || file.size > 15 * 1024 * 1024) throw Error(`${file.name}: допустимый размер — до 15 МБ.`);
  }
  function renderQueue(device) {
    const container = $(`#queue-${device}`);
    container.hidden = queues[device].length === 0;
    const grid = $(".queue-grid", container);
    grid.replaceChildren();
    queues[device].forEach(item => {
      const card = document.createElement("div");
      card.className = "queue-card";
      const button = document.createElement("button");
      button.type = "button";
      button.setAttribute("aria-label", `Предпросмотр: ${item.file.name}`);
      const img = document.createElement("img");
      img.src = item.url;
      img.alt = item.file.name;
      img.loading = "lazy";
      button.append(img);
      button.addEventListener("click", () => openPreview(item.url, item.file.name));
      const caption = document.createElement("p");
      caption.textContent = item.file.name;
      const status = document.createElement("small");
      status.textContent = item.error || `${(item.file.size / 1024 / 1024).toFixed(1)} МБ`;
      status.className = item.error ? "queue-error" : "";
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "queue-remove";
      remove.textContent = "×";
      remove.setAttribute("aria-label", `Убрать файл: ${item.file.name}`);
      remove.addEventListener("click", () => {
        if (busy) return;
        URL.revokeObjectURL(item.url);
        queues[device] = queues[device].filter(entry => entry !== item);
        renderQueue(device);
      });
      card.append(button, caption, status, remove);
      grid.append(card);
    });
  }
  function enqueue(files, device) {
    if (busy) return;
    const errors = [];
    Array.from(files).forEach(file => {
      try { validFile(file); queues[device].push({ file, url: URL.createObjectURL(file) }); }
      catch (error) { errors.push(error.message); }
    });
    renderQueue(device);
    if (errors.length) toast(errors.join("\n"), "error");
  }
  $$(".upload-dropzone").forEach(zone => {
    const device = zone.dataset.device;
    const input = $("input", zone);
    input.addEventListener("change", () => { enqueue(input.files, device); input.value = ""; });
    zone.addEventListener("dragover", event => { event.preventDefault(); if (!busy) zone.classList.add("dragover"); });
    ["dragleave", "dragend"].forEach(name => zone.addEventListener(name, () => zone.classList.remove("dragover")));
    zone.addEventListener("drop", event => { event.preventDefault(); zone.classList.remove("dragover"); enqueue(event.dataTransfer.files, device); });
    const queue = $(`#queue-${device}`);
    $(".btn-clear-queue", queue).addEventListener("click", () => {
      queues[device].forEach(item => URL.revokeObjectURL(item.url));
      queues[device] = [];
      renderQueue(device);
    });
    $(".btn-upload-queue", queue).addEventListener("click", () => mutation(async () => {
      const batch = [...queues[device]];
      let uploaded = 0;
      for (let index = 0; index < batch.length; index++) {
        const item = batch[index];
        $(".queue-progress", queue).textContent = `Загрузка ${index + 1} из ${batch.length}…`;
        const data = new FormData();
        data.set("device_type", device);
        data.set("image", item.file);
        try {
          const response = await request("upload", data);
          const card = createCard(response.slide);
          $(`#grid-${device}`).append(card);
          queues[device] = queues[device].filter(entry => entry !== item);
          URL.revokeObjectURL(item.url);
          uploaded++;
          refresh();
        } catch (error) {
          item.error = error.message;
          // A lost response is ambiguous: stop to avoid silently duplicating files.
          if (error.uncertain) { item.error = "Связь прервана. Перед повтором обновите страницу и проверьте, появился ли слайд."; break; }
        }
      }
      renderQueue(device);
      $(".queue-progress", queue).textContent = `Загружено: ${uploaded} из ${batch.length}. Оставшиеся файлы не удалены из очереди.`;
      toast(`Загружено: ${uploaded} из ${batch.length}${queues[device].length ? ". Проверьте ошибки в очереди." : ""}`, queues[device].length ? "error" : "success");
    }));
  });

  function createCard(slide) {
    const card = $("#slideCardTemplate").content.firstElementChild.cloneNode(true);
    card.dataset.id = slide.id;
    card.dataset.device = slide.device_type;
    card.dataset.order = slide.sort_order;
    card.classList.toggle("slide-card--mobile", slide.device_type === "mobile");
    $(".slide-card-img", card).src = "/" + slide.image_path.replace(/^\/+/, "");
    $(".slide-card-img", card).alt = slide.alt_text;
    $(".slide-alt-input", card).value = slide.alt_text;
    $(".slide-status-toggle", card).checked = Number(slide.is_active) === 1;
    bindCard(card);
    return card;
  }
  function bindCard(card) {
    const id = card.dataset.id;
    const image = $(".slide-card-img", card);
    const toggle = $(".slide-status-toggle", card);
    $(".slide-card-img-wrap", card).addEventListener("click", () => openPreview(image.src, image.alt));
    toggle.addEventListener("change", () => {
      const checked = toggle.checked;
      mutation(async () => {
        try { await request("toggle", { id, status: checked ? "1" : "0" }); toast(checked ? "Слайд опубликован" : "Слайд скрыт"); }
        catch (error) { toggle.checked = !checked; throw error; }
      });
    });
    const altInput = $(".slide-alt-input", card);
    const saveAlt = () => mutation(async () => {
      const text = altInput.value.trim();
      await request("update", { id, alt_text: text });
      altInput.value = text;
      image.alt = text;
      toast("Alt-текст сохранён");
    });
    $(".btn-save-alt", card).addEventListener("click", saveAlt);
    altInput.addEventListener("keydown", event => { if (event.key === "Enter") { event.preventDefault(); saveAlt(); } });
    ["up", "down"].forEach(direction => $(".btn-move-" + direction, card).addEventListener("click", () => mutation(async () => {
      const grid = card.parentElement;
      const before = [...grid.children];
      const sibling = direction === "up" ? card.previousElementSibling : card.nextElementSibling;
      if (!sibling) return;
      if (direction === "up") grid.insertBefore(card, sibling); else grid.insertBefore(sibling, card);
      try {
        await request("reorder", { device_type: card.dataset.device, ids: JSON.stringify([...grid.children].map(el => el.dataset.id)) });
        toast("Порядок сохранён");
      } catch (error) { before.forEach(el => grid.append(el)); throw error; }
    })));
    $(".btn-delete-slide", card).addEventListener("click", () => {
      deleteCard = card;
      $(".delete-error", deletion).textContent = "";
      deletion.showModal();
      $("#cancelDeleteSlideBtn").focus();
    });
    $(".slide-replace-input", card).addEventListener("change", event => {
      const file = event.target.files[0];
      event.target.value = "";
      if (!file) return;
      try { validFile(file); } catch (error) { toast(error.message, "error"); return; }
      mutation(async () => {
        const data = new FormData();
        data.set("id", id);
        data.set("device_type", card.dataset.device);
        data.set("image", file);
        const response = await request("replace", data);
        image.src = "/" + response.slide.image_path.replace(/^\/+/, "");
        toast("Изображение заменено");
      });
    });
  }
  function refresh() {
    let total = 0;
    ["desktop", "mobile"].forEach(device => {
      const cards = $$(".slide-card", $(`#grid-${device}`));
      const label = device[0].toUpperCase() + device.slice(1);
      $(`#stat${label}Count`).textContent = cards.length;
      $(`#badge${label}`).textContent = cards.length;
      $(`#active-${device}`).textContent = cards.filter(card => $(".slide-status-toggle", card).checked).length;
      $(`#empty-${device}`).hidden = cards.length > 0;
      cards.forEach((card, index) => {
        $(".slide-order-badge", card).textContent = `#${index + 1}`;
        $(".slide-status-text", card).textContent = $(".slide-status-toggle", card).checked ? "Активен" : "Скрыт";
        card.classList.toggle("is-hidden", !$(".slide-status-toggle", card).checked);
        $(".btn-move-up", card).disabled = busy || index === 0;
        $(".btn-move-down", card).disabled = busy || index === cards.length - 1;
        if (busy) $$("button, input", card).forEach(el => { el.disabled = true; });
      });
      total += cards.length;
    });
    $("#statTotalCount").textContent = total;
  }
  $$(".slide-card").forEach(bindCard);
  refresh();
  window.addEventListener("beforeunload", event => {
    if (busy || queues.desktop.length || queues.mobile.length) { event.preventDefault(); event.returnValue = ""; }
  });
});
