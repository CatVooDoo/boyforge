// Automated contract tests for js/cart.js + js/checkout.js working together (multi-item basket, validation, CDEK qty quote, checkout & clear).
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

class ClassList {
  constructor() { this.set = new Set(); }
  add(...names) { names.forEach(n => this.set.add(n)); }
  remove(...names) { names.forEach(n => this.set.delete(n)); }
  toggle(name, force) {
    if (typeof force === 'boolean') {
      if (force) this.set.add(name); else this.set.delete(name);
      return force;
    }
    if (this.set.has(name)) { this.set.delete(name); return false; }
    this.set.add(name); return true;
  }
  contains(name) { return this.set.has(name); }
}

class Element {
  constructor(id = '') {
    this.id = id;
    this.value = '';
    this.textContent = '';
    this.innerHTML = '';
    this.hidden = false;
    this.disabled = false;
    this.checked = false;
    this.style = {};
    this.dataset = {};
    this.attrs = {};
    this.listeners = {};
    this.classList = new ClassList();
  }
  addEventListener(name, cb) {
    if (!this.listeners[name]) this.listeners[name] = [];
    this.listeners[name].push(cb);
  }
  trigger(name, ev = {}) {
    (this.listeners[name] || []).forEach(fn => fn(ev));
  }
  setAttribute(name, val) { this.attrs[name] = String(val); }
  getAttribute(name) { return this.attrs[name] ?? null; }
  scrollIntoView() {}
  focus() {}
  querySelector(sel) {
    if (sel === '.order-modal-body') return elements.orderModalBody;
    if (sel === '.order-details-sections') return elements.orderDetailsSections;
    return null;
  }
}

const elementIds = [
  // Cart header & drawer
  'cartToggleBtn', 'cartCountBadge', 'mobileMenuCartCount',
  'cartDrawerOverlay', 'cartDrawer', 'cartDrawerCount', 'cartClearBtn', 'cartDrawerClose',
  'cartEmptyState', 'cartItemsList', 'cartDrawerFooter', 'cartSubtotalLabel', 'cartSubtotalDisplay',
  'cartDrawerError', 'cartCheckoutBtn', 'cartToast', 'cartToastText', 'cartToastOpenBtn',
  // Unified orderModal (WITHOUT orderModalBtn — simulating catalog.php / index.php!)
  'orderModal', 'orderModalTitle', 'orderModalClose', 'orderModalBody',
  'orderModalSingleSummary', 'orderModalCartSummary', 'orderModalCartCount', 'orderModalCartItems', 'orderModalCartSubtotal',
  'checkoutBackToCartBtn', 'orderDetailsSections', 'yookassaWidgetContainer', 'orderAgreeWrap',
  'orderForm', 'orderFio', 'orderEmail', 'orderPhone', 'orderConfirmBtn', 'orderFormStatus', 'orderPolicyAgree',
  'fivepostPointId', 'fivepostPointName', 'fivepostPointAddress', 'fivepostPointType', 'fivepostPointDetails',
  'fivepostSelectedCard', 'fivepostMapWrapper', 'fivepostCustomMap', 'fivepostCitySearch', 'fivepostCityClear',
  'fivepostMapLoader', 'deliveryNotice', 'deliveryQuote', 'deliveryPointList',
  'fivepostPointNameDisplay', 'fivepostPointAddressDisplay', 'fivepostPointExtraDisplay'
];

const elements = Object.fromEntries(elementIds.map(id => [id, new Element(id)]));
elements.cartCountBadge.hidden = true;
elements.mobileMenuCartCount.hidden = true;

const providers = ['fivepost', 'cdek'].map(p => {
  const el = new Element();
  el.dataset.deliveryProvider = p;
  return el;
});

const storageMap = new Map();
const localStorage = {
  getItem: k => storageMap.has(k) ? storageMap.get(k) : null,
  setItem: (k, v) => storageMap.set(k, String(v)),
  removeItem: k => storageMap.delete(k)
};

const requests = [];
const domReadyListeners = [];
let widgetInstance = null;

class FakeYooMoneyCheckoutWidget {
  constructor(cfg) {
    this.cfg = cfg;
    this.handlers = {};
    this.renderedTo = null;
    this.destroyed = false;
    widgetInstance = this;
  }
  on(event, fn) { this.handlers[event] = fn; }
  render(containerId) { this.renderedTo = containerId; }
  destroy() { this.destroyed = true; }
}

const document = {
  readyState: 'loading',
  body: new Element('body'),
  cookie: '',
  getElementById: id => elements[id] || null,
  querySelectorAll: sel => sel === '[data-delivery-provider]' ? providers : [],
  querySelector: () => null,
  addEventListener: (name, cb) => { if (name === 'DOMContentLoaded') domReadyListeners.push(cb); }
};

