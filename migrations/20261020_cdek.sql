-- Выполнить один раз до выкладки кода. Старые заказы остаются 5Post.
-- Колонки fivepost_point_* хранят выбранный ПВЗ обеих служб для совместимости.
ALTER TABLE orders
    ADD COLUMN delivery_provider VARCHAR(16) NOT NULL DEFAULT 'fivepost',
    ADD COLUMN delivery_mode VARCHAR(16) NOT NULL DEFAULT 'production',
    ADD COLUMN delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN cdek_uuid VARCHAR(36) NULL,
    ADD COLUMN cdek_number VARCHAR(32) NULL,
    ADD COLUMN cdek_status VARCHAR(32) NULL,
    ADD COLUMN cdek_error TEXT NULL;
