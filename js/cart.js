"use strict";

(function () {
  "use strict";

  var STORAGE_KEY = "bf_cart_v1";
  var MAX_ITEM_QTY = 20;
  var MAX_TOTAL_UNITS = 50;
  var memoryFallback = [];
  var toastTimer = null;
  var badgeBumpTimer = null;
  var isValidating = false;
  var cartRevision = 0;
  var validationRequest = 0;

  function safeReadStorage() {
    try {
      if (typeof window !== "undefined" && window.localStorage) {
        var raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        var parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
      }
    } catch (e) {}
    return memoryFallback.slice();
  }

  function safeWriteStorage(items) {
    memoryFallback = items.slice();
    try {
      if (typeof window !== "undefined" && window.localStorage) {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
      }
    } catch (e) {}
  }

  function parseNumericPrice(val, fallbackStr) {
    if (typeof val === "number" && isFinite(val) && val > 0) {
      return Math.round(val * 100) / 100;
    }
    var raw = String(val || fallbackStr || "").replace(",", ".").replace(/[^\d.]/g, "");
    var num = parseFloat(raw);
    return isFinite(num) && num > 0 ? Math.round(num * 100) / 100 : 0;
  }

  function formatRub(amount) {
    var num = Math.round((Number(amount) || 0) * 100) / 100;
    var hasDecimals = Math.abs(num - Math.round(num)) > 0.001;
    return num.toLocaleString("ru-RU", {
      minimumFractionDigits: hasDecimals ? 2 : 0,
      maximumFractionDigits: hasDecimals ? 2 : 0
    }) + " ₽";
  }

  function makeItemKey(productId, gender, size) {
    return String(productId || "").trim() + "__" + String(gender || "").trim() + "__" + String(size || "").trim();
  }

  function sanitizeItem(raw) {
    if (!raw || typeof raw !== "object") return null;
    var productId = String(raw.productId || raw.product_id || raw.id || "").trim();
    if (!productId) return null;
    var gender = String(raw.gender || "").trim();
    var size = String(raw.size || "").trim();
    var qty = parseInt(raw.qty, 10);
    if (!isFinite(qty) || qty < 1) qty = 1;
    if (qty > MAX_ITEM_QTY) qty = MAX_ITEM_QTY;

    var priceNumeric = parseNumericPrice(raw.priceNumeric, raw.price);
    var priceDisplay = raw.price ? String(raw.price).trim() : formatRub(priceNumeric);

    return {
      key: makeItemKey(productId, gender, size),
      productId: productId,
      name: String(raw.name || raw.productName || "Товар BOYFORGE").trim(),
      gender: gender,
      size: size,
      qty: qty,
      price: priceDisplay,
      priceNumeric: priceNumeric,
      img: String(raw.img || "").trim(),
      url: String(raw.url || "/catalog").trim(),
      available: raw.available !== false,
      reason: String(raw.reason || "").trim()
    };
  }

  function normalizeStoredList(list) {
    if (!Array.isArray(list)) return [];
    var map = Object.create(null);
    var order = [];
    var runningUnits = 0;

    for (var i = 0; i < list.length; i++) {
      var clean = sanitizeItem(list[i]);
      if (!clean) continue;
      if (runningUnits >= MAX_TOTAL_UNITS) break;

      var allowedAdd = Math.min(clean.qty, MAX_TOTAL_UNITS - runningUnits);
      if (allowedAdd < 1) break;

      if (map[clean.key]) {
        var nextQty = Math.min(MAX_ITEM_QTY, map[clean.key].qty + allowedAdd);
        runningUnits += nextQty - map[clean.key].qty;
        map[clean.key].qty = nextQty;
      } else {
        clean.qty = allowedAdd;
        runningUnits += allowedAdd;
        map[clean.key] = clean;
        order.push(clean.key);
      }
    }

    return order.map(function (k) { return map[k]; });
  }

  var stateItems = normalizeStoredList(safeReadStorage());

  function escapeHtml(str) {
    if (!str) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function getItems() {
    return stateItems.map(function (it) { return Object.assign({}, it); });
  }

  function getAvailableItems() {
    return stateItems
      .filter(function (it) { return it.available !== false && it.priceNumeric > 0; })
      .map(function (it) { return Object.assign({}, it); });
  }

  function getTotalUnits(onlyAvailable) {
    var source = onlyAvailable ? getAvailableItems() : stateItems;
    return source.reduce(function (sum, it) { return sum + (Number(it.qty) || 0); }, 0);
  }

  function getSubtotal() {
    return Math.round(getAvailableItems().reduce(function (sum, it) {
      return sum + (Number(it.priceNumeric) || 0) * (Number(it.qty) || 0);
    }, 0) * 100) / 100;
  }

  function hasUnavailableItems() {
    return stateItems.some(function (it) { return it.available === false; });
  }

  function emitChange(bumpBadge) {
    renderBadges(Boolean(bumpBadge));
    renderDrawer();
    try {
      if (typeof window !== "undefined" && typeof window.CustomEvent === "function") {
        window.dispatchEvent(new CustomEvent("bf:cart:updated", {
          detail: {
            items: getItems(),
            totalUnits: getTotalUnits(false),
            subtotal: getSubtotal()
          }
        }));
      }
    } catch (e) {}
  }

  function persistAndEmit(bumpBadge) {
    cartRevision++;
    stateItems = normalizeStoredList(stateItems);
    safeWriteStorage(stateItems);
    emitChange(bumpBadge);
  }

  function addItem(rawItem) {
    var incoming = sanitizeItem(rawItem);
    if (!incoming) return { added: false, error: "Некорректные данные товара" };

    var currentUnits = getTotalUnits(false);
    if (currentUnits >= MAX_TOTAL_UNITS) {
      return { added: false, error: "В одном заказе может быть не более " + MAX_TOTAL_UNITS + " единиц товара" };
    }

    var addQty = Math.min(incoming.qty, MAX_TOTAL_UNITS - currentUnits);
    var existingIdx = -1;
    for (var i = 0; i < stateItems.length; i++) {
      if (stateItems[i].key === incoming.key) {
        existingIdx = i;
        break;
      }
    }

    if (existingIdx !== -1) {
      var target = stateItems[existingIdx];
      if (target.qty >= MAX_ITEM_QTY) {
        return { added: false, error: "Максимум " + MAX_ITEM_QTY + " шт. одной позиции" };
      }
      target.qty = Math.min(MAX_ITEM_QTY, target.qty + addQty);
      target.price = incoming.price || target.price;
      target.priceNumeric = incoming.priceNumeric || target.priceNumeric;
      target.img = incoming.img || target.img;
      target.url = incoming.url || target.url;
      target.available = true;
      target.reason = "";
    } else {
      incoming.qty = addQty;
      incoming.available = true;
      incoming.reason = "";
      stateItems.push(incoming);
    }

    persistAndEmit(true);
    return { added: true, item: incoming };
  }

  function setItemQty(key, nextQty) {
    var idx = stateItems.findIndex(function (it) { return it.key === key; });
    if (idx === -1) return;

    var parsed = parseInt(nextQty, 10);
    if (!isFinite(parsed) || parsed <= 0) {
      stateItems.splice(idx, 1);
      persistAndEmit(false);
      return;
    }

    var otherUnits = stateItems.reduce(function (sum, it, i) {
      return i === idx ? sum : sum + (Number(it.qty) || 0);
    }, 0);
    var maxAllowed = Math.min(MAX_ITEM_QTY, Math.max(1, MAX_TOTAL_UNITS - otherUnits));
    stateItems[idx].qty = Math.min(maxAllowed, parsed);
    persistAndEmit(false);
  }

  function changeItemQty(key, delta) {
    var item = stateItems.find(function (it) { return it.key === key; });
    if (!item) return;
    setItemQty(key, (Number(item.qty) || 1) + Number(delta || 0));
  }

  function removeItem(key) {
    var before = stateItems.length;
    stateItems = stateItems.filter(function (it) { return it.key !== key; });
    if (stateItems.length !== before) {
      persistAndEmit(false);
    }
  }

  function clear() {
    cartRevision++;
    stateItems = [];
    try {
      if (typeof window !== "undefined" && window.localStorage) {
        window.localStorage.removeItem(STORAGE_KEY);
        window.localStorage.removeItem("bf_pending_cart_order");
      }
    } catch (e) {}
    memoryFallback = [];
    emitChange(false);
  }

  function renderBadges(bump) {
    if (typeof document === "undefined") return;
    var total = getTotalUnits(false);
    var badge = document.getElementById("cartCountBadge");
    var mobileCount = document.getElementById("mobileMenuCartCount");
    var drawerCount = document.getElementById("cartDrawerCount");
    var toggleBtn = document.getElementById("cartToggleBtn");

    if (badge) {
      badge.textContent = total > 99 ? "99+" : String(total);
      badge.hidden = total <= 0;
      if (bump && total > 0) {
        badge.classList.remove("is-bump");
        if (badge.offsetWidth) { /* reflow */ }
        badge.classList.add("is-bump");
        clearTimeout(badgeBumpTimer);
        badgeBumpTimer = setTimeout(function () {
          badge.classList.remove("is-bump");
        }, 420);
      }
    }

    if (toggleBtn) {
      toggleBtn.setAttribute(
        "aria-label",
        total > 0 ? "Открыть корзину (" + total + " шт.)" : "Открыть корзину"
      );
    }

    if (mobileCount) {
      mobileCount.textContent = String(total);
      mobileCount.hidden = total <= 0;
    }

    if (drawerCount) {
      drawerCount.textContent = String(total);
    }
  }

  function renderDrawer() {
    if (typeof document === "undefined") return;
    var emptyEl = document.getElementById("cartEmptyState");
    var listEl = document.getElementById("cartItemsList");
    var footerEl = document.getElementById("cartDrawerFooter");
    var clearBtn = document.getElementById("cartClearBtn");
    var subtotalLabel = document.getElementById("cartSubtotalLabel");
    var subtotalDisplay = document.getElementById("cartSubtotalDisplay");
    var errorEl = document.getElementById("cartDrawerError");
    var checkoutBtn = document.getElementById("cartCheckoutBtn");

    if (!listEl || !emptyEl || !footerEl) return;

    if (stateItems.length === 0) {
      emptyEl.hidden = false;
      listEl.hidden = true;
      listEl.innerHTML = "";
      footerEl.hidden = true;
      if (clearBtn) clearBtn.hidden = true;
      if (errorEl) {
        errorEl.hidden = true;
        errorEl.textContent = "";
      }
      return;
    }

    emptyEl.hidden = true;
    listEl.hidden = false;
    footerEl.hidden = false;
    if (clearBtn) clearBtn.hidden = false;

    listEl.innerHTML = stateItems.map(function (item) {
      var lineTotal = Math.round((Number(item.priceNumeric) || 0) * (Number(item.qty) || 1) * 100) / 100;
      var unavailable = item.available === false;
      var metaParts = [];
      if (item.gender) metaParts.push(escapeHtml(item.gender));
      if (item.size) metaParts.push("Размер " + escapeHtml(item.size));

      var imgHtml = item.img
        ? '<img src="' + escapeHtml(item.img) + '" alt="' + escapeHtml(item.name) + '" class="cart-item-img" loading="lazy">'
        : '<div class="cart-item-img cart-item-img--placeholder" aria-hidden="true">BF</div>';

      return (
        '<div class="cart-item' + (unavailable ? ' is-unavailable' : '') + '" data-cart-key="' + escapeHtml(item.key) + '">' +
          '<a href="' + escapeHtml(item.url || '/catalog') + '" class="cart-item-thumb">' + imgHtml + '</a>' +
          '<div class="cart-item-main">' +
            '<div class="cart-item-top">' +
              '<a href="' + escapeHtml(item.url || '/catalog') + '" class="cart-item-title">' + escapeHtml(item.name) + '</a>' +
              '<button type="button" class="cart-item-remove" data-cart-action="remove" data-cart-key="' + escapeHtml(item.key) + '" aria-label="Удалить из корзины" title="Удалить">&times;</button>' +
            '</div>' +
            (metaParts.length ? '<div class="cart-item-meta">' + metaParts.join(" · ") + '</div>' : '') +
            (unavailable
              ? '<div class="cart-item-alert">' + escapeHtml(item.reason || "Позиция недоступна") + '</div>'
              : '<div class="cart-item-bottom">' +
                  '<div class="cart-qty-control" role="group" aria-label="Количество">' +
                    '<button type="button" class="cart-qty-btn" data-cart-action="dec" data-cart-key="' + escapeHtml(item.key) + '" aria-label="Уменьшить количество">&minus;</button>' +
                    '<span class="cart-qty-val">' + Number(item.qty) + '</span>' +
                    '<button type="button" class="cart-qty-btn" data-cart-action="inc" data-cart-key="' + escapeHtml(item.key) + '"' + (item.qty >= MAX_ITEM_QTY ? ' disabled' : '') + ' aria-label="Увеличить количество">+</button>' +
                  '</div>' +
                  '<div class="cart-item-prices">' +
                    '<strong class="cart-item-sum">' + formatRub(lineTotal) + '</strong>' +
                    (item.qty > 1 ? '<span class="cart-item-unit">' + formatRub(item.priceNumeric) + ' / шт.</span>' : '') +
                  '</div>' +
                '</div>'
            ) +
          '</div>' +
        '</div>'
      );
    }).join("");

    var availUnits = getTotalUnits(true);
    var subtotal = getSubtotal();
    if (subtotalLabel) {
      subtotalLabel.textContent = "Товары (" + availUnits + " шт.)";
    }
    if (subtotalDisplay) {
      subtotalDisplay.textContent = formatRub(subtotal);
    }

    var anyUnavailable = hasUnavailableItems();
    if (errorEl) {
      if (anyUnavailable) {
        errorEl.hidden = false;
        errorEl.textContent = "В корзине есть недоступные позиции. Удалите их, чтобы перейти к оформлению заказа.";
      } else {
        errorEl.hidden = true;
        errorEl.textContent = "";
      }
    }

    if (checkoutBtn) {
      checkoutBtn.disabled = anyUnavailable || availUnits === 0 || isValidating;
      if (!isValidating) {
        checkoutBtn.textContent = availUnits > 0
          ? "Перейти к оформлению · " + formatRub(subtotal)
          : "Перейти к оформлению";
      }
    }
  }

  function validateWithServer() {
    if (stateItems.length === 0 || typeof fetch !== "function") {
      return Promise.resolve({ ok: false, items: getItems(), hasUnavailable: hasUnavailableItems() });
    }

    var request = ++validationRequest;
    var revision = cartRevision;
    isValidating = true;
    renderDrawer();

    return fetch("/api/cart-validate.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ items: stateItems })
    })
      .then(function (res) {
        if (!res.ok) throw new Error("Проверка корзины недоступна");
        return res.json();
      })
      .then(function (data) {
        if (request !== validationRequest) return { ok: false, items: [], hasUnavailable: false };
        isValidating = false;
        if (revision !== cartRevision || !data || !data.success || !Array.isArray(data.items)) {
          renderDrawer();
          return { ok: false, items: [], hasUnavailable: hasUnavailableItems(), error: "Корзина изменилась или проверка не завершена. Повторите оформление." };
        }
        stateItems = normalizeStoredList(data.items);
        cartRevision++;
        safeWriteStorage(stateItems);
        emitChange(false);
        return {
          ok: !hasUnavailableItems() && getAvailableItems().length > 0,
          items: getAvailableItems(),
          hasUnavailable: hasUnavailableItems()
        };
      })
      .catch(function () {
        if (request !== validationRequest) return { ok: false, items: [], hasUnavailable: false };
        isValidating = false;
        renderDrawer();
        return {
          ok: false,
          items: getAvailableItems(),
          hasUnavailable: hasUnavailableItems(),
          error: "Не удалось проверить наличие товаров. Проверьте соединение и повторите оформление."
        };
      });
  }

  function closeMobileMenuIfOpen() {
    if (typeof document === "undefined") return;
    var menu = document.querySelector(".mobile-menu");
    var overlay = document.querySelector(".menu-overlay, .overlay");
    var burger = document.querySelector(".burger");
    if (menu && menu.classList.contains("open")) {
      menu.classList.remove("open");
      menu.setAttribute("aria-hidden", "true");
    }
    if (overlay && overlay.classList.contains("open")) {
      overlay.classList.remove("open");
      overlay.hidden = true;
    }
    if (burger) {
      burger.setAttribute("aria-expanded", "false");
    }
  }

  function openDrawer() {
    if (typeof document === "undefined") return;
    closeMobileMenuIfOpen();
    hideToast();

    var drawer = document.getElementById("cartDrawer");
    var overlay = document.getElementById("cartDrawerOverlay");
    var toggleBtn = document.getElementById("cartToggleBtn");
    if (!drawer) return;

    renderDrawer();
    if (overlay) {
      overlay.hidden = false;
      requestAnimationFrame(function () {
        overlay.classList.add("is-open");
      });
    }
    drawer.classList.add("is-open");
    drawer.setAttribute("aria-hidden", "false");
    if (toggleBtn) toggleBtn.setAttribute("aria-expanded", "true");
    document.body.classList.add("no-scroll");

    if (window.BFCheckout && typeof window.BFCheckout.ensureScriptsLoaded === "function") {
      window.BFCheckout.ensureScriptsLoaded();
    }

    if (stateItems.length > 0) {
      validateWithServer();
    }
  }

  function closeDrawer() {
    if (typeof document === "undefined") return;
    var drawer = document.getElementById("cartDrawer");
    var overlay = document.getElementById("cartDrawerOverlay");
    var toggleBtn = document.getElementById("cartToggleBtn");
    if (!drawer) return;

    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    if (toggleBtn) toggleBtn.setAttribute("aria-expanded", "false");

    if (overlay) {
      overlay.classList.remove("is-open");
      setTimeout(function () {
        if (!drawer.classList.contains("is-open")) {
          overlay.hidden = true;
        }
      }, 320);
    }

    var orderModal = document.getElementById("orderModal");
    if (!orderModal || !orderModal.classList.contains("is-open")) {
      document.body.classList.remove("no-scroll");
    }
  }

  function hideToast() {
    if (typeof document === "undefined") return;
    var toast = document.getElementById("cartToast");
    if (!toast) return;
    clearTimeout(toastTimer);
    toast.classList.remove("is-visible");
    setTimeout(function () {
      if (!toast.classList.contains("is-visible")) {
        toast.hidden = true;
      }
    }, 260);
  }

  function showToast(message) {
    if (typeof document === "undefined") return;
    var toast = document.getElementById("cartToast");
    var textEl = document.getElementById("cartToastText");
    if (!toast) return;

    if (textEl) {
      textEl.textContent = message || "Товар добавлен в корзину";
    }
    clearTimeout(toastTimer);
    toast.hidden = false;
    requestAnimationFrame(function () {
      toast.classList.add("is-visible");
    });

    toastTimer = setTimeout(function () {
      hideToast();
    }, 3400);
  }

  function initCartDom() {
    renderBadges(false);
    renderDrawer();

    var toggleBtn = document.getElementById("cartToggleBtn");
    var closeBtn = document.getElementById("cartDrawerClose");
    var overlay = document.getElementById("cartDrawerOverlay");
    var clearBtn = document.getElementById("cartClearBtn");
    var listEl = document.getElementById("cartItemsList");
    var checkoutBtn = document.getElementById("cartCheckoutBtn");
    var toastOpenBtn = document.getElementById("cartToastOpenBtn");

    if (toggleBtn) {
      toggleBtn.addEventListener("click", function () {
        var drawer = document.getElementById("cartDrawer");
        if (drawer && drawer.classList.contains("is-open")) {
          closeDrawer();
        } else {
          openDrawer();
        }
      });
    }

    document.querySelectorAll("[data-open-cart]").forEach(function (el) {
      el.addEventListener("click", function (e) {
        e.preventDefault();
        openDrawer();
      });
    });

    if (closeBtn) closeBtn.addEventListener("click", closeDrawer);
    if (overlay) overlay.addEventListener("click", closeDrawer);

    if (clearBtn) {
      clearBtn.addEventListener("click", function () {
        clear();
      });
    }

    if (toastOpenBtn) {
      toastOpenBtn.addEventListener("click", function () {
        hideToast();
        openDrawer();
      });
    }

    if (listEl) {
      listEl.addEventListener("click", function (e) {
        var btn = e.target.closest("[data-cart-action]");
        if (!btn) return;
        var key = btn.getAttribute("data-cart-key");
        var action = btn.getAttribute("data-cart-action");
        if (!key) return;

        if (action === "inc") changeItemQty(key, 1);
        else if (action === "dec") changeItemQty(key, -1);
        else if (action === "remove") removeItem(key);
      });
    }

    if (checkoutBtn) {
      checkoutBtn.addEventListener("click", function () {
        if (stateItems.length === 0 || isValidating) return;
        checkoutBtn.disabled = true;
        checkoutBtn.textContent = "Проверяем наличие…";

        validateWithServer().then(function (result) {
          if (!result.ok || result.items.length === 0) {
            renderDrawer();
            var errorEl = document.getElementById("cartDrawerError");
            if (errorEl && result.error) {
              errorEl.hidden = false;
              errorEl.textContent = result.error;
            }
            return;
          }
          closeDrawer();
          if (window.BFCheckout && typeof window.BFCheckout.openCartCheckout === "function") {
            window.BFCheckout.openCartCheckout(result.items);
          }
        });
      });
    }

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      var orderModal = document.getElementById("orderModal");
      if (orderModal && orderModal.classList.contains("is-open")) return;
      var drawer = document.getElementById("cartDrawer");
      if (drawer && drawer.classList.contains("is-open")) {
        closeDrawer();
      }
    });

    window.addEventListener("storage", function (e) {
      if (e.key === STORAGE_KEY) {
        cartRevision++;
        stateItems = normalizeStoredList(safeReadStorage());
        renderBadges(false);
        renderDrawer();
      }
    });
  }

  if (typeof window !== "undefined") {
    window.BFCart = {
      getItems: getItems,
      getAvailableItems: getAvailableItems,
      getTotalUnits: getTotalUnits,
      getSubtotal: getSubtotal,
      formatRub: formatRub,
      addItem: addItem,
      setItemQty: setItemQty,
      changeItemQty: changeItemQty,
      removeItem: removeItem,
      clear: clear,
      openDrawer: openDrawer,
      closeDrawer: closeDrawer,
      validateWithServer: validateWithServer,
      showToast: showToast,
      hideToast: hideToast
    };
  }

  if (typeof document !== "undefined") {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", initCartDom);
    } else {
      initCartDom();
    }
  }
})();
