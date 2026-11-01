const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const read = file => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));
const item = { productId: '10', name: 'Футболка', gender: 'Мужской', size: 'L', qty: 1, priceNumeric: 3200 };

function cartHarness(elements) {
  const requests = [];
  const storage = new Map();
  const localStorage = {
    getItem: key => storage.get(key) ?? null,
    setItem: (key, value) => storage.set(key, String(value)),
    removeItem: key => storage.delete(key)
  };
  const window = { localStorage };
  const context = vm.createContext({
    window,
    ...(elements ? { document: {
      readyState: 'loading',
      addEventListener() {},
      getElementById: id => elements[id] || null
    } } : {}),
    fetch: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }))
  });
  vm.runInContext(read('js/cart.js'), context);
  return { cart: window.BFCart, requests };
}

function testEmptyCartControls() {
  const elements = Object.fromEntries(['cartEmptyState', 'cartItemsList', 'cartDrawerFooter', 'cartCheckoutBtn']
    .map(id => [id, {}]));
  const { cart } = cartHarness(elements);
  for (const empty of [() => cart.removeItem('10__Мужской__L'), () => cart.clear()]) {
    cart.addItem(item);
    assert.equal(elements.cartCheckoutBtn.disabled, false);
    empty();
    assert.equal(elements.cartCheckoutBtn.disabled, true, 'empty cart disables stale checkout control');
    assert.equal(elements.cartDrawerFooter.hidden, true);
    assert.equal(elements.cartEmptyState.hidden, false);
    assert.equal(elements.cartCheckoutBtn.textContent, 'Перейти к оформлению');
  }
}

async function testCartRaces() {
  for (const mutation of ['remove', 'clear', 'qty', 'add']) {
    const { cart, requests } = cartHarness();
    cart.addItem(item);
    const pending = cart.validateWithServer();
    if (mutation === 'remove') cart.removeItem('10__Мужской__L');
    if (mutation === 'clear') cart.clear();
    if (mutation === 'qty') cart.setItemQty('10__Мужской__L', 3);
    if (mutation === 'add') cart.addItem({ ...item, productId: '11' });
    const expected = JSON.stringify(cart.getItems());
    requests[0].resolve({ ok: true, json: async () => ({ success: true, items: [item] }) });
    assert.equal((await pending).ok, false, 'stale validation cannot open checkout');
    assert.equal(JSON.stringify(cart.getItems()), expected, `${mutation} survives pending validation`);
  }

  const { cart, requests } = cartHarness();
  cart.addItem(item);
  const first = cart.validateWithServer();
  const second = cart.validateWithServer();
  requests[1].resolve({ ok: true, json: async () => ({ success: true, items: [{ ...item, priceNumeric: 4000 }] }) });
  assert.equal((await second).ok, true);
  requests[0].resolve({ ok: true, json: async () => ({ success: true, items: [item] }) });
  assert.equal((await first).ok, false);
  assert.equal(cart.getSubtotal(), 4000, 'older response cannot overwrite newer prices');

  for (const failure of ['network', 'http', 'application']) {
    const pending = cart.validateWithServer();
    const request = requests.at(-1);
    if (failure === 'network') request.reject(new Error('offline'));
    else request.resolve({ ok: failure !== 'http', json: async () => ({ success: false }) });
    assert.equal((await pending).ok, false, `${failure} failure does not authorize checkout`);
    assert.equal(cart.getSubtotal(), 4000, 'failed validation preserves cart');
  }
}

async function testPaymentReturn() {
  // Execute the actual return-page script with a fake server, without sending payments.
  const script = read('payment-success.php').match(/<script>([\s\S]*?)<\/script>/)[1];
  for (const scenario of ['pending', 'http', 'network', 'paid', 'other-order', 'from-cart-only']) {
    let cleared = 0;
    let ready;
    let finish;
    const pendingOrder = scenario === 'other-order' ? 'BF-OTHER' : 'BF-ORDER';
    const elements = { paymentResultTitle: {}, paymentResultStatus: {} };
    vm.runInNewContext(script, {
      URLSearchParams,
      window: {
        location: { search: scenario === 'from-cart-only' ? '?fromCart=1' : '?orderId=BF-ORDER&fromCart=1' },
        BFCart: { clear() { cleared++; } }
      },
      document: {
        addEventListener: (name, cb) => { ready = cb; },
        getElementById: id => elements[id]
      },
      localStorage: { getItem: () => pendingOrder },
      fetch: () => new Promise((resolve, reject) => { finish = { resolve, reject }; })
    });
    ready();
    assert.equal(cleared, 0, 'navigation never clears cart before confirmation');
    if (scenario === 'from-cart-only') {
      assert.equal(finish, undefined, 'query flag alone never triggers confirmation or cart clearing');
      continue;
    }
    if (scenario === 'network') finish.reject(new Error('offline'));
    else finish.resolve({ ok: scenario !== 'http', json: async () => ({ success: scenario === 'paid' || scenario === 'other-order' }) });
    await tick();
    assert.equal(cleared, scenario === 'paid' ? 1 : 0, `${scenario}: only confirmed matching order clears cart`);
    if (['pending', 'http', 'network'].includes(scenario)) {
      assert.match(elements.paymentResultStatus.textContent, /Корзина сохранена/);
    }
  }
}

(async () => {
  testEmptyCartControls();
  await testCartRaces();
  await testPaymentReturn();
  console.log('OK: cart mutations, out-of-order validation, server failures and payment-return confirmation');
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
