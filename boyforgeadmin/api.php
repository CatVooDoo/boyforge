<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sentCsrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($sentCsrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Недействительный токен безопасности (CSRF)'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'toggle_status':
        $id = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0) === 1 ? 1 : 0;

        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Неверный идентификатор товара'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE products SET is_active = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);

        syncProductsJs($pdo);

        echo json_encode([
            'success' => true,
            'message' => $status === 1 ? 'Товар включен на сайте' : 'Товар скрыт с сайта'
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Неверный идентификатор товара'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);

        syncProductsJs($pdo);

        echo json_encode([
            'success' => true,
            'message' => 'Товар успешно удален'
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'duplicate':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Неверный идентификатор товара'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$orig) {
            echo json_encode(['success' => false, 'error' => 'Товар не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $newName = $orig['name'] . ' (Копия)';
        $newTg = 'https://telegram.me/theboyforge?text=' . rawurlencode('Здравствуйте! Хочу заказать: ' . $newName);

        $insertStmt = $pdo->prepare("INSERT INTO products (cat_id, cat, name, price, price_numeric, img, imgs, sub, tags, description, specs, tg_link, is_active, sort_order)
            VALUES (:cat_id, :cat, :name, :price, :price_numeric, :img, :imgs, :sub, :tags, :description, :specs, :tg_link, 0, :sort_order)");

        $insertStmt->execute([
            ':cat_id' => $orig['cat_id'],
            ':cat' => $orig['cat'],
            ':name' => $newName,
            ':price' => $orig['price'],
            ':price_numeric' => $orig['price_numeric'],
            ':img' => $orig['img'],
            ':imgs' => $orig['imgs'],
            ':sub' => $orig['sub'],
            ':tags' => $orig['tags'],
            ':description' => $orig['description'],
            ':specs' => $orig['specs'],
            ':tg_link' => $newTg,
            ':sort_order' => (int)$orig['sort_order'] + 1
        ]);

        syncProductsJs($pdo);

        echo json_encode([
            'success' => true,
            'message' => 'Товар успешно скопирован'
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'sync_js':
        $count = syncProductsJs($pdo);
        echo json_encode([
            'success' => true,
            'message' => "Файл js/products.js успешно обновлен! Синхронизировано товаров: {$count}"
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'upload_image':
        if (!isset($_FILES['image'])) {
            echo json_encode(['success' => false, 'error' => 'Файл не передан'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $res = handleImageUpload($_FILES['image']);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;

    case 'save_category':
        $catId = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Укажите название категории'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($slug === '') {
            echo json_encode(['success' => false, 'error' => 'Укажите системный код (slug) категории'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Проверка уникальности slug
        $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE slug = :slug AND id != :id LIMIT 1");
        $checkStmt->execute([':slug' => $slug, ':id' => $catId]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'error' => "Код «{$slug}» уже используется другой категорией"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($catId > 0) {
            $updateStmt = $pdo->prepare("UPDATE categories SET name = :name, slug = :slug, sort_order = :sort_order, is_active = :is_active WHERE id = :id");
            $updateStmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':sort_order' => $sortOrder,
                ':is_active' => $isActive,
                ':id' => $catId
            ]);
            $msg = 'Категория успешно обновлена';
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO categories (name, slug, sort_order, is_active) VALUES (:name, :slug, :sort_order, :is_active)");
            $insertStmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':sort_order' => $sortOrder,
                ':is_active' => $isActive
            ]);
            $msg = 'Категория успешно создана';
        }

        echo json_encode(['success' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;

    case 'delete_category':
        $catId = (int)($_POST['id'] ?? 0);
        if ($catId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Неверный ID категории'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $deleteStmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $deleteStmt->execute([':id' => $catId]);

        echo json_encode(['success' => true, 'message' => 'Категория удалена'], JSON_UNESCAPED_UNICODE);
        exit;

    case 'toggle_category_status':
        $catId = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0) === 1 ? 1 : 0;

        if ($catId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Неверный ID категории'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE categories SET is_active = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $catId]);

        echo json_encode([
            'success' => true,
            'message' => $status === 1 ? 'Категория активирована' : 'Категория скрыта'
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'save_tag':
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Укажите название бейджа'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (mb_strlen($name) > 64) {
            echo json_encode(['success' => false, 'error' => 'Название бейджа слишком длинное (макс. 64 символа)'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO tags (name) VALUES (:name)");
        $stmt->execute([':name' => $name]);

        echo json_encode([
            'success' => true,
            'tag' => $name,
            'message' => "Бейдж «{$name}» сохранен в базе"
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'delete_tag':
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Укажите название бейджа'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM tags WHERE name = :name");
        $stmt->execute([':name' => $name]);

        echo json_encode([
            'success' => true,
            'tag' => $name,
            'message' => "Бейдж «{$name}» удален из общего списка"
        ], JSON_UNESCAPED_UNICODE);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие'], JSON_UNESCAPED_UNICODE);
        exit;
}
