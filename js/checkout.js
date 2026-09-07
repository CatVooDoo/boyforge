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
    const tgInput = document.getElementById("orderTg") || document.getElementById("ozonTg") || document.getElementById("ozonName");
    const phoneInput = document.getElementById("orderPhone") || document.getElementById("ozonPhone");
    const submitBtn = document.getElementById("orderConfirmBtn") || document.getElementById("ozonConfirmBtn");
    const statusMsg = document.getElementById("orderFormStatus") || document.getElementById("ozonFormStatus");

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

      modalBody.innerHTML = `
        <div class="ozon-success-box order-success-box">
          <div class="ozon-success-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
          <h3>Заказ успешно оплачен!</h3>
          <div class="ozon-order-id">Номер заказа: <strong>${orderId}</strong></div>
          <p class="ozon-success-desc">
            Спасибо! Платёж через <strong>CloudPayments</strong> успешно проведён. Мы передали данные менеджеру для сборки заказа.
          </p>
          <div class="ozon-success-summary">
            <div><span>Товар:</span> ${order.productName} (${order.gender}, размер ${order.size})</div>
            <div><span>Сумма:</span> <strong>${order.price}</strong> <span style="color:#10b981; font-weight:600;">(Оплачено)</span></div>
            <div><span>Telegram:</span> <strong>${order.tgUsername}</strong></div>
            <div><span>Телефон:</span> ${order.phone}</div>
            ${order.transactionId ? `<div><span>ID транзакции:</span> #${order.transactionId}</div>` : ''}
          </div>
          <p class="ozon-sms-note">
            Электронный кассовый чек отправлен. Наш менеджер свяжется с вами в Telegram <strong>${order.tgUsername}</strong> для подтверждения отправки.
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

        let tg = (tgInput?.value || "").trim();
        const phone = (phoneInput?.value || "").trim();

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
          tgUsername: tg,
          phone: phone,
          source: "Онлайн-оплата (CloudPayments)"
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

          fetch("api/order.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(finalPayload)
          })
            .then(res => res.json())
            .catch(function () { return {}; })
            .finally(function () {
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
