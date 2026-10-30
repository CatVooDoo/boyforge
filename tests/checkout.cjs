// Checkout behavior with controlled network ordering, using only Node built-ins.
const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
class Element {
  constructor() {
    this.value = ''; this.style = {}; this.dataset = {}; this.listeners = {};
    this.classList = {add() {}, remove() {}, toggle() {}, contains() { return false; }};
  }
  addEventListener(name, callback) { this.listeners[name] = callback; }
  setAttribute(name, value) { this[name] = value; }
  getAttribute(name) { return name === 'data-gender' ? 'Мужской' : this[name]; }
  scrollIntoView() {}
  focus() {}
  querySelector() { return null; }
}
const ids = ['orderModalBtn', 'orderModal', 'orderForm', 'orderFio', 'orderEmail', 'orderPhone', 'orderConfirmBtn',
  'orderFormStatus', 'fivepostPointId', 'fivepostPointName', 'fivepostPointAddress', 'fivepostPointType',
  'fivepostPointDetails', 'fivepostSelectedCard', 'fivepostMapWrapper', 'fivepostCustomMap', 'fivepostCitySearch',
  'fivepostCityClear', 'fivepostMapLoader', 'deliveryNotice', 'deliveryQuote', 'deliveryPointList', 'orderPolicyAgree'];
const elements = Object.fromEntries(ids.map(id => [id, new Element()]));
const providers = ['fivepost', 'cdek'].map(provider => Object.assign(new Element(), {dataset: {deliveryProvider: provider}}));
const requests = [];
let ready;
const document = {
  getElementById: id => elements[id] || null,
  querySelectorAll: selector => selector === '[data-delivery-provider]' ? providers : [],
  querySelector: selector => selector === 'main.container' ? {dataset: {productId: '1', productName: 'Товар', productPrice: '3 200 ₽'}}
    : ['#genders button.active', '.sizes button.active'].includes(selector) ? Object.assign(new Element(), {textContent: 'M'}) : null,
  addEventListener: (name, callback) => { if (name === 'DOMContentLoaded') ready = callback; },
  body: new Element()
};
const context = vm.createContext({document, window: {crypto: require('node:crypto').webcrypto}, console: {...console, error() {}},
  setTimeout: () => 1, clearTimeout() {}, setInterval: () => 1, clearInterval() {},
  fetch: (url, options) => new Promise(resolve => requests.push({url, options, resolve}))
});
vm.runInContext(fs.readFileSync(require('node:path').join(__dirname, '../js/checkout.js'), 'utf8'), context);
ready();
const tick = () => new Promise(resolve => setImmediate(resolve));
const reply = async (request, body) => { request.resolve({ok: true, json: async () => body}); await tick(); };
const point = id => ({id, name: 'Пункт ' + id, type: 'PVZ', full_address: 'Пенза, улица', lat: 53.2, lng: 45});
const choose = id => elements.deliveryPointList.listeners.click({target: {closest: () => ({dataset: {pointId: id}})}});
(async () => {
  providers[1].listeners.click();
  assert.match(requests[0].url, /cdek-points/);
  providers[0].listeners.click();
  await reply(requests[0], {success: true, mode: 'sandbox', points: [point('STALE')]});
  assert.equal(elements.fivepostPointId.value, '');
  assert.equal(elements.deliveryPointList.innerHTML, '');
  await reply(requests[1], {success: true, points: [point('5P')]});
  providers[1].listeners.click();
  await reply(requests[2], {success: true, mode: 'sandbox', points: [point('C1')]});
  assert.match(elements.deliveryPointList.innerHTML, /C1/);
  choose('C1');
  assert.match(requests[3].url, /cdek-quote.*value=3200/);
  providers[0].listeners.click();
  await reply(requests[3], {success: true, mode: 'sandbox', point: point('C1'), quote: {cost: 400, days_min: 2, days_max: 4}});
  assert.equal(elements.fivepostPointId.value, '', 'stale quote cannot select a CDEK office for 5Post');
  await reply(requests[4], {success: true, points: [point('5P')]});
  elements.fivepostCustomMap.listeners.click({target: {closest: () => ({getAttribute: () => '5P'})}});
  assert.equal(elements.fivepostPointId.value, '5P', '5Post selection is preserved');
  providers[1].listeners.click();
  assert.equal(elements.fivepostPointId.value, '', 'switch clears the previous carrier office');
  await reply(requests[5], {success: true, mode: 'sandbox', points: [point('C2')]});
  choose('C2');
  await reply(requests[6], {success: true, mode: 'sandbox', point: point('C2'), quote: {cost: 400, days_min: 2, days_max: 4}});
  assert.equal(elements.fivepostPointId.value, 'C2');
  assert.match(elements.orderConfirmBtn.textContent, /3.600/);
  elements.orderFio.value = 'Тестовый Получатель'; elements.orderEmail.value = 'test@example.com';
  elements.orderPhone.value = '+7 (900) 000-00-00'; elements.orderPolicyAgree.checked = true;
  elements.orderForm.listeners.submit({preventDefault() {}});
  const payload = JSON.parse(requests[7].options.body);
  assert.equal(payload.deliveryProvider, 'cdek');
  assert.equal(payload.deliveryQuoteCost, 400);
  assert.equal(payload.deliveryQuoteMode, 'sandbox');
  assert.equal(payload.fivepostPointId, 'C2');
  await reply(requests[7], {success: false, error: 'Controlled end of test'});
  providers[0].listeners.click();
  await reply(requests[8], {success: true, points: [point('5P')]});
  elements.fivepostCustomMap.listeners.click({target: {closest: () => ({getAttribute: () => '5P'})}});
  elements.orderForm.listeners.submit({preventDefault() {}});
  const fivepostPayload = JSON.parse(requests[9].options.body);
  assert.equal(fivepostPayload.deliveryProvider, 'fivepost');
  assert.equal(fivepostPayload.deliveryQuoteCost, undefined, '5Post has no customer delivery surcharge');
  await reply(requests[9], {success: false, error: 'Controlled end of test'});
  console.log('OK: switching, stale responses, CDEK total and payment payload, seller-paid 5Post');
})().catch(error => { console.error(error); process.exitCode = 1; });
