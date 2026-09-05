"use strict";

/* product.js — заполняет страницу товара по ?id=N из products.js
   Порядок подключения: products.js -> product.js -> main.js
   Галерея: десктоп — миниатюры (#thumbs), мобайл — точки (#galleryDots) + свайп */
(function () {
  "use strict";

  // диапазоны размеров по полу
  var SIZES_BY_GENDER = {
    "Женский": ["XS", "S", "M", "L", "XL"],
    "Мужской": ["S", "M", "L", "XL", "2XL", "3XL"]
  };

  document.addEventListener("DOMContentLoaded", function () {
    if (!window.PRODUCTS) return;

    var params = new URLSearchParams(window.location.search);
    var id = params.get("id");
    var p = window.getProductById ? window.getProductById(id) : null;

    var main = document.querySelector("main.container");

    // ---- товар не найден ----
    if (!p) {
      document.title = "Товар не найден · BOYFORGE";
      if (main) {
        main.innerHTML =
          '<div class="empty-state" style="padding:120px 20px">' +
          '<div class="empty-title">Товар не найден</div>' +
          '<p class="empty-sub">Возможно, ссылка устарела. Загляните в каталог — там есть что выбрать.</p>' +
          '<a class="btn-outline empty-back" href="catalog.php">В каталог</a>' +
          "</div>";
      }
      return;
    }

    // ---- заголовки / текст ----
    document.title = p.name + " · BOYFORGE";
    setText("metaTitle", p.name + " · BOYFORGE");
    setText("crumbName", p.name);
    setText("prodName", p.name);
    setText("prodArt", p.sub || "Футболка · авторский принт");
    setText("prodPrice", p.price);
    setText("prodDesc", p.desc || "Плотный хлопок, DTF-печать — мягкий стойкий принт, который не трескается со временем. Уход: стирка при 30°, без отбеливателя.");

    /* ==========================================================
       ГАЛЕРЕЯ: единый источник imgs -> главное фото + миниатюры + точки
       ========================================================== */
    var imgs = Array.isArray(p.imgs) && p.imgs.length ? p.imgs : [p.img];

    var mainImg = document.getElementById("galleryMain");
    var thumbs = document.getElementById("thumbs");
    var dotsWrap = document.getElementById("galleryDots");
    var current = 0;

    // показать фото по индексу и синхронизировать миниатюры/точки
    function showImage(i) {
      if (i < 0) i = imgs.length - 1;
      if (i >= imgs.length) i = 0;
      current = i;

      if (mainImg) {
        mainImg.style.display = "";
        if (mainImg.parentElement) mainImg.parentElement.classList.remove("ph--empty");
        mainImg.src = imgs[i];
        mainImg.alt = p.name + " — фото " + (i + 1);
      }

      if (thumbs) {
        var ts = thumbs.querySelectorAll("img");
        ts.forEach(function (t, idx) {
          t.classList.toggle("active", idx === i);
        });
      }

      if (dotsWrap) {
        var ds = dotsWrap.querySelectorAll("button");
        ds.forEach(function (d, idx) {
          d.classList.toggle("active", idx === i);
        });
      }
    }

    // заглушка, если главное фото не загрузилось
    if (mainImg) {
      mainImg.onerror = function () {
        this.style.display = "none";
        if (this.parentElement) {
          this.parentElement.classList.add("ph--empty");
          this.parentElement.setAttribute("data-label", p.name);
        }
      };
    }

    // миниатюры (десктоп)
    if (thumbs) {
      if (imgs.length > 1) {
        thumbs.style.display = "";
        thumbs.innerHTML = imgs.map(function (src, i) {
          return '<img src="' + esc(src) + '" alt="' + esc(p.name) + " — фото " + (i + 1) +
                 '"' + (i === 0 ? ' class="active"' : "") + ' data-i="' + i + '">';
        }).join("");

        thumbs.querySelectorAll("img").forEach(function (t) {
          t.addEventListener("click", function () {
            showImage(parseInt(t.getAttribute("data-i"), 10) || 0);
          });
        });
      } else {
        thumbs.style.display = "none"; // одно фото — миниатюры не нужны
      }
    }

    // точки (мобайл)
    if (dotsWrap) {
      if (imgs.length > 1) {
        dotsWrap.style.display = "";
        dotsWrap.innerHTML = imgs.map(function (src, i) {
          return '<button type="button" aria-label="Фото ' + (i + 1) + '"' +
                 (i === 0 ? ' class="active"' : "") + ' data-i="' + i + '"></button>';
        }).join("");

        dotsWrap.querySelectorAll("button").forEach(function (d) {
          d.addEventListener("click", function () {
            showImage(parseInt(d.getAttribute("data-i"), 10) || 0);
          });
        });
      } else {
        dotsWrap.style.display = "none";
      }
    }

    // свайп по главному фото (мобайл)
    if (mainImg && imgs.length > 1) {
      var startX = 0;
      var startY = 0;
      var swiping = false;

      var swipeZone = mainImg.parentElement || mainImg;

      swipeZone.addEventListener("touchstart", function (e) {
        var t = e.changedTouches[0];
        startX = t.clientX;
        startY = t.clientY;
        swiping = true;
      }, { passive: true });

      swipeZone.addEventListener("touchend", function (e) {
        if (!swiping) return;
        swiping = false;
        var t = e.changedTouches[0];
        var dx = t.clientX - startX;
        var dy = t.clientY - startY;
        // горизонтальный жест с достаточной длиной
        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
          if (dx < 0) showImage(current + 1);
          else showImage(current - 1);
        }
      }, { passive: true });
    }

    // стартовое фото
    showImage(0);

        /* ==========================================================
       ЛАЙТБОКС — увеличение фото по клику
       ========================================================== */
    (function () {
      var modal = document.getElementById("pvModal");
      if (!modal || !mainImg) return;

      var pvImg = document.getElementById("pvImg");
      var pvClose = document.getElementById("pvClose");
      var pvPrev = document.getElementById("pvPrev");
      var pvNext = document.getElementById("pvNext");

      // если фото одно — стрелки не нужны
      if (imgs.length <= 1) {
        if (pvPrev) pvPrev.style.display = "none";
        if (pvNext) pvNext.style.display = "none";
      }

      function openModal() {
        pvImg.src = imgs[current];
        pvImg.alt = p.name + " — фото " + (current + 1);
        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("no-scroll");
      }
      function closeModal() {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("no-scroll");
      }
      function step(dir) {
        showImage(current + dir);   // листаем и главную, и модалку
        pvImg.src = imgs[current];
        pvImg.alt = p.name + " — фото " + (current + 1);
      }

      // открытие по клику на главное фото
      var zone = mainImg.parentElement || mainImg;
      zone.addEventListener("click", function () {
        // не открываем, если фото не загрузилось (заглушка)
        if (mainImg.style.display === "none") return;
        openModal();
      });

      if (pvClose) pvClose.addEventListener("click", closeModal);
      if (pvPrev) pvPrev.addEventListener("click", function (e) { e.stopPropagation(); step(-1); });
      if (pvNext) pvNext.addEventListener("click", function (e) { e.stopPropagation(); step(1); });

      // клик по фону — закрыть
      modal.addEventListener("click", function (e) {
        if (e.target === modal) closeModal();
      });

      // клавиатура
      document.addEventListener("keydown", function (e) {
        if (!modal.classList.contains("is-open")) return;
        if (e.key === "Escape") closeModal();
        else if (e.key === "ArrowLeft" && imgs.length > 1) step(-1);
        else if (e.key === "ArrowRight" && imgs.length > 1) step(1);
      });

      // свайп в модалке (мобайл) — только одним пальцем, зум не мешает
      if (imgs.length > 1) {
        var sx = 0;
        var multiTouch = false;   // был ли жест двумя пальцами

        modal.addEventListener("touchstart", function (e) {
          if (e.touches.length > 1) {
            multiTouch = true;    // два пальца = зум, не листаем
            return;
          }
          multiTouch = false;
          sx = e.changedTouches[0].clientX;
        }, { passive: true });

        modal.addEventListener("touchmove", function (e) {
          // если в любой момент появился второй палец — это зум
          if (e.touches.length > 1) multiTouch = true;
        }, { passive: true });

        modal.addEventListener("touchend", function (e) {
          // не листаем, если был зум или ещё остались пальцы на экране
          if (multiTouch || e.touches.length > 0) return;
          var dx = e.changedTouches[0].clientX - sx;
          if (Math.abs(dx) > 40) step(dx < 0 ? 1 : -1);
        }, { passive: true });
      }

    })();


    /* ==========================================================
       ХАРАКТЕРИСТИКИ
       ========================================================== */
    var specsEl = document.getElementById("prodSpecs");
    if (specsEl) {
      var specs = p.specs || [
        ["Материал", "Футер 2-нитка, 95% хлопок / 5% эластан"],
        ["Плотность", "240 г/м²"],
        ["Печать", "DTF"],
        ["Размеры", "S–3XL"],
        ["Пошив", "Россия"]
      ];
      specsEl.innerHTML = specs.map(function (row) {
        return "<li><span>" + esc(row[0]) + "</span>" + esc(row[1]) + "</li>";
      }).join("");
    }

    /* ==========================================================
       ВЫБОР ПОЛА + РАЗМЕРЫ (индивидуальные по полу) + КНОПКА TELEGRAM
       ========================================================== */
    var orderBtn = document.getElementById("orderBtn");
    var gendersWrap = document.getElementById("genders");
    var sizesWrap = document.querySelector(".sizes");

    function getSize() {
      var b = sizesWrap ? sizesWrap.querySelector("button.active") : null;
      return b ? b.textContent.trim() : "";
    }
    function getGender() {
      var b = gendersWrap ? gendersWrap.querySelector("button.active") : null;
      return b ? (b.getAttribute("data-gender") || b.textContent.trim()) : "";
    }

    // перерисовать размеры под выбранный пол (сбрасывает выбранный размер)
    function renderSizes(gender) {
      if (!sizesWrap) return;

      var list = SIZES_BY_GENDER[gender];
      if (!list) {
        // пол не выбран — прячем размеры, пока не выберут
        sizesWrap.innerHTML = "";
        sizesWrap.style.display = "none";
        return;
      }

      sizesWrap.style.display = "";
      sizesWrap.innerHTML = list.map(function (s) {
        return '<button type="button">' + esc(s) + "</button>";
      }).join("");

      // навешиваем обработчики на новые кнопки
      sizesWrap.querySelectorAll("button").forEach(function (btn) {
        btn.addEventListener("click", function () {
          sizesWrap.querySelectorAll("button").forEach(function (x) { x.classList.remove("active"); });
          btn.classList.add("active");
          refreshHref();
        });
      });

      refreshHref(); // размер сброшен — обновляем ссылку
    }

    function buildOrderHref() {
      var size = getSize();
      var gender = getGender();
      var extra = "";
      if (gender) extra += "\nПол: " + gender;
      if (size) extra += "\nРазмер: " + size;

      var base = p.tg || "https://telegram.me/theboyforge";
      if (base.indexOf("?text=") !== -1) {
        return base + (extra ? "%0A" + encodeURIComponent(extra.replace(/^\n/, "")).replace(/%0A/g, "%0A") : "");
      }
      return base + "?text=" +
        encodeURIComponent("Здравствуйте! Хочу заказать: " + p.name + extra);
    }

    // подсветка блока пола, если не выбран
    function flagGenderError() {
      if (!gendersWrap) return;
      gendersWrap.classList.add("gender-error");
      setTimeout(function () {
        gendersWrap.classList.remove("gender-error");
      }, 2500);
    }

    function refreshHref() {
      if (orderBtn) orderBtn.href = buildOrderHref();
    }

    // старт: пол не выбран — размеры скрыты
    renderSizes(getGender());
    refreshHref();

    if (orderBtn) {
      orderBtn.addEventListener("click", function (e) {
        if (!getGender()) {
          e.preventDefault();          // не пускаем в Telegram
          flagGenderError();           // подсветка красным
          if (gendersWrap) gendersWrap.scrollIntoView({ behavior: "smooth", block: "center" });
          return;
        }
        refreshHref();                 // финальный href с полом и размером

        // Отправка данных заказа в фоновом режиме на backend (для Google Таблицы)
        try {
          var payload = {
            productId: (p && p.id) ? p.id : "",
            productName: (p && p.name) ? p.name : "",
            price: (p && p.price) ? p.price : "",
            gender: getGender(),
            size: getSize(),
            source: "Заказ через Telegram"
          };

          if (navigator.sendBeacon) {
            navigator.sendBeacon("api/order.php", new Blob([JSON.stringify(payload)], { type: "application/json" }));
          } else {
            fetch("api/order.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(payload)
            }).catch(function () {});
          }
        } catch (err) {}
      });
    }

    // выбор пола -> перерисовываем размеры
    if (gendersWrap) {
      gendersWrap.querySelectorAll("button").forEach(function (btn) {
        btn.addEventListener("click", function () {
          gendersWrap.querySelectorAll("button").forEach(function (x) { x.classList.remove("active"); });
          btn.classList.add("active");
          gendersWrap.classList.remove("gender-error"); // выбрали — убираем ошибку

          renderSizes(getGender()); // размеры под выбранный пол
        });
      });
    }

    /* ==========================================================
       ПОХОЖИЕ ТОВАРЫ
       ========================================================== */
    var relatedGrid = document.getElementById("relatedGrid");
    var related = window.getRelated ? window.getRelated(p.id, 4) : [];
    if (relatedGrid && related.length) {
      relatedGrid.innerHTML = related.map(function (r) {
        var hit = (r.tags || []).indexOf("Хит") !== -1 ? '<span class="badge-hit">хит</span>' : "";
        return (
          '<a href="product.php?id=' + r.id + '" class="card">' +
          '<div class="card-img">' +
          '<img src="' + esc(r.img) + '" alt="' + esc(r.name) + '" loading="lazy" ' +
          "onerror=\"this.style.display='none';this.parentElement.classList.add('ph--empty');this.parentElement.setAttribute('data-label','" + esc(r.name).replace(/'/g, "") + "');\">" +
          hit + "</div>" +
          '<div class="card-info">' +
          '<div class="card-title">' + esc(r.name) + "</div>" +
          '<div class="card-price">' + esc(r.price) + "</div>" +
          "</div></a>"
        );
      }).join("");
    } else if (relatedGrid) {
      relatedGrid.style.display = "none";
      var relTitle = document.querySelector(".related-title");
      if (relTitle) relTitle.style.display = "none";
    }

  }); // конец DOMContentLoaded

  // ---- хелперы ----
  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }
  function esc(s) {
    return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;")
      .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }
})();
