"use strict";

(function () {
  "use strict";

  let yandexScriptPromise = null;
  let yookassaScriptPromise = null;

  function loadExternalScript(src, isReadyFn) {
    if (isReadyFn()) return Promise.resolve(true);
    if (typeof document === "undefined" || typeof document.createElement !== "function" || !document.head) {
      return Promise.resolve(false);
    }

    return new Promise(function (resolve) {
      const existing = Array.from(document.querySelectorAll("script")).find(function (s) {
        return s.src && s.src.indexOf(src.split("?")[0]) !== -1;
      });
      if (existing) {
        if (isReadyFn()) {
          resolve(true);
          return;
        }
        existing.addEventListener("load", function () { resolve(true); }, { once: true });
        existing.addEventListener("error", function () { resolve(false); }, { once: true });
        return;
      }

      const script = document.createElement("script");
      script.src = src;
      script.async = true;
      script.onload = function () { resolve(true); };
      script.onerror = function () { resolve(false); };
      document.head.appendChild(script);
    });
  }

  function ensureYandexMapsScript() {
    if (typeof ymaps !== "undefined") return Promise.resolve(true);
    if (!yandexScriptPromise) {
      const apiKey = (typeof window !== "undefined" && window.BF_CONFIG && window.BF_CONFIG.yandexMapsApiKey)
        ? String(window.BF_CONFIG.yandexMapsApiKey)
        : "";
      const url = "https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=" + encodeURIComponent(apiKey);
      yandexScriptPromise = loadExternalScript(url, function () { return typeof ymaps !== "undefined"; });
    }
    return yandexScriptPromise;
  }

  function ensureYookassaWidgetScript() {
    if (typeof window !== "undefined" && typeof window.YooMoneyCheckoutWidget !== "undefined") {
      return Promise.resolve(true);
    }
    if (!yookassaScriptPromise) {
      yookassaScriptPromise = loadExternalScript(
        "https://yookassa.ru/checkout-widget/v1/checkout-widget.js",
        function () { return typeof window !== "undefined" && typeof window.YooMoneyCheckoutWidget !== "undefined"; }
      );
    }
    return yookassaScriptPromise;
  }

  function ensureScriptsLoaded() {
    ensureYandexMapsScript();
    ensureYookassaWidgetScript();
  }

  document.addEventListener("DOMContentLoaded", function () {
    const orderBtn = document.getElementById("orderModalBtn") || document.getElementById("ozonOrderBtn");
    const modal = document.getElementById("orderModal") || document.getElementById("ozonModal");
    if (!modal) return;

    const closeBtn = document.getElementById("orderModalClose") || document.getElementById("ozonModalClose");
    const modalTitleEl = document.getElementById("orderModalTitle");
    const singleSummaryEl = document.getElementById("orderModalSingleSummary");
    const cartSummaryEl = document.getElementById("orderModalCartSummary");
    const cartCountEl = document.getElementById("orderModalCartCount");
    const cartItemsEl = document.getElementById("orderModalCartItems");
    const cartSubtotalEl = document.getElementById("orderModalCartSubtotal");
    const backToCartBtn = document.getElementById("checkoutBackToCartBtn");
    const widgetContainer = document.getElementById("yookassaWidgetContainer");
    const orderDetailsSections = document.getElementById("orderDetailsSections") || (modal.querySelector ? modal.querySelector(".order-details-sections") : null);
    const orderAgreeWrap = document.getElementById("orderAgreeWrap");

    const form = document.getElementById("orderForm") || document.getElementById("ozonOrderForm");
    const fioInput = document.getElementById("orderFio");
    const emailInput = document.getElementById("orderEmail");
    const phoneInput = document.getElementById("orderPhone") || document.getElementById("ozonPhone");
    const submitBtn = document.getElementById("orderConfirmBtn") || document.getElementById("ozonConfirmBtn");
    const statusMsg = document.getElementById("orderFormStatus") || document.getElementById("ozonFormStatus");

    const fivepostIdInput = document.getElementById("fivepostPointId");
    const fivepostNameInput = document.getElementById("fivepostPointName");
    const fivepostAddrInput = document.getElementById("fivepostPointAddress");
    const fivepostTypeInput = document.getElementById("fivepostPointType");
    const fivepostDetailsInput = document.getElementById("fivepostPointDetails");

    const fivepostCard = document.getElementById("fivepostSelectedCard");
    const fivepostMapWrapper = document.getElementById("fivepostMapWrapper");
    const fivepostMapContainer = document.getElementById("fivepostCustomMap") || document.getElementById("fivepost-widget-map");
    const fivepostChangeBtn = document.getElementById("fivepostChangeBtn");

    const searchInput = document.getElementById("fivepostCitySearch");
    const clearSearchBtn = document.getElementById("fivepostCityClear");
    const mapLoader = document.getElementById("fivepostMapLoader");

    const badgeEl = document.getElementById("fivepostPointTypeBadge");
    const nameEl = document.getElementById("fivepostPointNameDisplay");
    const addrEl = document.getElementById("fivepostPointAddressDisplay");
    const extraEl = document.getElementById("fivepostPointExtraDisplay");

    let checkoutMode = "single";
    let cartCheckoutItems = [];
    let activeYookassaWidget = null;

    let yandexMap = null;
    let pointsCollection = null;
    let currentPoints = [];
    let currentCity = "Пенза";
    let customPinLayout = null;
    let mapInitialized = false;
    const renderedPointIds = new Set();
    let boundsDebounceTimer = null;
    let isProgrammaticMove = false;
    let deliveryProvider = "fivepost";
    let deliveryQuote = null;
    let deliveryQuoteSignature = "";
    let deliveryMode = null;
    let pointsRequest = 0;
    let selectionRequest = 0;
    let cdekPinLayout = null;
    const providerButtons = document.querySelectorAll("[data-delivery-provider]");
    const quoteEl = document.getElementById("deliveryQuote");
    const pointList = document.getElementById("deliveryPointList");
    const carrierName = () => deliveryProvider === "cdek" ? "СДЭК" : "5Post";
    const pointsEndpoint = () => deliveryProvider === "cdek" ? "/api/cdek-points.php?" : "/api/fivepost-points.php?";

    function escapeHtml(str) {
      if (!str) return "";
      return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    function formatRub(amount) {
      const num = Math.round((Number(amount) || 0) * 100) / 100;
      const hasDec = Math.abs(num - Math.round(num)) > 0.001;
      return num.toLocaleString("ru-RU", {
        minimumFractionDigits: hasDec ? 2 : 0,
        maximumFractionDigits: hasDec ? 2 : 0
      }) + " ₽";
    }

    function getProductState() {
      const gActive = document.querySelector("#genders button.active");
      const sActive = document.querySelector(".sizes button.active");
      const main = document.querySelector("main.container");
      const mainImgEl = document.getElementById("galleryMain");

      return {
        id: main ? main.dataset.productId : "",
        name: main ? main.dataset.productName : "Товар BOYFORGE",
        price: main ? main.dataset.productPrice : "3 200 ₽",
        gender: gActive ? (gActive.getAttribute("data-gender") || gActive.textContent.trim()) : "",
        size: sActive ? sActive.textContent.trim() : "",
        img: mainImgEl ? mainImgEl.src : ""
      };
    }

    function getCheckoutTotals() {
      if (checkoutMode === "cart") {
        const goodsTotal = Math.round(cartCheckoutItems.reduce(function (sum, it) {
          const unit = Number(it.priceNumeric) || parseFloat(String(it.price || "").replace(/[^\d.]/g, "")) || 0;
          return sum + unit * (Number(it.qty) || 1);
        }, 0) * 100) / 100;
        const totalUnits = cartCheckoutItems.reduce(function (sum, it) {
          return sum + (Number(it.qty) || 1);
        }, 0);
        return {
          mode: "cart",
          goodsTotal: goodsTotal,
          totalUnits: Math.max(1, totalUnits),
          items: cartCheckoutItems
        };
      }

      const state = getProductState();
      const goodsTotal = parseFloat(String(state.price || "").replace(/[^\d.]/g, "")) || 3200;
      return {
        mode: "single",
        goodsTotal: goodsTotal,
        totalUnits: 1,
        state: state
      };
    }

    function resetPaymentWidgetView() {
      if (activeYookassaWidget) {
        try { activeYookassaWidget.destroy(); } catch (e) {}
        activeYookassaWidget = null;
      }
      if (widgetContainer) {
        widgetContainer.style.display = "none";
        widgetContainer.innerHTML = "";
      }
      if (orderDetailsSections) orderDetailsSections.style.display = "";
      if (orderAgreeWrap) orderAgreeWrap.style.display = "";
      if (submitBtn) {
        submitBtn.style.display = "";
        submitBtn.disabled = false;
      }
      providerButtons.forEach(function (btn) { btn.disabled = false; });
      if (fivepostIdInput && fivepostIdInput.value) {
        if (fivepostCard) fivepostCard.style.display = "flex";
        if (fivepostMapWrapper) fivepostMapWrapper.style.display = "none";
      } else {
        if (fivepostCard) fivepostCard.style.display = "none";
        if (fivepostMapWrapper) fivepostMapWrapper.style.display = "block";
      }
    }

    function clearDeliverySelection() {
      selectionRequest++;
      deliveryQuote = null;
      deliveryQuoteSignature = "";
      [fivepostIdInput, fivepostNameInput, fivepostAddrInput, fivepostTypeInput, fivepostDetailsInput].forEach(input => { if (input) input.value = ""; });
      if (fivepostCard) fivepostCard.style.display = "none";
      if (fivepostMapWrapper) fivepostMapWrapper.style.display = "block";
      if (quoteEl) quoteEl.hidden = true;
      if (submitBtn) submitBtn.textContent = "Оплатить онлайн картой / СБП";
    }

    providerButtons.forEach(button => button.addEventListener("click", function () {
      const next = button.dataset.deliveryProvider;
      if (next === deliveryProvider) return;
      deliveryProvider = next;
      pointsRequest++;
      clearTimeout(searchDebounceTimer);
      clearTimeout(boundsDebounceTimer);
      clearDeliverySelection();
      deliveryMode = null;
      currentPoints = [];
      renderedPointIds.clear();
      if (pointsCollection) pointsCollection.removeAll();
      if (yandexMap?.balloon) yandexMap.balloon.close();
      providerButtons.forEach(btn => {
        const active = btn.dataset.deliveryProvider === next;
        btn.classList.toggle("is-active", active);
        btn.setAttribute("aria-pressed", String(active));
      });
      if (pointList) { pointList.hidden = true; pointList.innerHTML = ""; }
      if (yandexMap) setTimeout(() => yandexMap.container.fitToViewport(), 50);
      loadPoints("", searchInput?.value.trim() || currentCity, true);
    }));

    function updateDeliveryMode(mode) {
      deliveryMode = mode;
    }

    async function chooseDeliveryPoint(point) {
      if (deliveryProvider !== "cdek") {
        handleSelect5PostPoint(point);
        if (submitBtn) {
          const totals = getCheckoutTotals();
          submitBtn.textContent = "Оплатить " + totals.goodsTotal.toLocaleString("ru-RU") + " ₽ онлайн";
        }
        return;
      }
      clearDeliverySelection();
      const request = selectionRequest;
      showStatus("Рассчитываем стоимость доставки СДЭК…", "info");
      try {
        const totals = getCheckoutTotals();
        const value = totals.goodsTotal || 3200;
        const quoteUrl = "/api/cdek-quote.php?point=" + encodeURIComponent(point.id)
          + "&value=" + encodeURIComponent(value)
          + (totals.totalUnits > 1 ? "&qty=" + encodeURIComponent(totals.totalUnits) : "");
        const res = await fetch(quoteUrl);
        const data = await res.json();
        if (request !== selectionRequest || deliveryProvider !== "cdek") return;
        if (!res.ok || !data.success) throw new Error(data.error || "Не удалось рассчитать доставку");
        deliveryQuote = data.quote;
        deliveryQuoteSignature = value + ":" + totals.totalUnits;
        updateDeliveryMode(data.mode);
        handleSelect5PostPoint(data.point);
        const total = Math.round((value + data.quote.cost) * 100) / 100;
        if (quoteEl) {
          quoteEl.hidden = false;
          const itemsWord = totals.totalUnits > 1 ? "Итого с товарами" : "Итого с товаром";
          quoteEl.textContent = `Доставка: ${data.quote.cost.toLocaleString("ru-RU")} ₽ · ${data.quote.days_min}–${data.quote.days_max} дн. ${itemsWord}: ${total.toLocaleString("ru-RU")} ₽`;
        }
        if (submitBtn) submitBtn.textContent = "Оплатить " + total.toLocaleString("ru-RU") + " ₽ онлайн";
      } catch (err) {
        if (request === selectionRequest) showStatus(escapeHtml(err.message), "error");
      }
    }

    function setProgrammaticMove() {
      isProgrammaticMove = true;
      setTimeout(function () {
        isProgrammaticMove = false;
      }, 700);
    }

    function flagGenderError() {
      const gendersWrap = document.getElementById("genders");
      if (!gendersWrap) return;
      gendersWrap.classList.add("gender-error");
      gendersWrap.scrollIntoView({ behavior: "smooth", block: "center" });
      setTimeout(function () {
        gendersWrap.classList.remove("gender-error");
      }, 2500);
    }

    function flagSizeError() {
      const sizesWrap = document.querySelector(".sizes");
      if (!sizesWrap) return;
      sizesWrap.classList.add("size-error");
      sizesWrap.scrollIntoView({ behavior: "smooth", block: "center" });
      setTimeout(function () {
        sizesWrap.classList.remove("size-error");
      }, 2500);
    }

    function handleSelect5PostPoint(point) {
      if (!point) return;

      const pointId = point.id || "";
      const pointType = point.type || "POSTAMAT";
      const pointName = point.name || (deliveryProvider === "cdek" ? "Пункт выдачи СДЭК" : (pointType === "POSTAMAT" ? "Постамат 5Post" : "Касса «Пятёрочка»"));
      const pointAddr = point.full_address || point.resultAddress || point.address || "";
      const pointExtra = [point.additional, point.work_hours].filter(Boolean).join(" · ");

      if (fivepostIdInput) fivepostIdInput.value = pointId;
      if (fivepostNameInput) fivepostNameInput.value = pointName;
      if (fivepostAddrInput) fivepostAddrInput.value = pointAddr;
      if (fivepostTypeInput) fivepostTypeInput.value = pointType;
      if (fivepostDetailsInput) fivepostDetailsInput.value = pointExtra;

      if (badgeEl) {
        badgeEl.textContent = pointType === "PVZ" ? "Пункт выдачи" : (pointType === "POSTAMAT" ? "Постамат" : "Касса");
      }
      if (nameEl) nameEl.textContent = pointName;
      if (addrEl) addrEl.textContent = pointAddr;
      if (extraEl) extraEl.textContent = pointExtra || ("Выдача заказа " + carrierName());

      if (fivepostCard) fivepostCard.style.display = "flex";
      if (fivepostMapWrapper) fivepostMapWrapper.style.display = "none";

      showStatus("", null);
    }

    function createCustomPinLayout() {
      if (deliveryProvider === "cdek" && typeof ymaps !== "undefined") {
        if (!cdekPinLayout) cdekPinLayout = ymaps.templateLayoutFactory.createClass(
          '<div class="bf-map-pin cdek-pin" title="$[properties.hintContent]">' +
            '<div class="bf-pin-body">' +
              '<img src="/images/cdek.png" alt="СДЭК" class="bf-pin-img" width="36" height="36">' +
            '</div>' +
            '<div class="bf-pin-tail"></div>' +
          '</div>'
        );
        return cdekPinLayout;
      }
      if (customPinLayout || typeof ymaps === "undefined") return customPinLayout;
      customPinLayout = ymaps.templateLayoutFactory.createClass(
        '<div class="bf-map-pin fivepost-pin" title="$[properties.hintContent]">' +
          '<div class="bf-pin-body">' +
            '<img src="/images/pyaterochka.png" alt="Пятёрочка" class="bf-pin-img" width="36" height="36">' +
          '</div>' +
          '<div class="bf-pin-tail"></div>' +
        '</div>'
      );
      return customPinLayout;
    }

    function createPointPlacemark(point, pinLayout) {
      const isPostamat = (point.type === "POSTAMAT");
      const titleName = point.name || (deliveryProvider === "cdek" ? "Пункт выдачи СДЭК" : (isPostamat ? "Постамат 5Post" : "Касса «Пятёрочка»"));
      const fullAddr = point.full_address || point.street || "";

      const balloonHtml = `
        <div class="bf-balloon">
          <div class="bf-balloon-header">
            <strong class="bf-balloon-name">${escapeHtml(titleName)}</strong>
          </div>
          <div class="bf-balloon-address">${escapeHtml(fullAddr)}</div>
          ${point.work_hours ? `<div class="bf-balloon-extra">${escapeHtml(point.work_hours)}</div>` : ""}
          ${point.additional ? `<div class="bf-balloon-extra">${escapeHtml(point.additional)}</div>` : ""}
          <button type="button" class="bf-balloon-select-btn" data-point-id="${escapeHtml(point.id)}">Выбрать эту точку</button>
        </div>
      `;

      return new ymaps.Placemark([point.lat, point.lng], {
        hintContent: (point.name ? point.name + " · " : "") + fullAddr,
        balloonContent: balloonHtml
      }, {
        iconLayout: pinLayout,
        iconOffset: [0, 0],
        iconShape: {
          type: "Rectangle",
          coordinates: [[-18, -44], [18, 0]]
        },
        hideIconOnBalloonOpen: false,
        balloonOffset: [0, -44],
        balloonCloseButton: true,
        balloonAutoPan: true
      });
    }

    function renderMarkers(points, adjustViewport) {
      if (pointList) {
        pointList.hidden = deliveryProvider !== "cdek" || !points?.length;
        pointList.innerHTML = deliveryProvider === "cdek" ? (points || []).map(p => `<button type="button" class="delivery-point-choice" data-point-id="${escapeHtml(p.id)}"><strong>${escapeHtml(p.name)}</strong><span>${escapeHtml(p.full_address)}</span><span>${escapeHtml(p.work_hours)}</span></button>`).join("") : "";
      }
      currentPoints = points || [];
      if (typeof adjustViewport === "undefined") adjustViewport = true;
      if (!pointsCollection || !yandexMap) return;
      pointsCollection.removeAll();
      renderedPointIds.clear();
      currentPoints = [];

      if (!points || points.length === 0) {
        return;
      }

      const pinLayout = createCustomPinLayout();

      points.forEach(function (point) {
        if (!point.lat || !point.lng) return;
        renderedPointIds.add(String(point.id));
        currentPoints.push(point);
        pointsCollection.add(createPointPlacemark(point, pinLayout));
      });

      if (adjustViewport) {
        setProgrammaticMove();
        if (points.length === 1) {
          yandexMap.setCenter([points[0].lat, points[0].lng], 15, { checkZoomRange: true });
        } else if (points.length > 1) {
          const bounds = pointsCollection.getBounds();
          if (bounds) {
            yandexMap.setBounds(bounds, { checkZoomRange: true, zoomMargin: 40 });
          }
        }
      }
    }

    function appendMarkers(newPoints) {
      if (!pointsCollection || !yandexMap || !newPoints || newPoints.length === 0) return;
      const pinLayout = createCustomPinLayout();

      newPoints.forEach(function (point) {
        if (!point.lat || !point.lng) return;
        const strId = String(point.id);
        if (renderedPointIds.has(strId)) return;

        renderedPointIds.add(strId);
        currentPoints.push(point);
        pointsCollection.add(createPointPlacemark(point, pinLayout));
      });
    }

    function loadPointsByBounds() {
      if (!yandexMap || !mapInitialized) return;
      const bounds = yandexMap.getBounds();
      if (!bounds) return;
      const zoom = yandexMap.getZoom();
      if (zoom < 8) return;

      const latMin = Math.min(bounds[0][0], bounds[1][0]);
      const latMax = Math.max(bounds[0][0], bounds[1][0]);
      const lngMin = Math.min(bounds[0][1], bounds[1][1]);
      const lngMax = Math.max(bounds[0][1], bounds[1][1]);

      const boundsParam = latMin.toFixed(6) + "," + lngMin.toFixed(6) + "," + latMax.toFixed(6) + "," + lngMax.toFixed(6);
      const url = pointsEndpoint() + "bounds=" + encodeURIComponent(boundsParam) + "&zoom=" + zoom;
      const provider = deliveryProvider;
      const request = pointsRequest;

      fetch(url)
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.success && Array.isArray(data.points)) {
            if (provider === deliveryProvider && request === pointsRequest) appendMarkers(data.points);
          }
        })
        .catch(function (err) {
          console.warn("Ошибка подгрузки точек по границам карты:", err);
        });
    }

    function loadPoints(city, search, isInitial) {
      const request = ++pointsRequest;
      const provider = deliveryProvider;
      if (isInitial && mapLoader) mapLoader.style.display = "flex";

      let url = pointsEndpoint();
      if (city) url += "city=" + encodeURIComponent(city) + "&";
      if (search) url += "search=" + encodeURIComponent(search);

      fetch(url)
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (request !== pointsRequest || provider !== deliveryProvider) return;
          if (!data?.success) throw new Error(data?.error || "Не удалось загрузить пункты выдачи");
          if (provider === "cdek") updateDeliveryMode(data.mode);
          if (data && data.success && Array.isArray(data.points)) {
            currentPoints = data.points;
            renderMarkers(data.points);

            if (data.city) {
              currentCity = data.city;
            }

            if (data.points.length === 0) {
              showStatus("Пункты " + carrierName() + " не найдены. Укажите город или город и улицу через запятую.", "info");
              if (yandexMap && typeof ymaps !== "undefined" && ymaps.geocode) {
                ymaps.geocode(data.city || search).then(function (res) {
                  const firstGeo = res.geoObjects.get(0);
                  if (request !== pointsRequest || provider !== deliveryProvider) return;
                  if (firstGeo) {
                    setProgrammaticMove();
                    yandexMap.setCenter(firstGeo.geometry.getCoordinates(), 11, { checkZoomRange: true });
                  }
                }).catch(function () {});
              }
            } else {
              showStatus("", null);
            }
          }
        })
        .catch(function (err) {
          if (request !== pointsRequest || provider !== deliveryProvider) return;
          showStatus(escapeHtml(err.message || "Не удалось загрузить пункты выдачи. Попробуйте ещё раз."), "error");
        })
        .finally(function () {
          if (request === pointsRequest && mapLoader) mapLoader.style.display = "none";
        });
    }

    function initCustomYandexMap() {
      if (!fivepostMapContainer) return;

      ensureYandexMapsScript();

      if (mapInitialized && yandexMap) {
        setTimeout(function () {
          try {
            yandexMap.container.fitToViewport();
          } catch (e) {}
        }, 300);
        return;
      }

      function setupYandexMap() {
        if (typeof ymaps === "undefined") return;

        ymaps.ready(function () {
          if (mapInitialized) return;

          yandexMap = new ymaps.Map(fivepostMapContainer, {
            center: [53.20066, 44.99965],
            zoom: 12,
            controls: ["zoomControl", "fullscreenControl"]
          }, {
            suppressMapOpenBlock: true
          });

          pointsCollection = new ymaps.GeoObjectCollection();
          yandexMap.geoObjects.add(pointsCollection);
          mapInitialized = true;

          yandexMap.events.add("boundschange", function () {
            if (isProgrammaticMove) return;
            clearTimeout(boundsDebounceTimer);
            boundsDebounceTimer = setTimeout(function () {
              loadPointsByBounds();
            }, 300);
          });

          if (typeof navigator !== "undefined" && navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
              function (pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                setProgrammaticMove();
                yandexMap.setCenter([lat, lng], 12, { checkZoomRange: true });
                if (typeof ymaps !== "undefined" && ymaps.geocode) {
                  ymaps.geocode([lat, lng]).then(function (res) {
                    const firstGeo = res.geoObjects.get(0);
                    if (firstGeo) {
                      const locality = firstGeo.getLocalities();
                      if (locality && locality.length > 0) {
                        currentCity = locality[0];
                      }
                    }
                    loadPoints(currentCity, "", true);
                  }).catch(function () {
                    loadPoints(currentCity, "", true);
                  });
                } else {
                  loadPoints(currentCity, "", true);
                }
              },
              function () {
                loadPoints(currentCity, "", true);
              },
              { timeout: 4000, maximumAge: 300000 }
            );
          } else {
            loadPoints(currentCity, "", true);
          }

          setTimeout(function () {
            try {
              yandexMap.container.fitToViewport();
            } catch (e) {}
          }, 350);
        });
      }

      if (typeof ymaps !== "undefined") {
        setupYandexMap();
      } else {
        let attempts = 0;
        const checkInterval = setInterval(function () {
          attempts++;
          if (typeof ymaps !== "undefined") {
            clearInterval(checkInterval);
            setupYandexMap();
          } else if (attempts > 60) {
            clearInterval(checkInterval);
            if (mapLoader) {
              mapLoader.innerHTML = `<span style="color:#ef4444; font-size:12px;">Не удалось загрузить Яндекс.Карты. Проверьте соединение.</span>`;
            }
          }
        }, 200);
      }
    }

    if (fivepostMapContainer) {
      fivepostMapContainer.addEventListener("click", function (e) {
        const btn = e.target.closest(".bf-balloon-select-btn");
        if (btn) {
          const pid = btn.getAttribute("data-point-id");
          const pt = currentPoints.find(function (p) { return String(p.id) === String(pid); });
          if (pt) {
            chooseDeliveryPoint(pt);
            if (yandexMap && yandexMap.balloon) {
              yandexMap.balloon.close();
            }
          }
        }
      });
    }

    let searchDebounceTimer = null;
    if (pointList) pointList.addEventListener("click", e => {
      const button = e.target.closest("[data-point-id]");
      const point = button && currentPoints.find(p => String(p.id) === button.dataset.pointId);
      if (point) chooseDeliveryPoint(point);
    });
    if (searchInput) {
      searchInput.addEventListener("input", function () {
        const val = this.value.trim();
        if (clearSearchBtn) {
          clearSearchBtn.style.display = val ? "block" : "none";
        }
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(function () {
          loadPoints("", val, false);
        }, 400);
      });
    }

    if (clearSearchBtn) {
      clearSearchBtn.addEventListener("click", function () {
        if (searchInput) searchInput.value = "";
        clearSearchBtn.style.display = "none";
        loadPoints(currentCity, "", false);
      });
    }

    if (fivepostChangeBtn) {
      fivepostChangeBtn.addEventListener("click", function () {
        if (fivepostCard) fivepostCard.style.display = "none";
        if (fivepostMapWrapper) {
          fivepostMapWrapper.style.display = "block";
          if (yandexMap) {
            setTimeout(function () {
              try {
                yandexMap.container.fitToViewport();
              } catch (e) {}
            }, 100);
          }
        }
      });
    }

    if (phoneInput) {
      phoneInput.addEventListener("input", function (e) {
        let x = e.target.value.replace(/\D/g, "").match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
        if (!x[2]) {
          e.target.value = x[1] ? "+7 (" + (x[1] === "7" || x[1] === "8" ? "" : x[1]) : "";
        } else {
          e.target.value = "+7 (" + x[2] + (x[3] ? ") " + x[3] : "") + (x[4] ? "-" + x[4] : "") + (x[5] ? "-" + x[5] : "");
        }
      });
    }

    const policyAgreeInput = document.getElementById("orderPolicyAgree");
    if (policyAgreeInput) {
      policyAgreeInput.addEventListener("change", function () {
        if (this.checked && statusMsg && statusMsg.textContent.includes("согласие")) {
          showStatus("", null);
        }
      });
    }

    function finalizeModalOpen() {
      ensureScriptsLoaded();
      resetPaymentWidgetView();
      showStatus("", null);

      const totals = getCheckoutTotals();
      const sig = totals.goodsTotal + ":" + totals.totalUnits;
      if (deliveryProvider === "cdek" && deliveryQuote && deliveryQuoteSignature !== sig) {
        clearDeliverySelection();
      }

      if (submitBtn) {
        submitBtn.disabled = false;
        const total = Math.round((totals.goodsTotal + (deliveryQuote?.cost || 0)) * 100) / 100;
        submitBtn.textContent = "Оплатить " + total.toLocaleString("ru-RU") + " ₽ онлайн";
      }

      modal.classList.add("is-open");
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("no-scroll");

      initCustomYandexMap();
    }

    function openSingleModal() {
      const state = getProductState();
      if (!state.gender) {
        flagGenderError();
        return;
      }
      if (!state.size) {
        flagSizeError();
        return;
      }

      checkoutMode = "single";
      cartCheckoutItems = [];

      if (singleSummaryEl) singleSummaryEl.hidden = false;
      if (cartSummaryEl) cartSummaryEl.hidden = true;
      if (modalTitleEl) modalTitleEl.textContent = "Оформление заказа";

      const prodNameEl = document.getElementById("orderModalProdName") || document.getElementById("ozonModalProdName");
      const prodPriceEl = document.getElementById("orderModalProdPrice") || document.getElementById("ozonModalProdPrice");
      const prodGenderEl = document.getElementById("orderModalProdGender") || document.getElementById("ozonModalProdGender");
      const prodSizeEl = document.getElementById("orderModalProdSize") || document.getElementById("ozonModalProdSize");
      const prodThumbEl = document.getElementById("orderModalProdImg") || document.getElementById("ozonModalProdImg");

      if (prodNameEl) prodNameEl.textContent = state.name;
      if (prodPriceEl) prodPriceEl.textContent = state.price;
      if (prodGenderEl) prodGenderEl.textContent = state.gender;
      if (prodSizeEl) prodSizeEl.textContent = state.size || "M";
      if (prodThumbEl && state.img) prodThumbEl.src = state.img;

      finalizeModalOpen();
    }

    function openCartCheckout(items) {
      const validItems = Array.isArray(items)
        ? items.filter(function (it) { return it && it.available !== false && Number(it.qty) > 0; })
        : [];
      if (validItems.length === 0) return;

      checkoutMode = "cart";
      cartCheckoutItems = validItems.map(function (it) { return Object.assign({}, it); });

      if (singleSummaryEl) singleSummaryEl.hidden = true;
      if (cartSummaryEl) cartSummaryEl.hidden = false;

      const totals = getCheckoutTotals();
      if (modalTitleEl) {
        modalTitleEl.textContent = "Оформление заказа (" + totals.totalUnits + " шт.)";
      }
      if (cartCountEl) {
        cartCountEl.textContent = "Состав заказа · " + totals.totalUnits + " шт.";
      }
      if (cartItemsEl) {
        cartItemsEl.innerHTML = cartCheckoutItems.map(function (it) {
          const unit = Number(it.priceNumeric) || parseFloat(String(it.price || "").replace(/[^\d.]/g, "")) || 0;
          const lineSum = Math.round(unit * (Number(it.qty) || 1) * 100) / 100;
          const meta = [it.gender, it.size ? ("размер " + it.size) : "", it.qty > 1 ? ("× " + it.qty + " шт.") : ""].filter(Boolean).join(", ");
          const thumb = it.img
            ? `<img src="${escapeHtml(it.img)}" alt="${escapeHtml(it.name)}" class="checkout-cart-thumb">`
            : `<div class="checkout-cart-thumb checkout-cart-thumb--placeholder">BF</div>`;

          return `
            <div class="checkout-cart-row">
              ${thumb}
              <div class="checkout-cart-row-info">
                <div class="checkout-cart-row-name">${escapeHtml(it.name)}</div>
                ${meta ? `<div class="checkout-cart-row-meta">${escapeHtml(meta)}</div>` : ""}
              </div>
              <div class="checkout-cart-row-price">${formatRub(lineSum)}</div>
            </div>
          `;
        }).join("");
      }
      if (cartSubtotalEl) {
        cartSubtotalEl.textContent = formatRub(totals.goodsTotal);
      }

      finalizeModalOpen();
    }

    function closeModal() {
      resetPaymentWidgetView();
      modal.classList.remove("is-open");
      modal.setAttribute("aria-hidden", "true");
      const drawer = document.getElementById("cartDrawer");
      if (!drawer || !drawer.classList.contains("is-open")) {
        document.body.classList.remove("no-scroll");
      }
    }

    if (orderBtn) orderBtn.addEventListener("click", openSingleModal);
    if (closeBtn) closeBtn.addEventListener("click", closeModal);
    if (backToCartBtn) {
      backToCartBtn.addEventListener("click", function () {
        closeModal();
        if (window.BFCart && typeof window.BFCart.openDrawer === "function") {
          window.BFCart.openDrawer();
        }
      });
    }

    modal.addEventListener("click", function (e) {
      if (e.target === modal) closeModal();
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modal.classList.contains("is-open")) {
        closeModal();
      }
    });

    function showStatus(msg, type) {
      if (!statusMsg) return;
      statusMsg.innerHTML = msg;
      statusMsg.className = "ozon-status-msg order-status-msg " + (type ? "is-" + type : "");
    }

    function showSuccessScreen(orderId, order) {
      const modalBody = modal.querySelector ? (modal.querySelector(".order-modal-body") || modal.querySelector(".ozon-modal-body")) : null;
      if (!modalBody) return;

      const pointTypeRu = order.fivepostPointType === "POSTAMAT" ? "Постамат" : (order.fivepostPointType === "TOBACCO" ? "Касса" : "Пункт выдачи");
      const itemsHtml = Array.isArray(order.items) && order.items.length > 0
        ? order.items.map(function (it) {
            const meta = [it.gender, it.size ? ("размер " + it.size) : ""].filter(Boolean).join(", ");
            const qtySuffix = Number(it.qty) > 1 ? ` × ${Number(it.qty)} шт.` : "";
            return `<div><span>Товар:</span> ${escapeHtml(it.name || it.productName)}${meta ? ` (${escapeHtml(meta)})` : ""}${qtySuffix}</div>`;
          }).join("")
        : `<div><span>Товар:</span> ${escapeHtml(order.productName)} (${escapeHtml(order.gender)}, размер ${escapeHtml(order.size)})</div>`;

      modalBody.innerHTML = `
        <div class="ozon-success-box order-success-box">
          <div class="ozon-success-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
          <h3>Заказ успешно оплачен!</h3>
          <div class="ozon-order-id">Номер заказа: <strong>${escapeHtml(orderId)}</strong></div>
          ${order.fivepostBarcode ? `<div class="ozon-order-id" style="margin-top:6px; font-size:13px; color:#10b981;">Трек 5Post: <strong>${escapeHtml(order.fivepostBarcode)}</strong></div>` : ""}
          <p class="ozon-success-desc">
            Спасибо! Заказ передан в обработку. Служба доставки: <strong>${carrierName()}</strong>.
          </p>
          <div class="ozon-success-summary">
            ${itemsHtml}
            <div><span>Итого с доставкой:</span> <strong>${escapeHtml(order.totalAmount)} ₽</strong> <span style="color:#10b981; font-weight:600;">(Оплачено)</span></div>
            ${Number(order.deliveryCost) > 0 ? `<div><span>В том числе доставка:</span> ${escapeHtml(order.deliveryCost)} ₽</div>` : ""}
            ${order.fio ? `<div><span>ФИО получателя:</span> <strong>${escapeHtml(order.fio)}</strong></div>` : ""}
            ${order.email ? `<div><span>Email:</span> <strong>${escapeHtml(order.email)}</strong></div>` : ""}
            <div><span>Телефон:</span> ${escapeHtml(order.phone)}</div>
            ${order.fivepostPointAddress ? `<div><span>Доставка ${carrierName()}:</span> <strong>${escapeHtml(order.fivepostPointAddress)}</strong> (${pointTypeRu})</div>` : ""}
            ${order.transactionId ? `<div><span>ID транзакции:</span> #${escapeHtml(order.transactionId)}</div>` : ""}
          </div>
          <p class="ozon-sms-note">
            ${deliveryMode === "sandbox" ? "Тестовый заказ СДЭК: посылка не будет отправлена." : "Уведомим вас о доставке заказа в выбранный пункт выдачи."}
          </p>
          <button type="button" class="btn-primary btn-block" id="orderDoneBtn" style="margin-top:12px;">Отлично</button>
        </div>
      `;

      document.getElementById("orderDoneBtn")?.addEventListener("click", closeModal);
    }

    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();

        const fio = (fioInput?.value || "").trim();
        const email = (emailInput?.value || "").trim();
        const phone = (phoneInput?.value || "").trim();

        if (!fio || fio.length < 3) {
          showStatus("Укажите ваше ФИО (Фамилию, Имя и Отчество)", "error");
          fioInput?.focus();
          return;
        }

        const fioWords = fio.split(/\s+/).filter(Boolean);
        if (fioWords.length < 2) {
          showStatus("Укажите как минимум Имя и Фамилию для получения посылки в " + carrierName(), "error");
          fioInput?.focus();
          return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email || !emailRegex.test(email)) {
          showStatus("Введите корректный адрес электронной почты", "error");
          emailInput?.focus();
          return;
        }

        if (!phone || phone.length < 16) {
          showStatus("Введите полный номер телефона (+7 ...)", "error");
          phoneInput?.focus();
          return;
        }

        const pointId = (fivepostIdInput?.value || "").trim();
        if (!pointId) {
          showStatus("Пожалуйста, выберите пункт выдачи " + carrierName(), "error");
          if (fivepostMapWrapper) {
            fivepostMapWrapper.style.display = "block";
            if (fivepostCard) fivepostCard.style.display = "none";
            fivepostMapWrapper.scrollIntoView({ behavior: "smooth", block: "center" });
            if (yandexMap) {
              setTimeout(function () {
                try {
                  yandexMap.container.fitToViewport();
                } catch (e) {}
              }, 100);
            }
          }
          return;
        }

        const policyAgreeCheckbox = document.getElementById("orderPolicyAgree");
        if (policyAgreeCheckbox && !policyAgreeCheckbox.checked) {
          showStatus("Пожалуйста, подтвердите согласие с Правилами и политикой конфиденциальности", "error");
          policyAgreeCheckbox.focus();
          return;
        }

        if (deliveryProvider === "cdek" && !deliveryQuote) {
          showStatus("Выберите пункт СДЭК и дождитесь расчёта доставки", "error");
          return;
        }

        const isCartOrder = checkoutMode === "cart";
        const totals = getCheckoutTotals();
        if (isCartOrder && (!cartCheckoutItems || cartCheckoutItems.length === 0)) {
          showStatus("Корзина пуста. Добавьте товары для оформления заказа.", "error");
          return;
        }

        const random = new Uint8Array(8);
        window.crypto.getRandomValues(random);
        const orderId = "BF-" + Array.from(random, n => n.toString(16).padStart(2, "0")).join("").toUpperCase();

        let orderPayload;
        if (isCartOrder) {
          const firstItem = cartCheckoutItems[0];
          orderPayload = {
            orderId: orderId,
            items: cartCheckoutItems.map(function (it) {
              return {
                productId: it.productId,
                name: it.name,
                gender: it.gender,
                size: it.size,
                qty: Number(it.qty) || 1
              };
            }),
            productId: firstItem.productId,
            productName: cartCheckoutItems.length > 1
              ? firstItem.name + " и ещё " + (cartCheckoutItems.length - 1) + " поз."
              : firstItem.name,
            price: String(totals.goodsTotal),
            gender: firstItem.gender,
            size: firstItem.size,
            fio: fio,
            email: email,
            phone: phone.replace(/[^\d+]/g, ""),
            deliveryProvider: deliveryProvider,
            deliveryQuoteMode: deliveryMode,
            deliveryQuoteCost: deliveryQuote?.cost,
            fivepostPointId: pointId,
            fivepostPointName: (fivepostNameInput?.value || "").trim(),
            fivepostPointAddress: (fivepostAddrInput?.value || "").trim(),
            fivepostPointType: (fivepostTypeInput?.value || "").trim(),
            fivepostPointDetails: (fivepostDetailsInput?.value || "").trim(),
            source: "Корзина (" + carrierName() + " + ЮKassa)"
          };
        } else {
          const state = totals.state;
          orderPayload = {
            orderId: orderId,
            productId: state.id,
            productName: state.name,
            price: state.price,
            gender: state.gender,
            size: state.size,
            fio: fio,
            email: email,
            phone: phone.replace(/[^\d+]/g, ""),
            deliveryProvider: deliveryProvider,
            deliveryQuoteMode: deliveryMode,
            deliveryQuoteCost: deliveryQuote?.cost,
            fivepostPointId: pointId,
            fivepostPointName: (fivepostNameInput?.value || "").trim(),
            fivepostPointAddress: (fivepostAddrInput?.value || "").trim(),
            fivepostPointType: (fivepostTypeInput?.value || "").trim(),
            fivepostPointDetails: (fivepostDetailsInput?.value || "").trim(),
            source: "Онлайн-оплата (" + carrierName() + " + ЮKassa)"
          };
        }

        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = "Создание платежа...";
        }
        showStatus("Соединение с платежным шлюзом...", "info");

        fetch("/api/yookassa/create.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(orderPayload)
        })
          .then(res => res.json())
          .then(data => {
            if (data.success && data.confirmation_token) {
              if (typeof window.YooMoneyCheckoutWidget === "undefined") {
                showStatus("Не удалось загрузить форму оплаты. Возможно, она заблокирована расширением (AdBlock) или антивирусом. Пожалуйста, отключите блокировщик и обновите страницу.", "error");
                if (submitBtn) {
                  submitBtn.disabled = false;
                  submitBtn.textContent = "Оплатить онлайн";
                }
                return;
              }

              const origin = (window.location && window.location.origin) ? window.location.origin : "";
              if (isCartOrder) {
                try {
                  if (window.localStorage) {
                    window.localStorage.setItem("bf_pending_cart_order", orderId);
                  }
                } catch (err) {}
              }

              const checkout = new window.YooMoneyCheckoutWidget({
                confirmation_token: data.confirmation_token,
                return_url: origin + "/payment-success.php?orderId=" + encodeURIComponent(orderId),
                customization: {
                  colors: {
                    control_primary: "#000000",
                    background: "#F2F3F5"
                  }
                },
                error_callback: function (error) {
                  console.error("YooKassa Widget Error:", error);
                  let errMsg = "Ошибка виджета оплаты";
                  if (error) {
                    if (typeof error === "string") errMsg += ": " + error;
                    else if (error.error) errMsg += ": " + error.error;
                    else if (error.message) errMsg += ": " + error.message;
                  }
                  let supportMsg = "<br><br><span style='color: #3b82f6; font-size: 0.95em; display: block; margin-top: 5px;'>Если у вас возникает такая ошибка, пожалуйста, отправьте скриншот нам в Telegram (чтобы было видно всю ошибку) <a href='https://t.me/theboyforge' target='_blank' style='color: inherit; text-decoration: underline;'>@theboyforge</a>. После этого обновите страницу и попробуйте ещё раз.</span>";
                  resetPaymentWidgetView();
                  showStatus(errMsg + supportMsg, "error");
                }
              });

              activeYookassaWidget = checkout;

              checkout.on("success", () => {
                fetch("/api/yookassa/confirm.php", {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    paymentId: data.payment_id,
                    orderId: orderId
                  })
                }).then(res => {
                  if (!res.ok) throw new Error("Не удалось подтвердить платёж");
                  return res.json();
                }).then(result => {
                  if (result.success && isCartOrder && window.BFCart) window.BFCart.clear();
                }).catch(() => {
                  // На странице возврата повторим проверку; корзину сохраняем до подтверждения.
                }).finally(() => {
                  checkout.destroy();
                  activeYookassaWidget = null;
                  if (window.location) {
                    window.location.href = "/payment-success.php?orderId=" + orderId;
                  }
                });
              });

              checkout.on("fail", () => {
                resetPaymentWidgetView();
                showStatus("Оплата не прошла", "error");
              });

              providerButtons.forEach(button => { button.disabled = true; });
              pointsRequest++;
              selectionRequest++;

              if (submitBtn) submitBtn.style.display = "none";
              if (orderAgreeWrap) orderAgreeWrap.style.display = "none";
              if (orderDetailsSections) orderDetailsSections.style.display = "none";

              showStatus("", null);
              document.cookie = "last_order_id=" + orderId + "; max-age=300; path=/";

              if (widgetContainer) {
                widgetContainer.style.display = "block";
                widgetContainer.innerHTML = "";
                checkout.render("yookassaWidgetContainer");
              } else {
                if (fivepostCard) fivepostCard.style.display = "none";
                if (fivepostMapWrapper) {
                  fivepostMapWrapper.style.display = "block";
                  fivepostMapWrapper.innerHTML = "";
                }
                checkout.render("fivepostMapWrapper");
              }
            } else {
              throw new Error(data.error || "Ошибка создания платежа");
            }
          })
          .catch(err => {
            console.error(err);
            let supportMsg = "<br><br><span style='color: #3b82f6; font-size: 0.95em; display: block; margin-top: 5px;'>Если у вас возникает такая ошибка, пожалуйста, отправьте скриншот нам в Telegram (чтобы было видно всю ошибку) <a href='https://t.me/theboyforge' target='_blank' style='color: inherit; text-decoration: underline;'>@theboyforge</a>. После этого обновите страницу и попробуйте ещё раз.</span>";
            showStatus((err.message || "Не удалось инициировать оплату") + supportMsg, "error");
            if (submitBtn) {
              submitBtn.disabled = false;
              const totalsNow = getCheckoutTotals();
              const retrySum = Math.round((totalsNow.goodsTotal + (deliveryQuote?.cost || 0)) * 100) / 100;
              submitBtn.textContent = "Оплатить " + retrySum.toLocaleString("ru-RU") + " ₽ онлайн";
            }
          });
      });
    }

    if (typeof window !== "undefined") {
      window.BFCheckout = {
        openCartCheckout: openCartCheckout,
        openSingleModal: openSingleModal,
        ensureScriptsLoaded: ensureScriptsLoaded
      };
    }
  });
})();
