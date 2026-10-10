<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function parseRoute(): array {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = trim($path, '/');
    
    $parts = explode('/', $path);
    
    if (empty($parts) || $parts[0] === '' || $parts[0] === 'index.php') {
        return ['type' => 'home', 'file' => 'index.php'];
    }
    
    if ($parts[0] === 'product' && isset($parts[1])) {
        $slug = $parts[1];
        
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return ['type' => '404'];
        }
        
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
    
    if ($parts[0] === 'catalog' && isset($parts[1])) {
        $slug = $parts[1];
        
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return ['type' => '404'];
        }
        
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
    
    if ($parts[0] === 'catalog') {
        return ['type' => 'catalog', 'file' => 'catalog.php'];
    }
    
    $staticPages = ['about', 'delivery', 'payment', 'returns', 'reviews', 'contacts', 'policy', 'privacy', 'terms', 'offer'];
    if (in_array($parts[0], $staticPages)) {
        return ['type' => 'static', 'file' => $parts[0] . '.php'];
    }
    
    return ['type' => '404'];
}

function handleLegacyUrls(): void {
    global $pdo;
    
    $scriptName = basename($_SERVER['SCRIPT_NAME']);
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    
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
    
    if ($scriptName === 'catalog.php' && isset($_GET['cat']) && strpos($requestUri, 'catalog.php') !== false) {
        $catParam = trim($_GET['cat']);
        
        if ($catParam === 'all' || $catParam === '') {
            return;
        }
        
        $stmt = $pdo->prepare("SELECT slug FROM categories WHERE (slug = :param OR name = :param) AND is_active = 1 LIMIT 1");
        $stmt->execute([':param' => $catParam]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category && $category['slug']) {
            $newUrl = '/catalog/' . $category['slug'];
            
            if (isset($_GET['sort'])) {
                $newUrl .= '?sort=' . urlencode($_GET['sort']);
            }
            
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $newUrl);
            exit;
        }
    }
}

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
    
    return 'product.php?id=' . $id;
}

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
