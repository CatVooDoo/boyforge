const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('node:child_process');
const { once } = require('node:events');
const net = require('node:net');
const path = require('node:path');

const root = path.join(__dirname, '..');

(async () => {
  const html = execFileSync('php', ['includes/components/cart_modal.php'], { cwd: root, encoding: 'utf8' });
  assert.match(html, /data-delivery-provider="fivepost"/);
  assert.doesNotMatch(html, /data-delivery-provider="cdek"/);
  assert.doesNotMatch(html, /СДЭК/i, 'checkout does not mention the disabled carrier');

  const listener = net.createServer();
  listener.listen(0, '127.0.0.1');
  await once(listener, 'listening');
  const port = listener.address().port;
  await new Promise(resolve => listener.close(resolve));
  const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', root], { stdio: ['ignore', 'ignore', 'pipe'] });
  const stopped = once(server, 'exit');
  try {
    await new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error('PHP server did not start')), 5000);
      server.once('error', error => { clearTimeout(timer); reject(error); });
      server.stderr.on('data', data => {
        if (data.toString().includes('Development Server')) { clearTimeout(timer); resolve(); }
      });
    });
    const base = `http://127.0.0.1:${port}`;
    for (const url of ['/api/cdek-points.php?city=Москва', '/api/cdek-quote.php?point=MSK1&value=3200']) {
      const response = await fetch(base + url);
      assert.equal(response.status, 503);
      const body = await response.json();
      assert.equal(body.success, false);
      assert.match(body.error, /способ доставки временно недоступен/);
    }
    for (const items of [undefined, [{ productId: '1', gender: 'Мужской', size: 'M', qty: 2 }]]) {
      const response = await fetch(base + '/api/yookassa/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ deliveryProvider: 'cdek', productId: '1', items }),
      });
      assert.equal(response.status, 503, 'CDEK is rejected before DB access and payment creation');
      assert.match((await response.json()).error, /Выберите 5Post/);
    }
    const invalid = await fetch(base + '/api/yookassa/create.php', {
      method: 'POST', body: JSON.stringify({ deliveryProvider: 'unknown' }),
    });
    assert.equal(invalid.status, 400);
    console.log('OK: CDEK hidden; pickup, quote, single and basket payment requests blocked before integrations');
  } finally {
    server.kill();
    await stopped;
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
