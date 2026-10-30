-- После миграций СДЭК и корзины. Админка также создаёт схему автоматически.
CREATE TABLE IF NOT EXISTS promocodes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL UNIQUE,
    category_id INT NOT NULL,
    discount_percent TINYINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_promocode_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE orders
    ADD COLUMN promo_code VARCHAR(32) NULL,
    ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0;
