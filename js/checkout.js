"use strict";

/**
 * checkout.js — Модуль оформления и онлайн-оплаты заказа через CloudPayments
 * BOYFORGE
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    const orderBtn = document.getElementById("orderModalBtn") || document.getElementById("ozonOrderBtn");
    const modal = document.getElementById("orderModal") || document.getElementById("ozonModal");
    if (!orderBtn || !modal) return;

    const closeBtn = document.getElementById("orderModalClose") || document.getElementById("ozonModalClose");
    const form = document.getElementById("orderForm") || document.getElementById("ozonOrderForm");
    const fioInput = document.getElementById("orderFio");
    const tgInput = document.getElementById("orderTg") || document.getElementById("ozonTg") || document.getElementById("ozonName");
    const phoneInput = document.getElementById("orderPhone") || document.getElementById("ozonPhone");
    const submitBtn = document.getElementById("orderConfirmBtn") || document.getElementById("ozonConfirmBtn");
    const statusMsg = document.getElementById("orderFormStatus") || document.getElementById("ozonFormStatus");

    // Элементы 5Post и кастомной Яндекс.Карты
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

    // Получить текущее состояние товара со страницы
    function getProductState() {
      const gActive = document.querySelector("#genders button.active");
      const sActive = document.querySelector(".sizes button.active");
      const prodNameEl = document.getElementById("prodName");
      const prodPriceEl = document.getElementById("prodPrice");
      const mainImgEl = document.getElementById("galleryMain");

      const params = new URLSearchParams(window.location.search);
      const prodId = params.get("id") || "1";

      return {
        id: prodId,
        name: prodNameEl ? prodNameEl.textContent.trim() : "Товар BOYFORGE",
        price: prodPriceEl ? prodPriceEl.textContent.trim() : "3 200 ₽",
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

    // Обработка выбора точки 5Post на карте
    function handleSelect5PostPoint(point) {
      if (!point) return;

      const pointId = point.id || "";
      const pointType = point.type || "POSTAMAT";
      const pointName = point.name || (pointType === "POSTAMAT" ? "Постамат 5Post" : "Касса «Пятёрочка»");
      const pointAddr = point.full_address || point.resultAddress || point.address || "";
      const pointExtra = [point.additional, point.work_hours].filter(Boolean).join(" · ");

      if (fivepostIdInput) fivepostIdInput.value = pointId;
      if (fivepostNameInput) fivepostNameInput.value = pointName;
      if (fivepostAddrInput) fivepostAddrInput.value = pointAddr;
      if (fivepostTypeInput) fivepostTypeInput.value = pointType;
      if (fivepostDetailsInput) fivepostDetailsInput.value = pointExtra;

      if (badgeEl) {
        badgeEl.textContent = pointType === "POSTAMAT" ? "Постамат" : "Касса";
      }
      if (nameEl) nameEl.textContent = pointName;
      if (addrEl) addrEl.textContent = pointAddr;
      if (extraEl) extraEl.textContent = pointExtra || "Выдача заказа 5Post";

      if (fivepostCard) fivepostCard.style.display = "flex";
      if (fivepostMapWrapper) fivepostMapWrapper.style.display = "none";

      showStatus("", null);
    }

    function createCustomPinLayout() {
      if (customPinLayout || typeof ymaps === "undefined") return customPinLayout;
      customPinLayout = ymaps.templateLayoutFactory.createClass(
        '<div class="bf-map-pin fivepost-pin" title="$[properties.hintContent]">' +
          '<div class="bf-pin-body">' +
            '<img src="/images/pyaterochka-pin.png" alt="Пятёрочка" class="bf-pin-img" width="36" height="36" onerror="this.src=\'/free-png.ru-53.png\'">' +
          '</div>' +
          '<div class="bf-pin-tail"></div>' +
        '</div>'
      );
      return customPinLayout;
    }

    function createPointPlacemark(point, pinLayout) {
      const isPostamat = (point.type === "POSTAMAT");
      const badgeText = isPostamat ? "Постамат" : "Касса";
      const titleName = point.name || (isPostamat ? "Постамат 5Post" : "Касса «Пятёрочка»");
      const fullAddr = point.full_address || point.street || "";

      const balloonHtml = `
        <div class="bf-balloon">
          <div class="bf-balloon-header">
            <span class="bf-balloon-badge">${badgeText}</span>
            <strong class="bf-balloon-name">${escapeHtml(titleName)}</strong>
          </div>
          <div class="bf-balloon-address">${escapeHtml(fullAddr)}</div>
          ${point.work_hours ? `<div class="bf-balloon-extra">🕒 ${escapeHtml(point.work_hours)}</div>` : ''}
          ${point.additional ? `<div class="bf-balloon-extra">ℹ️ ${escapeHtml(point.additional)}</div>` : ''}
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
      const url = "/api/fivepost-points.php?bounds=" + encodeURIComponent(boundsParam) + "&zoom=" + zoom;

      fetch(url)
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.success && Array.isArray(data.points)) {
            appendMarkers(data.points);
          }
        })
        .catch(function (err) {
          console.warn("Ошибка подгрузки точек по границам карты:", err);
        });
    }

    function loadPoints(city, search, isInitial) {
      if (isInitial && mapLoader) mapLoader.style.display = "flex";

      let url = "/api/fivepost-points.php?";
      if (city) url += "city=" + encodeURIComponent(city) + "&";
      if (search) url += "search=" + encodeURIComponent(search);

      fetch(url)
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.success && Array.isArray(data.points)) {
            currentPoints = data.points;
            renderMarkers(data.points);

            if (data.city) {
              currentCity = data.city;
            }

            if (data.points.length === 0) {
              showStatus("В населённом пункте «" + (data.city || search) + "» пунктов 5Post нет (сеть действует в магазинах «Пятёрочка» и «Перекрёсток»). Выберите другой город или оформите доставку CDEK.", "info");
              if (yandexMap && typeof ymaps !== "undefined" && ymaps.geocode) {
                ymaps.geocode(data.city || search).then(function (res) {
                  const firstGeo = res.geoObjects.get(0);
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
          console.error("Ошибка загрузки точек 5Post:", err);
          showStatus("Не удалось загрузить точки выдачи. Проверьте интернет-соединение и попробуйте снова.", "error");
        })
        .finally(function () {
          if (mapLoader) mapLoader.style.display = "none";
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
            center: [53.20066, 44.99965], // Центр Пензы
            zoom: 12,
            controls: ["zoomControl", "fullscreenControl"]
          }, {
            suppressMapOpenBlock: true
          });

          pointsCollection = new ymaps.GeoObjectCollection();
          yandexMap.geoObjects.add(pointsCollection);
          mapInitialized = true;

          // Подгрузка точек при перемещении / зуме карты пользователем (скролл/листание)
          yandexMap.events.add("boundschange", function () {
            if (isProgrammaticMove) return;
            clearTimeout(boundsDebounceTimer);
            boundsDebounceTimer = setTimeout(function () {
              loadPointsByBounds();
            }, 300);
          });

          // Определение города пользователя по геолокации
          if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
              function (pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                setProgrammaticMove();
                yandexMap.setCenter([lat, lng], 12, { checkZoomRange: true });
                // Reverse geocode to find city name
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
                // Geolocation denied or unavailable, use default
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

    // Делегирование клика по кнопке «Выбрать эту точку» в балуне Яндекс.Карты
    if (fivepostMapContainer) {
      fivepostMapContainer.addEventListener("click", function (e) {
        const btn = e.target.closest(".bf-balloon-select-btn");
        if (btn) {
          const pid = btn.getAttribute("data-point-id");
          const pt = currentPoints.find(function (p) { return String(p.id) === String(pid); });
          if (pt) {
            handleSelect5PostPoint(pt);
            if (yandexMap && yandexMap.balloon) {
              yandexMap.balloon.close();
            }
          }
        }
      });
    }

    // Поиск по городу и улице
    let searchDebounceTimer = null;
    if (searchInput) {
      searchInput.addEventListener("input", function () {
        const val = this.value.trim();
        if (clearSearchBtn) {
          clearSearchBtn.style.display = val ? "block" : "none";
        }
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(function () {
          // Ищем по городу или улице без мерцания загрузчика
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

    // Кнопка «Изменить» выбранный пункт
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

    // Telegram-маска: автодобавление @
    if (tgInput) {
      tgInput.addEventListener("blur", function () {
        let v = this.value.trim();
        if (v && v[0] !== "@") {
          this.value = "@" + v;
        }
      });
    }

    // Телефонная маска +7 (999) 000-00-00
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

    // Открытие модального окна
    function openModal() {
      const state = getProductState();
      if (!state.gender) {
        flagGenderError();
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
        submitBtn.textContent = "Оплатить " + state.price + " онлайн";
      }

      modal.classList.add("is-open");
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("no-scroll");

      // Инициализируем или подгоняем интерактивную Яндекс.Карту
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
      statusMsg.textContent = msg;
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
            Спасибо! Платёж через <strong>CloudPayments</strong> успешно проведён. Мы сформировали заказ для отправки через <strong>5Post</strong>.
          </p>
          <div class="ozon-success-summary">
            <div><span>Товар:</span> ${order.productName} (${order.gender}, размер ${order.size})</div>
            <div><span>Сумма:</span> <strong>${order.price}</strong> <span style="color:#10b981; font-weight:600;">(Оплачено)</span></div>
            ${order.fio ? `<div><span>ФИО получателя:</span> <strong>${escapeHtml(order.fio)}</strong></div>` : ''}
            <div><span>Telegram:</span> <strong>${order.tgUsername}</strong></div>
            <div><span>Телефон:</span> ${order.phone}</div>
            ${order.fivepostPointAddress ? `<div><span>Доставка 5Post:</span> <strong>${order.fivepostPointAddress}</strong> (${pointTypeRu})</div>` : ''}
            ${order.transactionId ? `<div><span>ID транзакции:</span> #${order.transactionId}</div>` : ''}
          </div>
          <p class="ozon-sms-note">
            Электронный кассовый чек отправлен. По прибытии заказа в постамат/кассу 5Post вам поступит SMS с кодом получения.
          </p>
          <button type="button" class="btn-primary btn-block" id="orderDoneBtn" style="margin-top:12px;">Отлично</button>
        </div>
      `;

      document.getElementById("orderDoneBtn")?.addEventListener("click", closeModal);
    }

    // Обработка отправки формы и запуск оплаты через CloudPayments
    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();

        const fio = (fioInput?.value || "").trim();
        let tg = (tgInput?.value || "").trim();
        const phone = (phoneInput?.value || "").trim();

        if (!fio || fio.length < 3) {
          showStatus("Укажите ваше ФИО (Фамилию, Имя и Отчество)", "error");
          fioInput?.focus();
          return;
        }

        const fioWords = fio.split(/\s+/).filter(Boolean);
        if (fioWords.length < 2) {
          showStatus("Укажите как минимум Имя и Фамилию для получения посылки в 5Post", "error");
          fioInput?.focus();
          return;
        }

        if (!tg) {
          showStatus("Укажите ваш @username в Telegram", "error");
          tgInput?.focus();
          return;
        }
        if (tg[0] !== "@") {
          tg = "@" + tg;
          if (tgInput) tgInput.value = tg;
        }
        if (tg.length < 2) {
          showStatus("Введите корректный ник в Telegram (например, @username)", "error");
          tgInput?.focus();
          return;
        }

        if (!phone || phone.length < 16) {
          showStatus("Введите полный номер телефона (+7 ...)", "error");
          phoneInput?.focus();
          return;
        }

        // Проверка выбора точки 5Post
        const pointId = (fivepostIdInput?.value || "").trim();
        if (!pointId) {
          showStatus("Пожалуйста, выберите удобный магазин или постамат 5Post на карте", "error");
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

        const state = getProductState();
        const numPrice = parseFloat(state.price.replace(/[^\d.]/g, "")) || 3200;
        const orderId = "BF-" + Math.random().toString(36).substring(2, 8).toUpperCase();

        const orderPayload = {
          orderId: orderId,
          productId: state.id,
          productName: state.name,
          price: state.price,
          gender: state.gender,
          size: state.size,
          fio: fio,
          tgUsername: tg,
          phone: phone,
          fivepostPointId: pointId,
          fivepostPointName: (fivepostNameInput?.value || "").trim(),
          fivepostPointAddress: (fivepostAddrInput?.value || "").trim(),
          fivepostPointType: (fivepostTypeInput?.value || "").trim(),
          fivepostPointDetails: (fivepostDetailsInput?.value || "").trim(),
          source: "Онлайн-оплата (5Post + CloudPayments)"
        };

        if (typeof cp === "undefined" || !cp.CloudPayments) {
          showStatus("Платёжная система CloudPayments загружается. Повторите через 2 секунды...", "error");
          return;
        }

        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = "Открытие оплаты…";
        }
        showStatus("Подключение к безопасному шлюзу CloudPayments...", "info");

        const widget = new cp.CloudPayments();
        const publicId = "pk_899737976b42eb267df76db653d6e";
        const cleanPhone = "+" + phone.replace(/\D/g, "");

        const paymentOptions = {
          publicTerminalId: publicId,
          publicId: publicId,
          description: "Оплата заказа BOYFORGE: " + state.name + " (" + state.gender + ", " + state.size + ")",
          amount: numPrice,
          currency: "RUB",
          culture: "ru-RU",
          paymentSchema: "Single",
          skin: "modern",
          accountId: tg,
          externalId: orderId,
          invoiceId: orderId,
          sbpSupport: true,
          tinkoffPaySupport: true,
          sberPaySupport: true,
          mirPaySupport: true,
          applePaySupport: true,
          googlePaySupport: true,
          restrictedPaymentMethods: [],
          payer: {
            name: fio,
            phone: cleanPhone,
            Phone: cleanPhone
          },
          userInfo: {
            name: fio,
            accountId: tg,
            phone: cleanPhone
          },
          metadata: orderPayload,
          data: orderPayload
        };

        function handleSuccess(options) {
          showStatus("Оплата принята! Сохраняем заказ...", "success");

          const txId = (options && (options.transactionId || options.TransactionId || (options.data && options.data.transactionId)))
            ? String(options.transactionId || options.TransactionId || options.data.transactionId)
            : "";

          const finalPayload = Object.assign({}, orderPayload, {
            paymentStatus: "paid",
            transactionId: txId
          });

          fetch("/api/order.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(finalPayload)
          })
            .then(res => res.json())
            .then(data => {
              if (data && data.fivepost && data.fivepost.barcode) {
                finalPayload.fivepostBarcode = data.fivepost.barcode;
                finalPayload.fivepostOrderId = data.fivepost.orderId;
              }
              showSuccessScreen(orderId, finalPayload);
            })
            .catch(function () {
              showSuccessScreen(orderId, finalPayload);
            });
        }

        function handleFail(reason) {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Оплатить " + state.price + " онлайн";
          }
          showStatus("Оплата не была завершена" + (reason ? ": " + reason : ". Попробуйте снова или выберите другой способ."), "error");
        }

        function handleComplete() {
          setTimeout(function () {
            if (submitBtn && submitBtn.disabled && !modal.querySelector(".ozon-success-box, .order-success-box")) {
              submitBtn.disabled = false;
              submitBtn.textContent = "Оплатить " + state.price + " онлайн";
              showStatus("", null);
            }
          }, 800);
        }

        if (typeof widget.start === "function") {
          widget.start(paymentOptions, {
            onSuccess: handleSuccess,
            onFail: handleFail,
            onComplete: handleComplete
          }).then(function (result) {
            if (result && (result.status === "success" || result.type === "payment")) {
              handleSuccess(result);
            } else if (result && result.type === "cancel") {
              handleComplete();
            } else if (result && result.status === "fail") {
              handleFail(result.message || "");
            }
          }).catch(function () {
            widget.pay("charge", paymentOptions, {
              onSuccess: handleSuccess,
              onFail: handleFail,
              onComplete: handleComplete
            });
          });
        } else {
          widget.pay("charge", paymentOptions, {
            onSuccess: handleSuccess,
            onFail: handleFail,
            onComplete: handleComplete
          });
        }
      });
    }

  });
})();
