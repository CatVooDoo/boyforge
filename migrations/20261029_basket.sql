-- Поддержка корзины с несколькими товарами и индивидуальных кодов маркировки Честного ЗНАКа.
-- Выполнить один раз после 20261020_cdek.sql. Старые одиночные заказы продолжают работать без изменений.
ALTER TABLE orders
    ADD COLUMN items_json LONGTEXT NULL AFTER size,
    ADD COLUMN mark_codes_json LONGTEXT NULL AFTER mark_code,
    MODIFY COLUMN mark_code TEXT NULL;
