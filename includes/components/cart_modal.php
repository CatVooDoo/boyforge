<?php
declare(strict_types=1);
require_once __DIR__ . '/../delivery_availability.php';
$bfYandexApiKey = function_exists('env_get') ? (env_get('YANDEX_MAPS_API_KEY') ?: '') : '';
?>
  <!-- ===== БОКОВАЯ ПАНЕЛЬ КОРЗИНЫ ===== -->
  <div class="cart-drawer-overlay" id="cartDrawerOverlay" hidden></div>
  <aside class="cart-drawer" id="cartDrawer" aria-hidden="true" aria-label="Корзина покупок" role="dialog" aria-modal="true">
    <div class="cart-drawer-header">
      <div class="cart-drawer-title-wrap">
        <h3 class="cart-drawer-title">Корзина</h3>
        <span class="cart-drawer-count" id="cartDrawerCount">0</span>
      </div>
      <div class="cart-drawer-head-actions">
        <button type="button" class="cart-clear-btn" id="cartClearBtn" hidden>Очистить</button>
        <button type="button" class="cart-drawer-close" id="cartDrawerClose" aria-label="Закрыть корзину">&times;</button>
      </div>
    </div>

    <div class="cart-drawer-body" id="cartDrawerBody">
      <div class="cart-empty-state" id="cartEmptyState">
        <div class="cart-empty-icon" aria-hidden="true">
          <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <path d="M16 10a4 4 0 0 1-8 0"></path>
          </svg>
        </div>
        <div class="cart-empty-title">В корзине пока пусто</div>
        <p class="cart-empty-sub">Выберите модель и размер в каталоге — доставим через 5Post по всей России.</p>
        <a href="/catalog" class="btn-primary cart-empty-cta">Перейти в каталог</a>
      </div>

      <div class="cart-items-list" id="cartItemsList" hidden></div>
    </div>

    <div class="cart-drawer-footer" id="cartDrawerFooter" hidden>
      <div class="cart-summary-row">
        <span id="cartSubtotalLabel">Товары (0 шт.)</span>
        <strong id="cartSubtotalDisplay">0 ₽</strong>
      </div>
      <p class="cart-drawer-error" id="cartDrawerError" hidden></p>
      <button type="button" class="btn-primary btn-block cart-checkout-btn" id="cartCheckoutBtn">
        Перейти к оформлению
      </button>
    </div>
  </aside>

  <!-- ===== УВЕДОМЛЕНИЕ О ДОБАВЛЕНИИ В КОРЗИНУ ===== -->
  <div class="cart-toast" id="cartToast" role="status" aria-live="polite" hidden>
    <div class="cart-toast-body">
      <span class="cart-toast-check" aria-hidden="true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
      </span>
      <div class="cart-toast-text" id="cartToastText">Товар добавлен в корзину</div>
    </div>
    <button type="button" class="cart-toast-open-btn" id="cartToastOpenBtn">Открыть</button>
  </div>

  <!-- ===== ЕДИНОЕ МОДАЛЬНОЕ ОКНО ОФОРМЛЕНИЯ ЗАКАЗА ===== -->
  <div class="order-modal ozon-modal" id="orderModal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="order-modal-dialog ozon-modal-dialog">
      <div class="order-modal-header ozon-modal-header">
        <h3 class="order-modal-title ozon-modal-title" id="orderModalTitle">Оформление заказа</h3>
        <button type="button" class="order-modal-close ozon-modal-close" id="orderModalClose" aria-label="Закрыть">&times;</button>
      </div>

      <div class="order-modal-body ozon-modal-body" id="orderModalBody">
        <!-- Мини-превью одиночного товара («Купить сразу») -->
        <div class="ozon-prod-summary" id="orderModalSingleSummary">
          <img class="ozon-prod-thumb" id="orderModalProdImg" src="" alt="Товар">
          <div class="ozon-prod-info">
            <div class="ozon-prod-name" id="orderModalProdName">Товар BOYFORGE</div>
            <div class="ozon-prod-price" id="orderModalProdPrice">—</div>
            <div class="ozon-prod-tags">
              <span class="ozon-pill" id="orderModalProdGender" hidden></span>
              <span class="ozon-pill">Размер: <strong id="orderModalProdSize">M</strong></span>
            </div>
          </div>
        </div>

        <!-- Сводка товаров при оформлении из корзины -->
        <div class="checkout-cart-summary" id="orderModalCartSummary" hidden>
          <div class="checkout-cart-summary-head">
            <span id="orderModalCartCount">Состав заказа</span>
            <button type="button" class="checkout-edit-cart-btn" id="checkoutBackToCartBtn">Изменить корзину</button>
          </div>
          <div class="checkout-cart-items" id="orderModalCartItems"></div>
          <div class="checkout-cart-subtotal">
            <span>Сумма товаров:</span>
            <strong id="orderModalCartSubtotal">0 ₽</strong>
          </div>
        </div>

        <form id="orderForm" novalidate>
          <div class="order-details-sections" id="orderDetailsSections">
            <section class="checkout-promo" aria-label="Промокод">
              <label for="checkoutPromoCode">Есть промокод?</label>
              <div class="checkout-promo-controls">
                <input class="ozon-input" id="checkoutPromoCode" maxlength="32" placeholder="Введите промокод" autocomplete="off" autocapitalize="characters" spellcheck="false">
                <button class="checkout-promo-apply" type="button" id="checkoutPromoApply">Применить</button>
              </div>
              <div class="checkout-promo-feedback"><p id="checkoutPromoStatus" role="status" aria-live="polite"></p><button type="button" id="checkoutPromoRemove" hidden>Убрать</button></div>
              <div class="checkout-promo-breakdown" id="checkoutPromoBreakdown" hidden>
                <div><span>Товары без скидки</span><span id="checkoutPromoOriginal"></span></div>
                <div class="checkout-promo-saving"><span>Скидка на товары</span><span id="checkoutPromoDiscount"></span></div>
                <div><span>Доставка</span><span id="checkoutPromoDelivery"></span></div>
                <div class="checkout-promo-total"><strong>К оплате</strong><strong id="checkoutPromoTotal"></strong></div>
              </div>
            </section>
            <!-- 1. Контактные данные -->
            <div class="ozon-section">
              <div class="ozon-section-title">1. Контактные данные покупателя</div>
              <div class="ozon-field">
                <label for="orderFio">ФИО получателя <span style="color:#ef4444;">*</span></label>
                <input type="text" id="orderFio" class="ozon-input" placeholder="Иванов Иван Иванович" required autocomplete="name">
              </div>
              <div class="ozon-fields-grid">
                <div class="ozon-field">
                  <label for="orderEmail">Email <span style="color:#ef4444;">*</span></label>
                  <input type="email" id="orderEmail" class="ozon-input" placeholder="mail@example.com" required autocomplete="email">
                </div>
                <div class="ozon-field">
                  <label for="orderPhone">Номер телефона <span style="color:#ef4444;">*</span></label>
                  <input type="tel" id="orderPhone" class="ozon-input" placeholder="+7 (999) 000-00-00" required autocomplete="tel">
                </div>
              </div>
            </div>

            <!-- 2. Служба доставки и пункт выдачи -->
            <div class="ozon-section" style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
              <div style="display:flex; align-items:center; justify-content:space-between;">
                <div class="ozon-section-title">2. Доставка</div>
              </div>

              <div class="delivery-switch" role="group" aria-label="Служба доставки">
                <button type="button" class="delivery-option is-active" data-delivery-provider="fivepost" aria-pressed="true"><strong>5Post</strong><span>Пятёрочка / Перекрёсток</span></button>
                <?php if (CDEK_CHECKOUT_ENABLED): ?>
                <button type="button" class="delivery-option" data-delivery-provider="cdek" aria-pressed="false"><strong>СДЭК</strong><span>Пункты выдачи</span></button>
                <?php endif; ?>
              </div>
              <p id="deliveryQuote" class="delivery-quote" role="status" hidden></p>

              <!-- Скрытые поля для данных выбранной точки выдачи -->
              <input type="hidden" id="fivepostPointId" name="fivepostPointId" value="">
              <input type="hidden" id="fivepostPointName" name="fivepostPointName" value="">
              <input type="hidden" id="fivepostPointAddress" name="fivepostPointAddress" value="">
              <input type="hidden" id="fivepostPointType" name="fivepostPointType" value="">
              <input type="hidden" id="fivepostPointDetails" name="fivepostPointDetails" value="">

              <!-- Карточка уже выбранного пункта выдачи -->
              <div class="fivepost-selected-card" id="fivepostSelectedCard" style="display:none;">
                <div class="fivepost-selected-details">
                  <div class="fivepost-selected-header">
                    <strong class="fivepost-selected-name" id="fivepostPointNameDisplay">Пятёрочка</strong>
                  </div>
                  <div class="fivepost-selected-address" id="fivepostPointAddressDisplay">г. Пенза, ул. Примерная, д. 1</div>
                  <div class="fivepost-selected-extra" id="fivepostPointExtraDisplay">Выдача заказа 5Post</div>
                </div>
                <button type="button" class="fivepost-change-btn" id="fivepostChangeBtn">Изменить</button>
              </div>

              <!-- Блок карты и поиска по городам -->
              <div class="fivepost-map-wrapper" id="fivepostMapWrapper">
                <div class="bf-city-search-wrap">
                  <div class="bf-city-search-box">
                    <svg class="bf-search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="fivepostCitySearch" class="ozon-input bf-city-search-input" placeholder="Поиск по городу или улице (например, Пенза, Победы…)" autocomplete="off">
                    <button type="button" id="fivepostCityClear" class="bf-clear-btn" style="display:none;" aria-label="Очистить">&times;</button>
                  </div>
                </div>

                <div id="fivepostCustomMap" class="bf-custom-map">
                  <div class="fivepost-map-loader" id="fivepostMapLoader">
                    <div class="fivepost-spinner"></div>
                    <span>Загрузка карты и точек выдачи…</span>
                  </div>
                </div>

                <div id="deliveryPointList" class="delivery-point-list" aria-label="Пункты выдачи" hidden></div>
              </div>
            </div>
          </div>

          <!-- Контейнер встроенного виджета оплаты ЮKassa -->
          <div id="yookassaWidgetContainer" style="display:none; min-height:280px; margin-top:8px;"></div>

          <div id="orderSubmitWrap" style="margin-top:16px;">
            <p class="ozon-status-msg order-status-msg" id="orderFormStatus"></p>
            <button type="submit" class="btn-primary btn-block" id="orderConfirmBtn">
              Оплатить онлайн картой / СБП
            </button>

            <div class="order-agree-wrap" id="orderAgreeWrap">
              <label class="order-agree-label" for="orderPolicyAgree">
                <input type="checkbox" id="orderPolicyAgree" class="order-agree-checkbox" required>
                <span class="order-agree-text">
                  Нажимая кнопку, Вы соглашаетесь с <a href="/terms" target="_blank" rel="noopener">Правилами</a> и <a href="/policy" target="_blank" rel="noopener">политикой конфиденциальности</a> Компании.
                </span>
              </label>
            </div>

            <div style="display:flex; align-items:center; justify-content:center; gap:6px; margin-top:10px; font-size:11px; color:#9ca3af;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
              <span>Безопасная оплата через <strong>ЮKassa</strong> · Карты РФ, СБП, Mir Pay, SberPay, Tinkoff</span>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    window.BF_CONFIG = Object.assign(window.BF_CONFIG || {}, {
      yandexMapsApiKey: <?= json_encode($bfYandexApiKey, JSON_UNESCAPED_UNICODE) ?>
    });
  </script>
