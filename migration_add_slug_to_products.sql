-- Migration: Add slug field to products table
-- Run this once to add slug support to products

-- 1. Add slug column (if not exists)
SET @dbname = DATABASE();
SET @tablename = 'products';
SET @columnname = 'slug';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE (table_name = @tablename)
       AND (table_schema = @dbname)
       AND (column_name = @columnname)
    ) > 0,
    "SELECT 1",
    CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL AFTER id")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Add UNIQUE index (if not exists)
SET @indexname = 'idx_products_slug_unique';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE (table_name = @tablename)
       AND (table_schema = @dbname)
       AND (index_name = @indexname)
    ) > 0,
    "SELECT 1",
    CONCAT("ALTER TABLE ", @tablename, " ADD UNIQUE INDEX ", @indexname, " (", @columnname, ")")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3. Update existing products with generated slugs
-- This is a placeholder - actual generation will be done by PHP script
-- UPDATE products SET slug = 'generated-slug' WHERE id = X;
