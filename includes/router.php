<?php
declare(strict_types=1);

/**
 * Роутер для обработки ЧПУ (человеко-понятных URL)
 * 
 * Поддерживает два формата URL:
 * 1. Старый: product.php?id=5 (делает 301 редирект на новый)
 * 2. Новый: /product/slug (отображает страницу товара)
 */

require_once __DIR__ . '/db.php';

/**
 * Парсит URL и определяет, какую страницу показать
 * 
 * @return array ['type' => 'product|category|404', 'id' => int|null, 'slug' => string|null]
 */
function parseRoute(): array {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = trim($path, '/');
    
    // Разбиваем путь на части
    $parts = explode('/', $path);
    
    // Главная страница
    if (empty($parts) || $parts[0] === '' || $parts[0] === 'index.php') {
        return ['type' => 'home', 'file' => 'index.php'];
    }
    
    // Страница товара: /product/slug
    if ($parts[0] === 'product' && isset($parts[1])) {
        $slug = $parts[1];
        
        // Валидация slug (только буквы, цифры, дефисы)
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return ['type' => '404'];
        }
        
        // Ищем товар по slug
        global $pdo;
        $stmt = $pdo->prepare("SELECT id FROM products WHERE slug = :slug AND is_active = 1 LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            return [
                'type' => 'product',
                'id' => (int)$product['id'],
                'slug' => $slug
            ];
        }
        
        return ['type' => '404'];
    }
    
    // Страница категории: /catalog/slug
    if ($parts[0] === 'catalog' && isset($parts[1])) {
        $slug = $parts[1];
        
        // Валидация slug
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return ['type' => '404'];
        }
        
        // Ищем категорию по slug
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, slug FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category) {
            return [
                'type' => 'category',
                'slug' => $slug
            ];
        }
        
        return ['type' => '404'];
    }
    
    // Каталог без категории: /catalog
    if ($parts[0] === 'catalog') {
        return ['type' => 'catalog', 'file' => 'catalog.php'];
    }
    
    // Статические страницы
    $staticPages = ['about', 'delivery', 'payment', 'returns', 'reviews', 'contacts', 'policy', 'privacy', 'terms', 'offer'];
    if (in_array($parts[0], $staticPages)) {
        return ['type' => 'static', 'file' => $parts[0] . '.php'];
    }
    
    // 404 для всего остального
    return ['type' => '404'];
}

/**
 * Обрабатывает старые URL и делает 301 редирект на новые
 * Вызывается в начале каждого PHP файла
 */
function handleLegacyUrls(): void {
    global $pdo;
    
    $scriptName = basename($_SERVER['SCRIPT_NAME']);
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Старый URL товара: product.php?id=5 или /product?id=5
    if (($scriptName === 'product.php' || strpos($requestUri, '/product?') === 0) && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT slug FROM products WHERE id = :id AND is_active = 1 LIMIT 1");
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product && $product['slug']) {
            $newUrl = '/product/' . $product['slug'];
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $newUrl);
            exit;
        }
    }
    
    // Старый URL категории: catalog.php?cat=tshirt
    if ($scriptName === 'catalog.php' && isset($_GET['cat']) && strpos($requestUri, 'catalog.php') !== false) {
        $catParam = trim($_GET['cat']);
        
        if ($catParam === 'all' || $catParam === '') {
            // Это главная страница каталога, редирект не нужен
            return;
        }
        
        // Ищем категорию по slug или name
        $stmt = $pdo->prepare("SELECT slug FROM categories WHERE (slug = :param OR name = :param) AND is_active = 1 LIMIT 1");
        $stmt->execute([':param' => $catParam]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category && $category['slug']) {
            $newUrl = '/catalog/' . $category['slug'];
            
            // Сохраняем параметр sort если есть
            if (isset($_GET['sort'])) {
                $newUrl .= '?sort=' . urlencode($_GET['sort']);
            }
            
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $newUrl);
            exit;
        }
    }
}

/**
 * Генерирует URL для товара
 * 
 * @param int $id ID товара
 * @param string|null $slug Slug товара (если известен)
 * @return string URL товара
 */
function productUrl(int $id, ?string $slug = null): string {
    if ($slug === null) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT slug FROM products WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        $slug = $product['slug'] ?? null;
    }
    
    if ($slug) {
        return '/product/' . $slug;
    }
    
    // Fallback на старый формат если slug нет
    return 'product.php?id=' . $id;
}

/**
 * Генерирует URL для категории
 * 
 * @param string $slug Slug категории
 * @param string|null $sort Параметр сортировки
 * @return string URL категории
 */
function categoryUrl(string $slug, ?string $sort = null): string {
    $url = '/catalog/' . $slug;
    
    if ($sort && $sort !== 'pop') {
        $url .= '?sort=' . urlencode($sort);
    }
    
    return $url;
}

function show404(): void {
    http_response_code(404);
    require_once __DIR__ . '/../404.php';
    exit;
}
