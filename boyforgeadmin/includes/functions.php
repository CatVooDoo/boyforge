<?php
declare(strict_types=1);

function renderSvgIcon(string $name, string $class = 'icon'): string {
    $classAttr = 'class="' . htmlspecialchars($class) . '"';
    $baseAttrs = 'xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' . $classAttr;

    switch ($name) {
        case 'plus':
            return '<svg ' . $baseAttrs . '><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>';
        case 'edit':
            return '<svg ' . $baseAttrs . '><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
        case 'trash':
            return '<svg ' . $baseAttrs . '><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';
        case 'eye':
            return '<svg ' . $baseAttrs . '><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        case 'eye-off':
            return '<svg ' . $baseAttrs . '><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        case 'check':
            return '<svg ' . $baseAttrs . '><polyline points="20 6 9 17 4 12"></polyline></svg>';
        case 'x':
            return '<svg ' . $baseAttrs . '><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        case 'search':
            return '<svg ' . $baseAttrs . '><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
        case 'filter':
            return '<svg ' . $baseAttrs . '><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>';
        case 'image':
            return '<svg ' . $baseAttrs . '><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
        case 'upload':
            return '<svg ' . $baseAttrs . '><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>';
        case 'refresh':
            return '<svg ' . $baseAttrs . '><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>';
        case 'copy':
            return '<svg ' . $baseAttrs . '><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
        case 'logout':
            return '<svg ' . $baseAttrs . '><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>';
        case 'external':
            return '<svg ' . $baseAttrs . '><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>';
        case 'arrow-left':
            return '<svg ' . $baseAttrs . '><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>';
        case 'lock':
            return '<svg ' . $baseAttrs . '><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>';
        case 'box':
            return '<svg ' . $baseAttrs . '><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>';
        case 'tag':
            return '<svg ' . $baseAttrs . '><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>';
        default:
            return '<svg ' . $baseAttrs . '><circle cx="12" cy="12" r="10"></circle></svg>';
    }
}

function syncProductsJs(PDO $pdo): int {
    $stmt = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $jsItems = [];
    foreach ($products as $p) {
        $id = (int)$p['id'];
        $catId = $p['cat_id'] ?: 'tshirt';
        $cat = $p['cat'] ?: 'Футболка';
        $name = $p['name'];
        $price = $p['price'] ?: '3 200 ₽';
        $img = $p['img'] ?: '';
        
        $imgs = json_decode($p['imgs'] ?? '[]', true);
        if (!is_array($imgs) || empty($imgs)) {
            $imgs = !empty($img) ? [$img] : [];
        }

        $sub = $p['sub'] ?: '';
        $tags = json_decode($p['tags'] ?? '[]', true);
        if (!is_array($tags)) {
            $tags = [];
        }

        $desc = $p['description'] ?: '';
        $specs = json_decode($p['specs'] ?? '[]', true);
        if (!is_array($specs)) {
            $specs = [];
        }

        $tg = $p['tg_link'] ?: ('https://telegram.me/theboyforge?text=' . rawurlencode('Здравствуйте! Хочу заказать: ' . $name));

        $jsItems[] = [
            'id' => $id,
            'catId' => $catId,
            'cat' => $cat,
            'name' => $name,
            'price' => $price,
            'img' => $img,
            'imgs' => $imgs,
            'sub' => $sub,
            'tags' => $tags,
            'desc' => $desc,
            'specs' => $specs,
            'tg' => $tg
        ];
    }

    $jsonProducts = json_encode($jsItems, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $content = <<<JS
"use strict";

(function () {
  "use strict";

  const PRODUCTS = {$jsonProducts};

  PRODUCTS.forEach((p) => {
    if (!Array.isArray(p.imgs) || !p.imgs.length) {
      p.imgs = [p.img];
    } else if (p.imgs[0] !== p.img) {
      p.imgs = [p.img, ...p.imgs.filter((s) => s !== p.img)];
    }
  });

  const getProductById = (id) =>
    PRODUCTS.find((p) => String(p.id) === String(id)) || null;

  const getRelated = (id, limit = 4) => {
    const cur = getProductById(id);
    if (!cur) return [];
    return PRODUCTS.filter((p) => p.catId === cur.catId && p.id !== cur.id).slice(0, limit);
  };

  const getByCat = (catId) =>
    catId ? PRODUCTS.filter((p) => p.catId === catId) : PRODUCTS.slice();

  window.PRODUCTS = PRODUCTS;
  window.getProductById = getProductById;
  window.getRelated = getRelated;
  window.getByCat = getByCat;
})();

JS;

    $targetFile = dirname(__DIR__, 2) . '/js/products.js';
    file_put_contents($targetFile, $content);

    return count($jsItems);
}

function handleImageUpload(array $file): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Некорректные параметры файла.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'error' => 'Размер файла превышает лимит сервера.'];
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'error' => 'Файл не был загружен.'];
            default:
                return ['success' => false, 'error' => 'Ошибка при загрузке файла (код: ' . $file['error'] . ').'];
        }
    }

    if ($file['size'] > 15 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Размер файла превышает 15 МБ.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    if (!isset($allowedMimes[$mimeType])) {
        return ['success' => false, 'error' => 'Недопустимый формат файла. Разрешены JPG, PNG, WEBP, GIF.'];
    }

    $ext = $allowedMimes[$mimeType];
    $filename = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $uploadDir = dirname(__DIR__, 2) . '/uploads/products';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Не удалось переместить загруженный файл.'];
    }

    return [
        'success' => true,
        'path' => 'uploads/products/' . $filename
    ];
}

function extractPriceNumeric(string $price): int {
    $clean = preg_replace('/[^\d]/', '', $price);
    return (int)$clean;
}
