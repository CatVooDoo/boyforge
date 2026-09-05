"use strict";

/* catalog.js — рендер карточек под BOYFORGE-разметку (.card, .catalog-grid, .chip)
   Порядок загрузки: products.js -> catalog.js */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var grid = document.getElementById("catalogGrid");
    if (!grid || !window.PRODUCTS) return;

    var filterWrap = document.querySelector(".chips");
    var sortSelect = document.getElementById("sortSelect");
    var countEl = document.getElementById("catalogCount");
    var emptyState = document.getElementById("emptyState");
    var emptyBack = document.getElementById("emptyBack");

    var state = { category: "all", sort: "pop" };

    function pluralize(n) {
      var m10 = n % 10, m100 = n % 100;
      if (m10 === 1 && m100 !== 11) return "товар";
      if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return "товара";
      return "товаров";
    }

    function esc(s) {
      return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;")
        .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    // цена как число для сортировки ("4 200 ₽" -> 4200)
    function priceNum(p) {
      return parseInt(String(p.price).replace(/\D/g, ""), 10) || 0;
    }

    // формирует бейджи из тегов (поддержка: Хит, Новая коллекция, Новинка)
    function badgesHTML(p) {
      var tags = p.tags || [];
      var out = "";
      if (tags.indexOf("Хит") !== -1) {
        out += '<span class="badge-hit">хит</span>';
      }
      if (tags.indexOf("Новая коллекция") !== -1) {
        out += '<span class="badge-new-collection">новая коллекция</span>';
      } else if (tags.indexOf("Новинка") !== -1 || tags.indexOf("Новая") !== -1) {
        out += '<span class="badge-new">новинка</span>';
      }
      return out;
    }

    function cardHTML(p) {
      var badges = badgesHTML(p);

      var inner =
        '<div class="card-img">' +
        '<img src="' + esc(p.img) + '" alt="' + esc(p.name) + '" loading="lazy" ' +
        "onerror=\"this.style.display='none';this.parentElement.classList.add('ph--empty');this.parentElement.setAttribute('data-label','" + esc(p.name).replace(/'/g, "") + "');\">" +
        badges +
        (p.soon ? '<div class="card-soon-overlay"><span>Скоро появится</span></div>' : "") +
        "</div>" +
        '<div class="card-info">' +
        '<div class="card-title">' + esc(p.name) + "</div>" +
        '<div class="card-price">' + esc(p.price) + "</div>" +
        "</div>";

      // "скоро появится" — карточка не ссылка, открыть нельзя
      if (p.soon) {
        return '<div class="card card--soon" aria-disabled="true">' + inner + "</div>";
      }

      return '<a href="product.php?id=' + p.id + '" class="card">' + inner + "</a>";
    }

    function getItems() {
      var items = window.PRODUCTS.filter(function (p) {
        return state.category === "all" || p.catId === state.category;
      });

      if (state.sort === "cheap") items.sort(function (a, b) { return priceNum(a) - priceNum(b); });
      else if (state.sort === "exp") items.sort(function (a, b) { return priceNum(b) - priceNum(a); });
      else if (state.sort === "new") items.sort(function (a, b) { return b.id - a.id; });
      else items.sort(function (a, b) { return a.id - b.id; }); // pop / по умолчанию

      return items;
    }

    function render() {
      var items = getItems();

      if (countEl) countEl.textContent = items.length + " " + pluralize(items.length);

      if (items.length === 0) {
        grid.innerHTML = "";
        if (emptyState) emptyState.hidden = false;
        return;
      }
      if (emptyState) emptyState.hidden = true;

      grid.innerHTML = items.map(cardHTML).join("");

      // плавное появление (класс .card-in из твоего CSS)
      var cards = grid.querySelectorAll(".card");
      cards.forEach(function (c, i) {
        setTimeout(function () { c.classList.add("card-in"); }, i * 60);
      });
    }

    function syncFilterUI() {
      if (!filterWrap) return;
      filterWrap.querySelectorAll(".chip").forEach(function (chip) {
        chip.classList.toggle("is-active", chip.getAttribute("data-cat") === state.category);
      });
    }

    if (filterWrap) {
      filterWrap.addEventListener("click", function (e) {
        var chip = e.target.closest(".chip");
        if (!chip) return;
        state.category = chip.getAttribute("data-cat");
        syncFilterUI();
        render();
      });
    }

    if (sortSelect) {
      sortSelect.addEventListener("change", function () {
        state.sort = sortSelect.value;
        render();
      });
    }

    if (emptyBack) {
      emptyBack.addEventListener("click", function () {
        state.category = "all";
        syncFilterUI();
        render();
      });
    }

    syncFilterUI();
    render();
  });
})();