const windowObj = {
  crypto: require('node:crypto').webcrypto,
  localStorage,
  location: { origin: 'http://localhost', pathname: '/catalog', href: 'http://localhost/catalog' },
  YooMoneyCheckoutWidget: FakeYooMoneyCheckoutWidget,
  addEventListener() {},
  dispatchEvent() {},
  CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init?.detail; } }
};

const context = vm.createContext({
  window: windowObj,
  document,
  localStorage,
  console: { ...console, error() {} },
  requestAnimationFrame: fn => fn(),
  setTimeout: () => 1,
  clearTimeout() {},
  setInterval: () => 1,
  clearInterval() {},
  fetch: (url, options) => new Promise(resolve => requests.push({ url, options, resolve }))
});

vm.runInContext(fs.readFileSync(path.join(__dirname, '../js/cart.js'), 'utf8'), context);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../js/checkout.js'), 'utf8'), context);
domReadyListeners.forEach(fn => fn());

const tick = () => new Promise(resolve => setImmediate(resolve));
const reply = async (req, body) => { req.resolve({ ok: true, json: async () => body }); await tick(); await tick(); };

(async () => {
  const BFCart = windowObj.BFCart;
  const BFCheckout = windowObj.BFCheckout;
  assert.ok(BFCart && BFCheckout, 'BFCart and BFCheckout initialized on non-product page without #orderModalBtn');
  assert.equal(elements.cartCountBadge.hidden, true, 'badge hidden when cart is empty');

  // 1. Add items, merge duplicates, distinguish sizes
  BFCart.addItem({ productId: '10', name: 'Худи Кузница', price: '4 900 ₽', priceNumeric: 4900, gender: 'Мужской', size: 'L', qty: 1, img: '/images/h1.jpg', url: '/product/10' });
  BFCart.addItem({ productId: '10', name: 'Худи Кузница', price: '4 900 ₽', priceNumeric: 4900, gender: 'Мужской', size: 'L', qty: 1, img: '/images/h1.jpg', url: '/product/10' });
  BFCart.addItem({ productId: '11', name: 'Футболка Тишина', price: '3 200 ₽', priceNumeric: 3200, gender: 'Женский', size: 'S', qty: 1, img: '/images/t1.jpg', url: '/product/11' });

  assert.equal(BFCart.getItems().length, 2, 'identical product+gender+size merged into 1 line');
  assert.equal(BFCart.getTotalUnits(), 3, 'total physical units = 3 (2 + 1)');
  assert.equal(BFCart.getSubtotal(), 13000, 'subtotal = 2*4900 + 3200 = 13000');
  assert.equal(elements.cartCountBadge.hidden, false);
  assert.equal(elements.cartCountBadge.textContent, '3');

  // 2. Server validation marks one item unavailable -> blocks checkout until removed
  elements.cartCheckoutBtn.trigger('click');
  assert.match(requests[0].url, /cart-validate\.php/);
  await reply(requests[0], {
    success: true,
    items: [
      { key: '10__Мужской__L', productId: '10', name: 'Худи Кузница', gender: 'Мужской', size: 'L', qty: 2, price: '4 900 ₽', priceNumeric: 4900, img: '/images/h1.jpg', url: '/product/10', available: true, reason: '' },
      { key: '11__Женский__S', productId: '11', name: 'Футболка Тишина', gender: 'Женский', size: 'S', qty: 1, price: '3 200 ₽', priceNumeric: 3200, img: '/images/t1.jpg', url: '/product/11', available: false, reason: 'Размер закончился' }
    ]
  });
  assert.equal(elements.orderModal.classList.contains('is-open'), false, 'checkout modal does not open when cart contains unavailable item');
  assert.equal(elements.cartDrawerError.hidden, false, 'drawer displays unavailable item warning');
  assert.equal(elements.cartCheckoutBtn.disabled, true, 'checkout button disabled while unavailable item present');

  // Remove unavailable item & add available M size -> total 3 units (2*4900 + 1*3200 = 13000)
  BFCart.removeItem('11__Женский__S');
  BFCart.addItem({ productId: '11', name: 'Футболка Тишина', price: '3 200 ₽', priceNumeric: 3200, gender: 'Женский', size: 'M', qty: 1, img: '/images/t1.jpg', url: '/product/11' });
  assert.equal(elements.cartCheckoutBtn.disabled, false, 'checkout button re-enabled after removing unavailable item');

  // 3. Proceed to checkout from cart
  elements.cartCheckoutBtn.trigger('click');
  await reply(requests[1], {
    success: true,
    items: [
      { key: '10__Мужской__L', productId: '10', name: 'Худи Кузница', gender: 'Мужской', size: 'L', qty: 2, price: '4 900 ₽', priceNumeric: 4900, img: '/images/h1.jpg', url: '/product/10', available: true, reason: '' },
      { key: '11__Женский__M', productId: '11', name: 'Футболка Тишина', gender: 'Женский', size: 'M', qty: 1, price: '3 200 ₽', priceNumeric: 3200, img: '/images/t1.jpg', url: '/product/11', available: true, reason: '' }
    ]
  });
  assert.equal(elements.orderModal.classList.contains('is-open'), true, 'orderModal opens in cart mode');
  assert.equal(elements.orderModalSingleSummary.hidden, true, 'single product summary hidden');
  assert.equal(elements.orderModalCartSummary.hidden, false, 'cart summary shown');
  assert.match(elements.orderModalTitle.textContent, /3 шт\./);

  // 4. Switch to CDEK and verify quote passes value=13000 & qty=3
  providers[1].trigger('click');
  assert.match(requests[2].url, /cdek-points\.php/);
  await reply(requests[2], {
    success: true,
    mode: 'sandbox',
    points: [{ id: 'PVZ77', name: 'ПВЗ СДЭК', type: 'PVZ', full_address: 'Москва, Тверская 1', lat: 55.75, lng: 37.61 }]
  });

  elements.deliveryPointList.trigger('click', {
    target: { closest: () => ({ dataset: { pointId: 'PVZ77' } }) }
  });
  assert.match(requests[3].url, /cdek-quote\.php\?point=PVZ77&value=13000&qty=3/, 'CDEK quote receives multi-item total value=13000 and qty=3');

  await reply(requests[3], {
    success: true,
    mode: 'sandbox',
    point: { id: 'PVZ77', name: 'ПВЗ СДЭК', type: 'PVZ', full_address: 'Москва, Тверская 1' },
    quote: { cost: 550, days_min: 2, days_max: 4 }
  });
  assert.match(elements.orderConfirmBtn.textContent, /13.550/, 'submit button shows goods + CDEK delivery (13 550 ₽)');

  // 5. Submit multi-item cart order -> verify payload, widget render & automatic cart clear on payment success
  elements.orderFio.value = 'Иванов Иван Иванович';
  elements.orderEmail.value = 'buyer@example.com';
  elements.orderPhone.value = '+7 (999) 123-45-67';
  elements.orderPolicyAgree.checked = true;
  elements.orderForm.trigger('submit', { preventDefault() {} });

  assert.equal(requests[4].url, '/api/yookassa/create.php');
  const createPayload = JSON.parse(requests[4].options.body);
  assert.equal(Array.isArray(createPayload.items), true);
  assert.equal(createPayload.items.length, 2);
  assert.deepEqual(createPayload.items[0], { productId: '10', name: 'Худи Кузница', gender: 'Мужской', size: 'L', qty: 2 });
  assert.deepEqual(createPayload.items[1], { productId: '11', name: 'Футболка Тишина', gender: 'Женский', size: 'M', qty: 1 });
  assert.equal(createPayload.deliveryProvider, 'cdek');
  assert.equal(createPayload.deliveryQuoteCost, 550);

  await reply(requests[4], {
    success: true,
    confirmation_token: 'ct-test-token',
    payment_id: 'pay_test_123',
    total_amount: '13550.00',
    delivery_cost: '550.00'
  });

  assert.ok(widgetInstance, 'YooKassa widget instantiated');
  assert.equal(widgetInstance.renderedTo, 'yookassaWidgetContainer', 'widget rendered into dedicated #yookassaWidgetContainer without destroying map DOM');
  assert.equal(widgetInstance.cfg.return_url, 'http://localhost/payment-success.php?orderId=' + encodeURIComponent(createPayload.orderId), 'return_url matches HEAD 1:1');
  assert.equal(localStorage.getItem('bf_pending_cart_order'), createPayload.orderId, 'pending cart order saved in localStorage');

  // Widget success is not sufficient: preserve the cart until server confirmation.
  widgetInstance.handlers.success();
  assert.equal(BFCart.getTotalUnits(), 3, 'cart preserved while server confirmation is pending');
  assert.equal(requests[5].url, '/api/yookassa/confirm.php');
  const confirmed = !process.argv.includes('--unconfirmed');
  await reply(requests[5], { success: confirmed });
  assert.equal(BFCart.getTotalUnits(), confirmed ? 0 : 3, 'cart clears only after server confirms payment');
  assert.equal(elements.cartCountBadge.hidden, confirmed, 'header badge follows confirmed payment status');
  assert.equal(windowObj.location.href, '/payment-success.php?orderId=' + createPayload.orderId);

  console.log('OK: cart state, server availability validation, multi-unit CDEK quote, YooKassa multi-item payload & auto-clear');
})().catch(err => {
  console.error(err);
  process.exitCode = 1;
});
