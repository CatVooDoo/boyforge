"use strict";

/**
 * ozon-delivery.js — Оптимизированный модуль OZON Доставки с реальным поиском ПВЗ по всей России
 * BOYFORGE
 */
(function () {
  "use strict";

  // Стартовая база популярных городов для мгновенной загрузки
  const INITIAL_CITIES = {
    "Москва": { lat: 55.7558, lng: 37.6173, zoom: 12 },
    "Санкт-Петербург": { lat: 59.9343, lng: 30.3351, zoom: 12 },
    "Екатеринбург": { lat: 56.8389, lng: 60.6057, zoom: 12 },
    "Казань": { lat: 55.7887, lng: 49.1221, zoom: 12 },
    "Новосибирск": { lat: 55.0084, lng: 82.9357, zoom: 12 },
    "Краснодар": { lat: 45.0355, lng: 38.9753, zoom: 12 },
    "Нижний Новгород": { lat: 56.3269, lng: 44.0059, zoom: 12 },
    "Самара": { lat: 53.1959, lng: 50.1002, zoom: 12 },
    "Ростов-на-Дону": { lat: 47.2225, lng: 39.7187, zoom: 12 },
    "Уфа": { lat: 54.7388, lng: 55.9721, zoom: 12 },
    "Воронеж": { lat: 51.6608, lng: 39.2003, zoom: 12 },
    "Челябинск": { lat: 55.1644, lng: 61.4368, zoom: 12 },
    "Сочи": { lat: 43.5855, lng: 39.7231, zoom: 12 },
    "Пермь": { lat: 58.0105, lng: 56.2502, zoom: 12 },
    "Волгоград": { lat: 48.7080, lng: 44.5133, zoom: 12 }
  };

  document.addEventListener("DOMContentLoaded", function () {
    const ozonBtn = document.getElementById("ozonOrderBtn");
    const modal = document.getElementById("ozonModal");
    if (!ozonBtn || !modal) return;

    const closeBtn = document.getElementById("ozonModalClose");
    const form = document.getElementById("ozonOrderForm");
    const cityChips = document.querySelectorAll(".ozon-city-chip");
    const citySearchInput = document.getElementById("ozonCitySearch");
    const pvzListEl = document.getElementById("ozonPvzList");
    const selectedPvzBox = document.getElementById("ozonSelectedPvzBox");
    const selectedPvzText = document.getElementById("ozonSelectedPvzText");
    const selectedPvzInput = document.getElementById("ozonSelectedPvzInput");
    const selectedPvzIdInput = document.getElementById("ozonSelectedPvzIdInput");
    const submitBtn = document.getElementById("ozonConfirmBtn");
    const statusMsg = document.getElementById("ozonFormStatus");
    const tabMapBtn = document.getElementById("ozonTabMap");
    const tabListBtn = document.getElementById("ozonTabList");
    const mapWrap = document.getElementById("ozonMapWrap");
    const listWrap = document.getElementById("ozonListWrap");
    const geoBtn = document.getElementById("ozonGeoBtn");

    let currentCity = "Москва";
    let selectedPoint = null;
    let yMap = null;
    let yClusterer = null;
    let currentPoints = [];
    let isSearching = false;
    let searchDebounceTimer = null;
    let suggestListEl = null;

    // Хеш-функция для стабильных ID
    function hashString(str) {
      let hash = 0;
      for (let i = 0; i < str.length; i++) {
        hash = (hash << 5) - hash + str.charCodeAt(i);
        hash |= 0;
      }
      return Math.abs(hash).toString(36).toUpperCase().slice(0, 6);
    }

    // Получить выбранный пол и размер товара
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
      setTimeout(() => gendersWrap.classList.remove("gender-error"), 2500);
    }

    // Telegram ник (@username) и телефонная маска
    const tgInput = document.getElementById("ozonTg") || document.getElementById("ozonName");
    if (tgInput) {
      tgInput.addEventListener("blur", function () {
        let v = this.value.trim();
        if (v && v[0] !== "@") {
          this.value = "@" + v;
        }
      });
    }

    const phoneInput = document.getElementById("ozonPhone");
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

    // Открытие модалки
    function openModal() {
      const state = getProductState();
      if (!state.gender) {
        flagGenderError();
        return;
      }

      document.getElementById("ozonModalProdName").textContent = state.name;
      document.getElementById("ozonModalProdPrice").textContent = state.price;
      document.getElementById("ozonModalProdGender").textContent = state.gender;
      document.getElementById("ozonModalProdSize").textContent = state.size || "M";
      const thumb = document.getElementById("ozonModalProdImg");
      if (thumb && state.img) thumb.src = state.img;

      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = "Оплатить " + state.price + " онлайн";
      }

      modal.classList.add("is-open");
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("no-scroll");

      // Инициализация карты
      setTimeout(initYandexMap, 200);
    }

    function closeModal() {
      modal.classList.remove("is-open");
      modal.setAttribute("aria-hidden", "true");
      document.body.classList.remove("no-scroll");
      if (suggestListEl) suggestListEl.style.display = "none";
    }

    ozonBtn.addEventListener("click", openModal);
    if (closeBtn) closeBtn.addEventListener("click", closeModal);

    modal.addEventListener("click", function (e) {
      if (e.target === modal) closeModal();
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modal.classList.contains("is-open")) {
        closeModal();
      }
    });

    // Вкладки: Карта / Список
    if (tabMapBtn && tabListBtn) {
      tabMapBtn.addEventListener("click", () => {
        tabMapBtn.classList.add("active");
        tabListBtn.classList.remove("active");
        mapWrap.style.display = "block";
        listWrap.style.display = "none";
        if (yMap) yMap.container.fitToViewport();
      });
      tabListBtn.addEventListener("click", () => {
        tabListBtn.classList.add("active");
        tabMapBtn.classList.remove("active");
        mapWrap.style.display = "none";
        listWrap.style.display = "block";
      });
    }

    // Глобальная функция выбора ПВЗ (для кликов из балунов)
    window.selectOzonPvz = function (pointId) {
      const point = currentPoints.find(p => p.id === pointId);
      if (point) applyPointSelection(point);
    };

    function applyPointSelection(point) {
      selectedPoint = point;
      selectedPvzInput.value = point.address;
      selectedPvzIdInput.value = point.id;
      selectedPvzText.innerHTML = `<strong>Выбран ПВЗ OZON:</strong> ${point.address} <span class="ozon-point-badge">#${point.id}</span><br><small class="text-muted">${point.metro ? point.metro + ' · ' : ''}${point.hours} (доставка: ${point.days || '2–3 дня'})</small>`;
      selectedPvzBox.style.display = "block";

      document.querySelectorAll(".ozon-pvz-card").forEach(c => {
        c.classList.toggle("selected", c.getAttribute("data-id") === point.id);
      });

      if (yMap && point.lat && point.lng) {
        yMap.panTo([point.lat, point.lng], { flying: true, duration: 500 });
      }
    }

    // Создание фирменной метки OZON
    function createOzonPlacemark(p) {
      const balloonHtml = `
        <div class="ozon-ymap-balloon">
          <div class="ozon-balloon-header">
            <span class="ozon-logo-tag">OZON</span>
            <strong>ПВЗ #${p.id}</strong>
          </div>
          <div class="ozon-balloon-address">${p.address}</div>
          <div class="ozon-balloon-meta">${p.metro ? '<span class="ozon-balloon-metro">' + p.metro + '</span><br>' : ''}${p.hours}</div>
          <div class="ozon-balloon-delivery">Срок доставки: <strong>${p.days || '2–3 дня'}</strong></div>
          <button type="button" class="ozon-balloon-select-btn" onclick="selectOzonPvz('${p.id}')">
            Выбрать этот пункт
          </button>
        </div>
      `;

      return new ymaps.Placemark([p.lat, p.lng], {
        balloonContentBody: balloonHtml,
        hintContent: "ПВЗ OZON: " + p.address
      }, {
        preset: "islands#blueDotIcon",
        iconColor: "#005BFF"
      });
    }

    // Обновление меток на карте и списка
    function updatePointsView(points, autoSelectFirst) {
      currentPoints = points;

      if (yClusterer) {
        yClusterer.removeAll();
        const marks = points.map(p => createOzonPlacemark(p));
        yClusterer.add(marks);
      }

      // Обновляем список в табе "Списком"
      if (pvzListEl) {
        if (points.length === 0) {
          pvzListEl.innerHTML = `
            <div class="ozon-no-points">
              <p>В этой области не найдено точек OZON. Попробуйте отдалить карту или ввести другой адрес в поиск.</p>
            </div>
          `;
        } else {
          pvzListEl.innerHTML = points.map(p => `
            <div class="ozon-pvz-card ${selectedPoint && selectedPoint.id === p.id ? 'selected' : ''}" data-id="${p.id}">
              <div class="ozon-pvz-head">
                <span class="ozon-pvz-title">ПВЗ OZON · #${p.id}</span>
                <span class="ozon-pvz-time">${p.days || '2–3 дня'}</span>
              </div>
              <div class="ozon-pvz-address">${p.address}</div>
              <div class="ozon-pvz-details">${p.metro ? '<span class="ozon-metro">' + p.metro + '</span>' : ''} <span>${p.hours}</span></div>
              <button type="button" class="ozon-select-pvz-btn" onclick="selectOzonPvz('${p.id}')">Выбрать этот ПВЗ</button>
            </div>
          `).join("");
        }
      }

      if (autoSelectFirst && points.length > 0 && !selectedPoint) {
        applyPointSelection(points[0]);
      }
    }

    // Запрос реальных актуальных ПВЗ OZON в текущей области карты через Яндекс.Поиск
    function searchOzonInBounds() {
      if (typeof ymaps === "undefined" || !yMap || isSearching) return;
      isSearching = true;

      const bounds = yMap.getBounds();
      const center = yMap.getCenter();

      // Выполняем поиск организаций "OZON пункт выдачи" в видимой области
      ymaps.search("OZON пункт выдачи", {
        boundedBy: bounds,
        results: 40,
        type: "biz"
      }).then(function (res) {
        isSearching = false;
        const found = [];

        res.geoObjects.each(function (geoObject) {
          const coords = geoObject.geometry.getCoordinates();
          const props = geoObject.properties.getAll();
          const address = props.text || props.description || props.name || "ПВЗ OZON";
          const meta = (props.CompanyMetaData && props.CompanyMetaData.Hours) ? props.CompanyMetaData.Hours.text : "10:00–21:00 ежедневно";

          found.push({
            id: hashString(address),
            address: address,
            metro: "",
            hours: meta,
            days: "2–3 дня",
            lat: coords[0],
            lng: coords[1]
          });
        });

        if (found.length > 0) {
          updatePointsView(found, false);
        } else {
          // Если поиск по организациям не вернул данных (например, маленький поселок),
          // геокодируем центр карты и предлагаем доставку OZON по адресу
          ymaps.geocode(center).then(function (gRes) {
            const first = gRes.geoObjects.get(0);
            if (first) {
              const fallbackPoint = {
                id: hashString(first.getAddressLine()),
                address: first.getAddressLine() + " (ПВЗ OZON)",
                metro: "",
                hours: "10:00–21:00 ежедневно",
                days: "3–4 дня",
                lat: center[0],
                lng: center[1]
              };
              updatePointsView([fallbackPoint], false);
            }
          });
        }
      }).catch(function () {
        isSearching = false;
      });
    }

    // Инициализация Яндекс.Карт
    function initYandexMap() {
      if (typeof ymaps === "undefined") {
        console.warn("Yandex Maps library loading...");
        return;
      }

      ymaps.ready(function () {
        const mapEl = document.getElementById("ozonMap");
        if (!mapEl) return;

        const cityData = INITIAL_CITIES[currentCity] || INITIAL_CITIES["Москва"];

        if (!yMap) {
          yMap = new ymaps.Map("ozonMap", {
            center: [cityData.lat, cityData.lng],
            zoom: cityData.zoom,
            controls: ["zoomControl", "fullscreenControl"]
          }, {
            suppressMapOpenBlock: true
          });

          // Кластеризатор
          yClusterer = new ymaps.Clusterer({
            preset: "islands#blueClusterIcons",
            groupByCoordinates: false,
            clusterDisableClickZoom: false,
            clusterHideIconOnBalloonOpen: false,
            geoObjectHideIconOnBalloonOpen: false
          });

          yMap.geoObjects.add(yClusterer);

          // При перемещении или зуме карты — автоматически подгружаем актуальные ПВЗ в этой области!
          yMap.events.add("boundschange", function () {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(searchOzonInBounds, 400);
          });

          // Клик в любую точку карты
          yMap.events.add("click", function (e) {
            const coords = e.get("coords");
            ymaps.geocode(coords).then(function (res) {
              const first = res.geoObjects.get(0);
              if (first) {
                const addr = first.getAddressLine();
                const customPoint = {
                  id: hashString(addr),
                  address: addr,
                  metro: "",
                  hours: "10:00–21:00 ежедневно",
                  days: "2–4 дня",
                  lat: coords[0],
                  lng: coords[1]
                };
                applyPointSelection(customPoint);
                const mark = createOzonPlacemark(customPoint);
                yMap.geoObjects.add(mark);
                mark.balloon.open();
              }
            });
          });

          // Первый поиск ПВЗ при загрузке
          setTimeout(searchOzonInBounds, 300);
        } else {
          yMap.setCenter([cityData.lat, cityData.lng], cityData.zoom);
          yMap.container.fitToViewport();
          setTimeout(searchOzonInBounds, 200);
        }
      });
    }

    // Переход к городу
    function goToCity(cityName) {
      currentCity = cityName;
      const data = INITIAL_CITIES[cityName];
      if (data && yMap) {
        yMap.setCenter([data.lat, data.lng], data.zoom, { flying: true, duration: 600 });
        setTimeout(searchOzonInBounds, 700);
      } else if (typeof ymaps !== "undefined" && yMap) {
        ymaps.geocode("город " + cityName, { results: 1 }).then(function (res) {
          const first = res.geoObjects.get(0);
          if (first) {
            const coords = first.geometry.getCoordinates();
            yMap.setCenter(coords, 12, { flying: true, duration: 600 });
            setTimeout(searchOzonInBounds, 700);
          }
        });
      }
    }

    // Чипы городов
    cityChips.forEach(chip => {
      chip.addEventListener("click", function () {
        cityChips.forEach(c => c.classList.remove("active"));
        chip.classList.add("active");
        const city = chip.getAttribute("data-city");
        goToCity(city);
      });
    });

    // Умный поиск любого города, улицы или адреса с выпадающим списком подсказок
    if (citySearchInput) {
      // Создаем выпадающий блок подсказок под полем ввода
      suggestListEl = document.createElement("div");
      suggestListEl.className = "ozon-suggest-dropdown";
      citySearchInput.parentNode.style.position = "relative";
      citySearchInput.parentNode.appendChild(suggestListEl);

      let suggestTimer = null;

      citySearchInput.addEventListener("input", function () {
        clearTimeout(suggestTimer);
        const query = this.value.trim();

        if (query.length < 2) {
          suggestListEl.style.display = "none";
          return;
        }

        suggestTimer = setTimeout(function () {
          if (typeof ymaps === "undefined") return;

          ymaps.suggest(query, { results: 5 }).then(function (items) {
            if (!items || items.length === 0) {
              suggestListEl.style.display = "none";
              return;
            }

            suggestListEl.innerHTML = items.map(item => `
              <div class="ozon-suggest-item" data-value="${item.value}">
                
                <span class="ozon-suggest-text">${item.displayName}</span>
              </div>
            `).join("");

            suggestListEl.style.display = "block";

            suggestListEl.querySelectorAll(".ozon-suggest-item").forEach(el => {
              el.addEventListener("click", function () {
                const val = this.getAttribute("data-value");
                citySearchInput.value = val;
                suggestListEl.style.display = "none";
                executeSearch(val);
              });
            });
          });
        }, 250);
      });

      // Поиск при нажатии Enter
      citySearchInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          suggestListEl.style.display = "none";
          const query = this.value.trim();
          if (query) executeSearch(query);
        }
      });

      // Скрытие подсказок при клике вне
      document.addEventListener("click", function (e) {
        if (!citySearchInput.contains(e.target) && !suggestListEl.contains(e.target)) {
          suggestListEl.style.display = "none";
        }
      });
    }

    // Выполнение перехода к найденному адресу и поиск ПВЗ
    function executeSearch(query) {
      if (typeof ymaps === "undefined" || !yMap) return;

      ymaps.geocode(query, { results: 1 }).then(function (res) {
        const first = res.geoObjects.get(0);
        if (first) {
          const coords = first.geometry.getCoordinates();
          // Приближаем карту к найденному адресу
          yMap.setCenter(coords, 14, { flying: true, duration: 600 });
          // Запускаем поиск реальных ПВЗ OZON в этом районе
          setTimeout(searchOzonInBounds, 700);
        } else {
          alert("Адрес не найден. Попробуйте уточнить запрос.");
        }
      });
    }

    // Кнопка «Рядом» (Определение геопозиции)
    if (geoBtn) {
      geoBtn.addEventListener("click", function () {
        if (typeof ymaps === "undefined" || !yMap) return;
        geoBtn.classList.add("loading");
        geoBtn.textContent = "Ищем…";

        ymaps.geolocation.get({
          provider: "browser",
          mapStateAutoApply: true
        }).then(function (result) {
          geoBtn.classList.remove("loading");
          geoBtn.textContent = "Рядом";
          const userCoords = result.geoObjects.position;
          yMap.setCenter(userCoords, 14, { flying: true, duration: 600 });
          setTimeout(searchOzonInBounds, 700);
        }).catch(function () {
          geoBtn.classList.remove("loading");
          geoBtn.textContent = "Рядом";
          alert("Не удалось определить местоположение. Разрешите доступ к геопозиции в браузере или введите адрес в поиск.");
        });
      });
    }

    // Отправка формы и запуск оплаты через CloudPayments
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      const tgInput = document.getElementById("ozonTg") || document.getElementById("ozonName");
      let tg = (tgInput?.value || "").trim();
      const phone = (document.getElementById("ozonPhone")?.value || "").trim();
      const pvzAddress = selectedPvzInput.value.trim();
      const pvzId = selectedPvzIdInput.value.trim();

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
        document.getElementById("ozonPhone")?.focus();
        return;
      }

      // ПВЗ опционален — если не выбран, согласовываем позже
      const effectivePvz = pvzAddress || "Не выбран (согласовать в Telegram)";

      const state = getProductState();
      const numPrice = parseFloat(state.price.replace(/[^\d.]/g, "")) || 3200;
      const orderId = "BF-OZON-" + Math.random().toString(36).substring(2, 8).toUpperCase();

      const orderPayload = {
        orderId: orderId,
        productId: state.id,
        productName: state.name,
        price: state.price,
        gender: state.gender,
        size: state.size,
        tgUsername: tg,
        recipientName: tg,
        phone: phone,
        city: currentCity,
        pvzId: pvzId,
        pvzAddress: effectivePvz
      };

      // Проверка доступности виджета CloudPayments
      if (typeof cp === "undefined" || !cp.CloudPayments) {
        showStatus("Платёжная система CloudPayments загружается. Повторите через 2 секунды...", "error");
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = "Открытие оплаты…";
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
          phone: cleanPhone,
          Phone: cleanPhone
        },
        userInfo: {
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

        fetch("api/ozon-order.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(finalPayload)
        })
          .then(res => res.json())
          .catch(() => ({}))
          .finally(() => {
            showSuccessScreen(orderId, finalPayload);
          });
      }

      function handleFail(reason) {
        submitBtn.disabled = false;
        submitBtn.textContent = "Оплатить " + state.price + " онлайн";
        showStatus("Оплата не была завершена" + (reason ? ": " + reason : ". Попробуйте снова или выберите другой способ."), "error");
      }

      function handleComplete() {
        setTimeout(function () {
          if (submitBtn && submitBtn.disabled && !document.querySelector(".ozon-success-box")) {
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

    function showStatus(msg, type) {
      if (!statusMsg) return;
      statusMsg.textContent = msg;
      statusMsg.className = "ozon-status-msg " + (type ? "is-" + type : "");
    }

    function showSuccessScreen(orderId, order) {
      const modalBody = document.querySelector(".ozon-modal-body");
      if (!modalBody) return;

      modalBody.innerHTML = `
        <div class="ozon-success-box">
          <div class="ozon-success-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
          <h3>Заказ успешно оплачен!</h3>
          <div class="ozon-order-id">Номер заказа: <strong>${orderId}</strong></div>
          <p class="ozon-success-desc">
            Спасибо! Платёж через <strong>CloudPayments</strong> успешно проведён. Мы уже передали данные менеджеру для сборки заказа.
          </p>
          <div class="ozon-success-summary">
            <div><span>Товар:</span> ${order.productName} (${order.gender}, размер ${order.size})</div>
            <div><span>Сумма:</span> <strong>${order.price}</strong> <span style="color:#10b981; font-weight:600;">(Оплачено)</span></div>
            <div><span>Telegram:</span> <strong>${order.tgUsername || order.recipientName}</strong></div>
            <div><span>Телефон:</span> ${order.phone}</div>
            <div><span>Пункт выдачи OZON:</span> ${order.pvzAddress}</div>
            ${order.transactionId ? `<div><span>ID транзакции:</span> #${order.transactionId}</div>` : ''}
          </div>
          <p class="ozon-sms-note">
            Электронный кассовый чек отправлен. Наш менеджер свяжется с вами в Telegram <strong>${order.tgUsername || ''}</strong> для подтверждения отправки.
          </p>
          <button type="button" class="btn-primary btn-block" id="ozonDoneBtn">Отлично</button>
        </div>
      `;

      document.getElementById("ozonDoneBtn")?.addEventListener("click", closeModal);
    }

  });
})();
