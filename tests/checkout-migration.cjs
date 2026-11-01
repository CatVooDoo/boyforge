const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

// Uses only disposable databases inside the local Compose MariaDB service.
const projectRoot = path.resolve(__dirname, '../../..');
const migration = fs.readFileSync(path.join(__dirname, '../migrations/20261101_checkout.sql'), 'utf8');
const database = 'bf_checkout_test_' + process.pid + '_' + Date.now();
const sql = input => execFileSync('docker', ['compose', 'exec', '-T', 'mariadb', 'sh', '-c',
  'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" --batch --skip-column-names'], {
  cwd: projectRoot, input, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'],
});
const fields = ['delivery_provider', 'delivery_mode', 'delivery_cost', 'cdek_uuid', 'cdek_number',
  'cdek_status', 'cdek_error', 'items_json', 'mark_codes_json', 'promo_code', 'discount_amount'];
const baseline = `CREATE TABLE orders (
  id INT PRIMARY KEY, order_id VARCHAR(64) NOT NULL UNIQUE, size VARCHAR(20),
  mark_code VARCHAR(255) NULL, price DECIMAL(10,2) NOT NULL, payment_status VARCHAR(50) NOT NULL
) ENGINE=InnoDB;
INSERT INTO orders VALUES (1,'BF-LEGACY','M','legacy-mark',3200.00,'paid');`;

try {
  sql(`CREATE DATABASE \`${database}\` CHARACTER SET utf8mb4;`);
  for (const scenario of ['original schema', 'CDEK already applied', 'basket already applied']) {
    sql(`USE \`${database}\`; DROP TABLE IF EXISTS orders; DROP TABLE IF EXISTS promocodes; ${baseline}`);
    if (scenario === 'CDEK already applied') sql(`USE \`${database}\`;
      ALTER TABLE orders ADD delivery_provider VARCHAR(16) NOT NULL DEFAULT 'fivepost',
      ADD cdek_uuid VARCHAR(36) NULL;
      UPDATE orders SET delivery_provider='cdek', cdek_uuid='preserved-uuid';`);
    if (scenario === 'basket already applied') sql(`USE \`${database}\`;
      ALTER TABLE orders ADD items_json LONGTEXT NULL, ADD mark_codes_json LONGTEXT NULL,
      MODIFY mark_code TEXT NULL;
      UPDATE orders SET items_json='[{"product_id":"1","qty":2}]';`);
    sql(`USE \`${database}\`; ${migration}`);
    const actual = sql(`SELECT COLUMN_NAME FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA='${database}' AND TABLE_NAME='orders';`).trim().split('\n');
    fields.forEach(field => assert(actual.includes(field), field));
    assert.equal(sql(`USE \`${database}\`; SELECT CONCAT(order_id,'|',price,'|',payment_status,'|',mark_code) FROM orders;`).trim(),
      'BF-LEGACY|3200.00|paid|legacy-mark');
    if (scenario === 'CDEK already applied') assert.equal(sql(`USE \`${database}\`; SELECT cdek_uuid FROM orders;`).trim(),'preserved-uuid');
    if (scenario === 'basket already applied') assert.equal(sql(`USE \`${database}\`; SELECT items_json FROM orders;`).trim(),'[{"product_id":"1","qty":2}]');
    sql(`USE \`${database}\`; UPDATE orders SET mark_code=REPEAT('X',500), promo_code='TEST', discount_amount=320;
      INSERT INTO promocodes(code,category_id,discount_percent) VALUES('TEST',1,10);`);
    const before = sql(`USE \`${database}\`; SHOW CREATE TABLE orders; SHOW CREATE TABLE promocodes;
      SELECT * FROM orders; SELECT * FROM promocodes;`);
    sql(`USE \`${database}\`; ${migration}`);
    const after = sql(`USE \`${database}\`; SHOW CREATE TABLE orders; SHOW CREATE TABLE promocodes;
      SELECT * FROM orders; SELECT * FROM promocodes;`);
    assert.equal(after, before, 'repeat must preserve schema, prices, mark codes and promocodes');
    console.log('OK: consolidated migration preserves data and can repeat — ' + scenario);
  }
} finally {
  sql(`DROP DATABASE IF EXISTS \`${database}\`;`);
}
