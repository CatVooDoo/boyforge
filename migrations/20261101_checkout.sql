-- Единая миграция СДЭК, корзины и промокодов. MySQL / MariaDB.
-- Заменяет 20261020_cdek.sql, 20261029_basket.sql и 20261030_promocodes.sql.
-- Перед запуском: резервная копия БД, выбор нужной базы (USE ...), остановка записи заказов.
-- Таблицы orders, products и categories должны существовать. Данные не удаляются.
-- Можно повторять и запускать после частичного применения старых миграций / открытия админки.
-- DDL выполняет неявный COMMIT; весь файл не является атомарной транзакцией.
-- CREATE ROUTINE не требуется. Нужны права чтения схемы, ALTER и CREATE TABLE.

-- Добавляем только отсутствующие поля одним ALTER TABLE.
SET @bf_checkout_additions = (
    SELECT GROUP_CONCAT(required_columns.ddl ORDER BY required_columns.ordinal SEPARATOR ', ')
    FROM (
        SELECT 1 AS ordinal, 'delivery_provider' AS column_name, 'ADD COLUMN delivery_provider VARCHAR(16) NOT NULL DEFAULT ''fivepost''' AS ddl
        UNION ALL SELECT 2, 'delivery_mode', 'ADD COLUMN delivery_mode VARCHAR(16) NOT NULL DEFAULT ''production'''
        UNION ALL SELECT 3, 'delivery_cost', 'ADD COLUMN delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0'
        UNION ALL SELECT 4, 'cdek_uuid', 'ADD COLUMN cdek_uuid VARCHAR(36) NULL'
        UNION ALL SELECT 5, 'cdek_number', 'ADD COLUMN cdek_number VARCHAR(32) NULL'
        UNION ALL SELECT 6, 'cdek_status', 'ADD COLUMN cdek_status VARCHAR(32) NULL'
        UNION ALL SELECT 7, 'cdek_error', 'ADD COLUMN cdek_error TEXT NULL'
        UNION ALL SELECT 8, 'items_json', 'ADD COLUMN items_json LONGTEXT NULL AFTER size'
        UNION ALL SELECT 9, 'mark_codes_json', 'ADD COLUMN mark_codes_json LONGTEXT NULL AFTER mark_code'
        UNION ALL SELECT 10, 'promo_code', 'ADD COLUMN promo_code VARCHAR(32) NULL'
        UNION ALL SELECT 11, 'discount_amount', 'ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0'
    ) AS required_columns
    LEFT JOIN information_schema.COLUMNS AS existing_columns
      ON existing_columns.TABLE_SCHEMA = DATABASE()
     AND existing_columns.TABLE_NAME = 'orders'
     AND existing_columns.COLUMN_NAME = required_columns.column_name
    WHERE existing_columns.COLUMN_NAME IS NULL
);
SET @bf_checkout_sql = IF(@bf_checkout_additions IS NULL, 'SELECT 1',
    CONCAT('ALTER TABLE orders ', @bf_checkout_additions));
PREPARE bf_checkout_stmt FROM @bf_checkout_sql;
EXECUTE bf_checkout_stmt;
DEALLOCATE PREPARE bf_checkout_stmt;

-- Сохраняем старый одиночный код маркировки, увеличивая допустимую длину.
SET @bf_checkout_sql = IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'mark_code'
      AND DATA_TYPE NOT IN ('text', 'mediumtext', 'longtext')
), 'ALTER TABLE orders MODIFY COLUMN mark_code TEXT NULL', 'SELECT 1');
PREPARE bf_checkout_stmt FROM @bf_checkout_sql;
EXECUTE bf_checkout_stmt;
DEALLOCATE PREPARE bf_checkout_stmt;

CREATE TABLE IF NOT EXISTS promocodes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL UNIQUE,
    category_id INT NOT NULL,
    discount_percent TINYINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_promocode_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @bf_checkout_additions = NULL;
SET @bf_checkout_sql = NULL;
