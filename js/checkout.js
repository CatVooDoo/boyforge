"use strict";

(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    const orderBtn = document.getElementById("orderModalBtn") || document.getElementById("ozonOrderBtn");
    const modal = document.getElementById("orderModal") || document.getElementById("ozonModal");
    if (!orderBtn || !modal) return;

    const closeBtn = document.getElementById("orderModalClose") || document.getElementById("ozonModalClose");
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

    let yandexMap = null;
    let pointsCollection = null;
    let currentPoints = [];
    let currentCity = "Пенза";
    let customPinLayout = null;
    let mapInitialized = false;
    const renderedPointIds = new Set();
    let boundsDebounceTimer = null;
    let isProgrammaticMove = false;
    let deliveryProvider = 'fivepost';
    let deliveryQuote = null;
    let deliveryMode = null;
    let pointsRequest = 0;
    let selectionRequest = 0;
    let cdekPinLayout = null;
    const providerButtons = document.querySelectorAll('[data-delivery-provider]');
    const deliveryNotice = document.getElementById('deliveryNotice');
    const quoteEl = document.getElementById('deliveryQuote');
    const pointList = document.getElementById('deliveryPointList');
    const carrierName = () => deliveryProvider === 'cdek' ? 'СДЭК' : '5Post';
    const pointsEndpoint = () => deliveryProvider === 'cdek' ? '/api/cdek-points.php?' : '/api/fivepost-points.php?';

    function clearDeliverySelection() {
      selectionRequest++;
      deliveryQuote = null;
      [fivepostIdInput, fivepostNameInput, fivepostAddrInput, fivepostTypeInput, fivepostDetailsInput].forEach(input => { if (input) input.value = ''; });
      if (fivepostCard) fivepostCard.style.display = 'none';
      if (fivepostMapWrapper) fivepostMapWrapper.style.display = 'block';
      if (quoteEl) quoteEl.hidden = true;
      if (submitBtn) submitBtn.textContent = 'Оплатить онлайн картой / СБП';
    }

    providerButtons.forEach(button => button.addEventListener('click', function () {
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
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', String(active));
      });
      if (deliveryNotice) {
        deliveryNotice.hidden = false;
        deliveryNotice.textContent = next === 'cdek' ? 'Загружаем пункты СДЭК…' : 'Доставка 5Post за счёт магазина.';
      }
      if (pointList) { pointList.hidden = true; pointList.innerHTML = ''; }
      if (yandexMap) setTimeout(() => yandexMap.container.fitToViewport(), 50);
      loadPoints('', searchInput?.value.trim() || currentCity, true);
    }));

    function updateDeliveryMode(mode) {
      deliveryMode = mode;
      if (deliveryNotice && deliveryProvider === 'cdek') {
        deliveryNotice.hidden = false;
        deliveryNotice.textContent = mode === 'sandbox'
          ? 'Тестовый режим СДЭК: можно выбрать пункт и оформить тестовый заказ. Посылка не будет отправлена. Для оплаты нужен тестовый магазин ЮKassa.'
          : 'Доставку СДЭК оплачиваете вы вместе с товаром. Стоимость и срок появятся после выбора пункта.';
      }
    }

    async function chooseDeliveryPoint(point) {
      if (deliveryProvider !== 'cdek') { handleSelect5PostPoint(point); return; }
      clearDeliverySelection();
      const request = selectionRequest;
      showStatus('Рассчитываем стоимость доставки СДЭК…', 'info');
      try {
        const value = parseFloat(getProductState().price.replace(/[^\d.]/g, '')) || 3200;
        const res = await fetch('/api/cdek-quote.php?point=' + encodeURIComponent(point.id) + '&value=' + encodeURIComponent(value));
        const data = await res.json();
        if (request !== selectionRequest || deliveryProvider !== 'cdek') return;
        if (!res.ok || !data.success) throw new Error(data.error || 'Не удалось рассчитать доставку');
        deliveryQuote = data.quote;
        updateDeliveryMode(data.mode);
        handleSelect5PostPoint(data.point);
        const total = (parseFloat(getProductState().price.replace(/[^\d.]/g, '')) || 3200) + data.quote.cost;
        if (quoteEl) {
          quoteEl.hidden = false;
          quoteEl.textContent = `Доставка: ${data.quote.cost.toLocaleString('ru-RU')} ₽ · ${data.quote.days_min}–${data.quote.days_max} дн. Итого с товаром: ${total.toLocaleString('ru-RU')} ₽`;
        }
        if (submitBtn) submitBtn.textContent = 'Оплатить ' + total.toLocaleString('ru-RU') + ' ₽ онлайн';
      } catch (err) {
        if (request === selectionRequest) showStatus(escapeHtml(err.message), 'error');
      }
    }

    function setProgrammaticMove() {
      isProgrammaticMove = true;
      setTimeout(function () {
        isProgrammaticMove = false;
      }, 700);
    }

    function escapeHtml(str) {
      if (!str) return "";
      return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
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
      const pointName = point.name || (deliveryProvider === 'cdek' ? 'Пункт выдачи СДЭК' : (pointType === "POSTAMAT" ? "Постамат 5Post" : "Касса «Пятёрочка»"));
      const pointAddr = point.full_address || point.resultAddress || point.address || "";
      const pointExtra = [point.additional, point.work_hours].filter(Boolean).join(" · ");

      if (fivepostIdInput) fivepostIdInput.value = pointId;
      if (fivepostNameInput) fivepostNameInput.value = pointName;
      if (fivepostAddrInput) fivepostAddrInput.value = pointAddr;
      if (fivepostTypeInput) fivepostTypeInput.value = pointType;
      if (fivepostDetailsInput) fivepostDetailsInput.value = pointExtra;

      if (badgeEl) {
        badgeEl.textContent = pointType === 'PVZ' ? 'Пункт выдачи' : (pointType === "POSTAMAT" ? "Постамат" : "Касса");
      }
      if (nameEl) nameEl.textContent = pointName;
      if (addrEl) addrEl.textContent = pointAddr;
      if (extraEl) extraEl.textContent = pointExtra || ('Выдача заказа ' + carrierName());

      if (fivepostCard) fivepostCard.style.display = "flex";
      if (fivepostMapWrapper) fivepostMapWrapper.style.display = "none";

      showStatus("", null);
    }

    function createCustomPinLayout() {
      if (deliveryProvider === 'cdek' && typeof ymaps !== 'undefined') {
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
      const titleName = point.name || (deliveryProvider === 'cdek' ? 'Пункт выдачи СДЭК' : (isPostamat ? "Постамат 5Post" : "Касса «Пятёрочка»"));
      const fullAddr = point.full_address || point.street || "";

      const balloonHtml = `
        <div class="bf-balloon">
          <div class="bf-balloon-header">
            <strong class="bf-balloon-name">${escapeHtml(titleName)}</strong>
          </div>
          <div class="bf-balloon-address">${escapeHtml(fullAddr)}</div>
          ${point.work_hours ? `<div class="bf-balloon-extra">${escapeHtml(point.work_hours)}</div>` : ''}
          ${point.additional ? `<div class="bf-balloon-extra">${escapeHtml(point.additional)}</div>` : ''}
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
        pointList.hidden = deliveryProvider !== 'cdek' || !points?.length;
        pointList.innerHTML = deliveryProvider === 'cdek' ? (points || []).map(p => `<button type="button" class="delivery-point-choice" data-point-id="${escapeHtml(p.id)}"><strong>${escapeHtml(p.name)}</strong><span>${escapeHtml(p.full_address)}</span><span>${escapeHtml(p.work_hours)}</span></button>`).join('') : '';
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
          if (!data?.success) throw new Error(data?.error || 'Не удалось загрузить пункты выдачи');
          if (provider === 'cdek') updateDeliveryMode(data.mode);
          if (data && data.success && Array.isArray(data.points)) {
            currentPoints = data.points;
            renderMarkers(data.points);

            if (data.city) {
              currentCity = data.city;
            }

            if (data.points.length === 0) {
              showStatus('Пункты ' + carrierName() + ' не найдены. Укажите город или город и улицу через запятую.', 'info');
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
          showStatus(escapeHtml(err.message || 'Не удалось загрузить пункты выдачи. Попробуйте ещё раз.'), 'error');
        })
        .finally(function () {
          if (request === pointsRequest && mapLoader) mapLoader.style.display = "none";
        });
    }

    function initCustomYandexMap() {
      if (!fivepostMapContainer) return;

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

          if (navigator.geolocation) {
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
          } else if (attempts > 50) {
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
    if (pointList) pointList.addEventListener('click', e => {
      const button = e.target.closest('[data-point-id]');
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

    function openModal() {
      const state = getProductState();
      if (!state.gender) {
        flagGenderError();
        return;
      }
      if (!state.size) {
        flagSizeError();
        return;
      }

      const nameEl = document.getElementById("orderModalProdName") || document.getElementById("ozonModalProdName");
      const priceEl = document.getElementById("orderModalProdPrice") || document.getElementById("ozonModalProdPrice");
      const genderEl = document.getElementById("orderModalProdGender") || document.getElementById("ozonModalProdGender");
      const sizeEl = document.getElementById("orderModalProdSize") || document.getElementById("ozonModalProdSize");
      const thumbEl = document.getElementById("orderModalProdImg") || document.getElementById("ozonModalProdImg");

      if (nameEl) nameEl.textContent = state.name;
      if (priceEl) priceEl.textContent = state.price;
      if (genderEl) genderEl.textContent = state.gender;
      if (sizeEl) sizeEl.textContent = state.size || "M";
      if (thumbEl && state.img) thumbEl.src = state.img;

      if (submitBtn) {
        submitBtn.disabled = false;
        const total = (parseFloat(state.price.replace(/[^\d.]/g, '')) || 3200) + (deliveryQuote?.cost || 0);
        submitBtn.textContent = 'Оплатить ' + total.toLocaleString('ru-RU') + ' ₽ онлайн';
      }

      modal.classList.add("is-open");
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("no-scroll");

      initCustomYandexMap();
    }

    function closeModal() {
      modal.classList.remove("is-open");
      modal.setAttribute("aria-hidden", "true");
      document.body.classList.remove("no-scroll");
    }

    orderBtn.addEventListener("click", openModal);
    if (closeBtn) closeBtn.addEventListener("click", closeModal);

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
      const modalBody = modal.querySelector(".order-modal-body") || modal.querySelector(".ozon-modal-body");
      if (!modalBody) return;

      const pointTypeRu = order.fivepostPointType === "POSTAMAT" ? "Постамат" : (order.fivepostPointType === "TOBACCO" ? "Касса" : "Пункт выдачи");

      modalBody.innerHTML = `
        <div class="ozon-success-box order-success-box">
          <div class="ozon-success-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
          <h3>Заказ успешно оплачен!</h3>
          <div class="ozon-order-id">Номер заказа: <strong>${orderId}</strong></div>
          ${order.fivepostBarcode ? `<div class="ozon-order-id" style="margin-top:6px; font-size:13px; color:#10b981;">Трек 5Post: <strong>${escapeHtml(order.fivepostBarcode)}</strong></div>` : ''}
          <p class="ozon-success-desc">
            Спасибо! Заказ передан в обработку. Служба доставки: <strong>${carrierName()}</strong>.
          </p>
          <div class="ozon-success-summary">
            <div><span>Товар:</span> ${escapeHtml(order.productName)} (${escapeHtml(order.gender)}, размер ${escapeHtml(order.size)})</div>
            <div><span>Итого с доставкой:</span> <strong>${escapeHtml(order.totalAmount)} ₽</strong> <span style="color:#10b981; font-weight:600;">(Оплачено)</span></div>
            ${Number(order.deliveryCost) > 0 ? `<div><span>В том числе доставка:</span> ${escapeHtml(order.deliveryCost)} ₽</div>` : ''}
            ${order.fio ? `<div><span>ФИО получателя:</span> <strong>${escapeHtml(order.fio)}</strong></div>` : ''}
            ${order.email ? `<div><span>Email:</span> <strong>${escapeHtml(order.email)}</strong></div>` : ''}
            <div><span>Телефон:</span> ${escapeHtml(order.phone)}</div>
            ${order.fivepostPointAddress ? `<div><span>Доставка ${carrierName()}:</span> <strong>${escapeHtml(order.fivepostPointAddress)}</strong> (${pointTypeRu})</div>` : ''}
            ${order.transactionId ? `<div><span>ID транзакции:</span> #${escapeHtml(order.transactionId)}</div>` : ''}
          </div>
          <p class="ozon-sms-note">
            ${deliveryMode === 'sandbox' ? 'Тестовый заказ СДЭК: посылка не будет отправлена.' : 'Уведомим вас о доставке заказа в выбранный пункт выдачи.'}
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
          showStatus('Укажите как минимум Имя и Фамилию для получения посылки в ' + carrierName(), 'error');
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
          showStatus('Пожалуйста, выберите пункт выдачи ' + carrierName(), 'error');
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

        const state = getProductState();
        if (deliveryProvider === 'cdek' && !deliveryQuote) {
          showStatus('Выберите пункт СДЭК и дождитесь расчёта доставки', 'error');
          return;
        }
        const numPrice = parseFloat(state.price.replace(/[^\d.]/g, "")) || 3200;
        const random = new Uint8Array(8);
        window.crypto.getRandomValues(random);
        const orderId = 'BF-' + Array.from(random, n => n.toString(16).padStart(2, '0')).join('').toUpperCase();

        const orderPayload = {
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
          source: 'Онлайн-оплата (' + carrierName() + ' + ЮKassa)'
        };

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
                if (typeof window.YooMoneyCheckoutWidget === 'undefined') {
                    showStatus("Не удалось загрузить форму оплаты. Возможно, она заблокирована расширением (AdBlock) или антивирусом. Пожалуйста, отключите блокировщик и обновите страницу.", "error");
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = "Оплатить онлайн";
                    }
                    return;
                }

                const checkout = new window.YooMoneyCheckoutWidget({
                    confirmation_token: data.confirmation_token,
                    return_url: window.location.origin + '/payment-success.php?orderId=' + encodeURIComponent(orderId),
                    customization: {
                        colors: {
                            control_primary: '#000000',
                            background: '#F2F3F5'
                        }
                    },
                    error_callback: function(error) {
                        console.error("YooKassa Widget Error:", error);
                        let errMsg = "Ошибка виджета оплаты";
                        if (error) {
                            if (typeof error === 'string') errMsg += ": " + error;
                            else if (error.error) errMsg += ": " + error.error;
                            else if (error.message) errMsg += ": " + error.message;
                        }
                        let supportMsg = "<br><br><span style='color: #3b82f6; font-size: 0.95em; display: block; margin-top: 5px;'>Если у вас возникает такая ошибка, пожалуйста, отправьте скриншот нам в Telegram (чтобы было видно всю ошибку) <a href='https://t.me/theboyforge' target='_blank' style='color: inherit; text-decoration: underline;'>@theboyforge</a>. После этого обновите страницу и попробуйте ещё раз.</span>";
                        showStatus(errMsg + supportMsg, "error");
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = "Оплатить онлайн";
                        }
                    }
                });
                
                checkout.on('success', () => {
                    fetch("/api/yookassa/confirm.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            paymentId: data.payment_id,
                            orderId: orderId
                        })
                    }).finally(() => {
                        const finalPayload = Object.assign({}, orderPayload, {
                            totalAmount: data.total_amount,
                            deliveryCost: data.delivery_cost,
                            paymentStatus: "paid",
                            transactionId: data.payment_id || ""
                        });
                        
                        showSuccessScreen(orderId, finalPayload);
                        checkout.destroy();
                        window.location.href = "/payment-success.php?orderId=" + orderId;
                    });
                });
                
                checkout.on('fail', () => {
                    showStatus("Оплата не прошла", "error");
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = "Оплатить онлайн";
                    }
                    checkout.destroy();
                });

                if (fivepostCard) fivepostCard.style.display = "none";
                providerButtons.forEach(button => { button.disabled = true; });
                pointsRequest++;
                selectionRequest++;
                if (fivepostMapWrapper) {
                    fivepostMapWrapper.style.display = "block";
                    fivepostMapWrapper.innerHTML = '';
                }
                
                if (submitBtn) submitBtn.style.display = "none";
                const formSection = document.querySelector(".order-details-sections");
                if (formSection) formSection.style.display = "none";
                
                showStatus("", null);
                document.cookie = "last_order_id=" + orderId + "; max-age=300; path=/";
                checkout.render('fivepostMapWrapper');
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
                submitBtn.textContent = "Оплатить онлайн";
            }
        });
      });
    }

  });
})();
