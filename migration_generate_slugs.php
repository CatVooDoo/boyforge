<?php
declare(strict_types=1);

// This script generates slugs for existing products and categories
// Run ONCE after applying migration_add_slug_to_products.sql
// Then DELETE this file for security

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

echo "Starting slug generation...\n";

// 1. Generate slugs for products
echo "Processing products...\n";
$stmt = $pdo->query("SELECT id, name FROM products WHERE slug IS NULL OR slug = ''");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updatedProducts = 0;
foreach ($products as $product) {
    $slug = generateSlug($product['name'], 'products', (int)$product['id'], $pdo);
    
    $updateStmt = $pdo->prepare("UPDATE products SET slug = :slug WHERE id = :id");
    $updateStmt->execute([
        ':slug' => $slug,
        ':id' => $product['id']
    ]);
    
    echo "  Product ID {$product['id']}: '{$product['name']}' -> '$slug'\n";
    $updatedProducts++;
}

echo "Updated $updatedProducts products\n";

// 2. Generate slugs for categories (if empty)
echo "\nProcessing categories...\n";
$stmt = $pdo->query("SELECT id, name FROM categories WHERE slug IS NULL OR slug = ''");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updatedCategories = 0;
foreach ($categories as $category) {
    $slug = generateSlug($category['name'], 'categories', (int)$category['id'], $pdo);
    
    $updateStmt = $pdo->prepare("UPDATE categories SET slug = :slug WHERE id = :id");
    $updateStmt->execute([
        ':slug' => $slug,
        ':id' => $category['id']
    ]);
    
    echo "  Category ID {$category['id']}: '{$category['name']}' -> '$slug'\n";
    $updatedCategories++;
}

echo "Updated $updatedCategories categories\n";

echo "\nMigration completed successfully!\n";
echo "Please DELETE this file (migration_generate_slugs.php) for security.\n";
