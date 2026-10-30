const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

class Element {
  constructor() {
    this.value = ''; this.textContent = ''; this.style = {}; this.dataset = {}; this.hidden = false; this.listeners = {};
    this.classList = { add() {}, remove() {}, toggle() {}, contains() { return false; } };
  }
  addEventListener(event, fn) { this.listeners[event] = fn; }
  setAttribute() {}
  getAttribute() { return 'Мужской'; }
  querySelector() { return null; }
  focus() {}
  scrollIntoView() {}
}
const elements = new Map();
const get = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
const main = {dataset: {productId: '1', productName: 'Футболка', productPrice: '3 200 ₽'}};
let ready;
const requests = [];
const document = {
  getElementById: get,
  querySelector: selector => selector === 'main.container' ? main : ['#genders button.active', '.sizes button.active'].includes(selector) ? Object.assign(new Element(), {textContent: 'M'}) : null,
  querySelectorAll: () => [],
  addEventListener: (event, fn) => { if (event === 'DOMContentLoaded') ready = fn; },
  body: new Element()
};
const window = {crypto: require('node:crypto').webcrypto};
const context = vm.createContext({
  window, document, console: {...console, error() {}}, setTimeout: () => 1, clearTimeout() {}, setInterval: () => 1, clearInterval() {},
  fetch: (url, options) => new Promise(resolve => requests.push({url, options, resolve}))
});
vm.runInContext(fs.readFileSync(path.join(__dirname, '../js/checkout.js'), 'utf8'), context);
ready();
const tick = () => new Promise(resolve => setImmediate(resolve));
const reply = async (request, body) => { request.resolve({ok: true, json: async () => body}); await tick(); };
(async () => {
  window.BFCheckout.openSingleModal();
  get('orderFio').value = 'Тестовый Покупатель';
  get('orderEmail').value = 'buyer@example.invalid';
  get('orderPhone').value = '+7 (999) 123-45-67';
  get('orderPolicyAgree').checked = true;
  get('fivepostPointId').value = 'test-point';
  get('checkoutPromoCode').value = 'save10';
  get('orderForm').listeners.submit({preventDefault() {}});
  const preview = requests.at(-1);
  assert.equal(preview.url, '/api/promo-validate.php');
  assert.deepEqual(JSON.parse(preview.options.body), {promoCode: 'SAVE10', items: [{productId: '1', gender: 'Мужской', size: 'M', qty: 1}]});
  await reply(preview, {success: true, subtotal: 3200, total: 2880, discount: 320, promo: {code: 'SAVE10', percent: 10, category: 'Футболка'}});
  const payment = requests.at(-1);
  assert.equal(payment.url, '/api/yookassa/create.php');
  const payload = JSON.parse(payment.options.body);
  assert.equal(payload.promoCode, 'SAVE10');
  assert.equal(payload.productId, '1');
  assert.equal(payload.deliveryProvider, 'fivepost');
  assert.equal(payload.deliveryQuoteCost, undefined);
  assert.match(get('checkoutPromoTotal').textContent, /2.880/);
  assert.match(get('checkoutPromoDelivery').textContent, /0/);
  await reply(payment, {success: false, error: 'Controlled test end'});
  get('checkoutPromoRemove').listeners.click();
  assert.equal(get('checkoutPromoCode').value, '');
  assert.match(get('orderConfirmBtn').textContent, /3.200/);
  console.log('OK: single-product promo application, payment payload, seller-paid delivery and removal');
})().catch(error => { console.error(error); process.exitCode = 1; });
