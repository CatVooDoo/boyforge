"use strict";

(function () {
  "use strict";

  var SIZES_BY_GENDER = {
    "Женский": ["XS", "S", "M", "L", "XL"],
    "Мужской": ["S", "M", "L", "XL", "2XL", "3XL"]
  };

  document.addEventListener("DOMContentLoaded", function () {
    var main = document.querySelector("main.container");
    if (!main) return;

    var productId = main.dataset.productId;
    var productName = main.dataset.productName;
    var productPrice = main.dataset.productPrice;
    var tgBase = main.dataset.productTgBase;

    if (!productId) {
      console.error("Product ID not found in data-* attributes");
      return;
    }

    var mainImg = document.getElementById("galleryMain");
    var thumbs = document.getElementById("thumbs");
    var dotsWrap = document.getElementById("galleryDots");
    
    var thumbImages = thumbs ? Array.from(thumbs.querySelectorAll("img")) : [];
    var imgs = thumbImages.map(function(img) { return img.src; });
    
    if (imgs.length === 0 && mainImg) {
      imgs = [mainImg.src];
    }

    var current = 0;

    function showImage(i) {
      if (i < 0) i = imgs.length - 1;
      if (i >= imgs.length) i = 0;
      current = i;

      if (mainImg) {
        mainImg.src = imgs[i];
        mainImg.alt = productName + " — фото " + (i + 1);
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

    if (mainImg) {
      mainImg.onerror = function () {
        this.style.display = "none";
        if (this.parentElement) {
          this.parentElement.classList.add("ph--empty");
          this.parentElement.setAttribute("data-label", productName);
        }
      };
    }

    if (thumbs && thumbImages.length > 0) {
      thumbImages.forEach(function(t) {
        t.addEventListener("click", function () {
          showImage(parseInt(t.getAttribute("data-i"), 10) || 0);
        });
      });
    }

    if (dotsWrap) {
      var dots = dotsWrap.querySelectorAll("button");
      dots.forEach(function(d) {
        d.addEventListener("click", function () {
          showImage(parseInt(d.getAttribute("data-i"), 10) || 0);
        });
      });
    }

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
        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
          if (dx < 0) showImage(current + 1);
          else showImage(current - 1);
        }
      }, { passive: true });
    }

    showImage(0);

    (function () {
      var modal = document.getElementById("pvModal");
      if (!modal || !mainImg) return;

      var pvImg = document.getElementById("pvImg");
      var pvClose = document.getElementById("pvClose");
      var pvPrev = document.getElementById("pvPrev");
      var pvNext = document.getElementById("pvNext");

      if (imgs.length <= 1) {
        if (pvPrev) pvPrev.style.display = "none";
        if (pvNext) pvNext.style.display = "none";
      }

      function openModal() {
        pvImg.src = imgs[current];
        pvImg.alt = productName + " — фото " + (current + 1);
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
        showImage(current + dir);
        pvImg.src = imgs[current];
        pvImg.alt = productName + " — фото " + (current + 1);
      }

      var zone = mainImg.parentElement || mainImg;
      zone.addEventListener("click", function () {
        if (mainImg.style.display === "none") return;
        openModal();
      });

      if (pvClose) pvClose.addEventListener("click", closeModal);
      if (pvPrev) pvPrev.addEventListener("click", function (e) { e.stopPropagation(); step(-1); });
      if (pvNext) pvNext.addEventListener("click", function (e) { e.stopPropagation(); step(1); });

      modal.addEventListener("click", function (e) {
        if (e.target === modal) closeModal();
      });

      document.addEventListener("keydown", function (e) {
        if (!modal.classList.contains("is-open")) return;
        if (e.key === "Escape") closeModal();
        else if (e.key === "ArrowLeft" && imgs.length > 1) step(-1);
        else if (e.key === "ArrowRight" && imgs.length > 1) step(1);
      });

      if (imgs.length > 1) {
        var sx = 0;
        var multiTouch = false;

        modal.addEventListener("touchstart", function (e) {
          if (e.touches.length > 1) {
            multiTouch = true;
            return;
          }
          multiTouch = false;
          sx = e.changedTouches[0].clientX;
        }, { passive: true });

        modal.addEventListener("touchmove", function (e) {
          if (e.touches.length > 1) multiTouch = true;
        }, { passive: true });

        modal.addEventListener("touchend", function (e) {
          if (multiTouch || e.touches.length > 0) return;
          var dx = e.changedTouches[0].clientX - sx;
          if (Math.abs(dx) > 40) step(dx < 0 ? 1 : -1);
        }, { passive: true });
      }
    })();

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

    function renderSizes(gender) {
      if (!sizesWrap) return;

      var list = SIZES_BY_GENDER[gender];
      if (!list) {
        sizesWrap.innerHTML = "";
        sizesWrap.style.display = "none";
        return;
      }

      var attrName = gender === "Мужской" ? "data-unavailable-male" : "data-unavailable-female";
      var unavailable = (sizesWrap.getAttribute(attrName) || "")
        .split(",")
        .map(function (s) { return s.trim(); })
        .filter(Boolean);

      sizesWrap.style.display = "";
      sizesWrap.innerHTML = list.map(function (s) {
        if (unavailable.indexOf(s) !== -1) {
          return '<button type="button" class="size-out" disabled aria-disabled="true" title="Нет в наличии">' + s + "</button>";
        }
        return '<button type="button">' + s + "</button>";
      }).join("");

      sizesWrap.querySelectorAll("button:not([disabled])").forEach(function (btn) {
        btn.addEventListener("click", function () {
          sizesWrap.querySelectorAll("button").forEach(function (x) { x.classList.remove("active"); });
          btn.classList.add("active");
          sizesWrap.classList.remove("size-error");
          refreshHref();
        });
      });

      refreshHref();
    }

    function buildOrderHref() {
      var size = getSize();
      var gender = getGender();
      var extra = "";
      if (gender) extra += "\nПол: " + gender;
      if (size) extra += "\nРазмер: " + size;

      var base = tgBase || "https://telegram.me/theboyforge";
      if (base.indexOf("?text=") !== -1) {
        return base + (extra ? "%0A" + encodeURIComponent(extra.replace(/^\n/, "")).replace(/%0A/g, "%0A") : "");
      }
      return base + "?text=" +
        encodeURIComponent("Здравствуйте! Хочу заказать: " + productName + extra);
    }

    function flagGenderError() {
      if (!gendersWrap) return;
      gendersWrap.classList.add("gender-error");
      setTimeout(function () {
        gendersWrap.classList.remove("gender-error");
      }, 2500);
    }

    function flagSizeError() {
      if (!sizesWrap) return;
      sizesWrap.classList.add("size-error");
      sizesWrap.scrollIntoView({ behavior: "smooth", block: "center" });
      setTimeout(function () {
        sizesWrap.classList.remove("size-error");
      }, 2500);
    }

    function refreshHref() {
      if (orderBtn) orderBtn.href = buildOrderHref();
    }

    renderSizes(getGender());
    refreshHref();

    if (orderBtn) {
      orderBtn.addEventListener("click", function (e) {
        if (!getGender()) {
          e.preventDefault();
          flagGenderError();
          if (gendersWrap) gendersWrap.scrollIntoView({ behavior: "smooth", block: "center" });
          return;
        }
        if (!getSize()) {
          e.preventDefault();
          flagSizeError();
          return;
        }
        refreshHref();

      });
    }

    if (gendersWrap) {
      gendersWrap.querySelectorAll("button").forEach(function (btn) {
        btn.addEventListener("click", function () {
          gendersWrap.querySelectorAll("button").forEach(function (x) { x.classList.remove("active"); });
          btn.classList.add("active");
          gendersWrap.classList.remove("gender-error");
          renderSizes(getGender());
        });
      });
    }

  });
})();
