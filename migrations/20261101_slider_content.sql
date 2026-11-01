-- Миграция для раздела «Контент -> Слайдер» (мобильная и десктопная версии баннера). MySQL / MariaDB.
-- Таблица создаётся пустой (без предустановленных слайдов).
-- Можно безопасно запускать повторно (идемпотентный DDL).

CREATE TABLE IF NOT EXISTS slider_slides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_type ENUM('desktop', 'mobile') NOT NULL DEFAULT 'desktop',
    image_path VARCHAR(500) NOT NULL,
    alt_text VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slider_device_active_sort (device_type, is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
