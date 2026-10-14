<?php
declare(strict_types=1);

/**
 * Handles saving/updating a product from POST data.
 *
 * @param PDO $pdo
 * @param bool $isEdit
 * @param int $id
 * @return string Error message, or empty string on success (will redirect on success)
 */
function handleProductSave(PDO $pdo, bool $isEdit, int $id): string
{
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        return 'Недействительный токен безопасности. Повторите попытку.';
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        return 'Укажите название товара.';
    }

    $cat = trim((string)($_POST['cat'] ?? 'Футболка'));
    $catId = trim((string)($_POST['cat_id'] ?? ''));
    if ($catId === '') {
        $catId = match (mb_strtolower($cat)) {
            'футболка', 'футболки' => 'tshirt',
            'свитшот', 'свитшоты' => 'sweatshirt',
            'худи' => 'hoodie',
            'кепка', 'кепки', 'бейсболка' => 'cap',
            default => 'other'
        };
    }

    $rawPrice = trim((string)($_POST['price'] ?? '0 ₽'));
    $priceNumeric = extractPriceNumeric($rawPrice);
    if ($rawPrice === '') {
        $price = number_format($priceNumeric, 0, '', ' ') . ' ₽';
    } else {
        $price = $rawPrice;
        if (!str_contains($price, '₽') && !str_contains($price, 'руб')) {
            $price .= ' ₽';
        }
    }

    $sub = trim((string)($_POST['sub'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    // Обработка тегов / бейджей
    $selectedTags = $_POST['tags'] ?? [];
    if (!is_array($selectedTags)) $selectedTags = [];
    $selectedTags = array_values(array_filter(array_map('trim', $selectedTags)));

    $customTag = trim((string)($_POST['custom_tag'] ?? ''));
    if ($customTag !== '' && !in_array($customTag, $selectedTags, true)) {
        $selectedTags[] = $customTag;
    }

    if (!empty($selectedTags)) {
        $stmtTag = $pdo->prepare("INSERT IGNORE INTO tags (name) VALUES (:name)");
        foreach ($selectedTags as $st) {
            if ($st !== '') {
                $stmtTag->execute([':name' => $st]);
            }
        }
    }

    $tagsJson = json_encode(array_values(array_unique($selectedTags)), JSON_UNESCAPED_UNICODE);

    // Главное изображение
    $img = trim((string)($_POST['img'] ?? ''));
    if (isset($_FILES['main_image_file']) && $_FILES['main_image_file']['error'] === UPLOAD_ERR_OK) {
        $up = handleImageUpload($_FILES['main_image_file']);
        if ($up['success']) {
            $img = $up['path'];
        } else {
            return $up['error'];
        }
    }

    // Галерея изображений
    $galleryImgs = $_POST['gallery_imgs'] ?? [];
    if (!is_array($galleryImgs)) $galleryImgs = [];
    $galleryImgs = array_filter(array_map('trim', $galleryImgs));

    if (isset($_FILES['additional_gallery_files']) && !empty($_FILES['additional_gallery_files']['name'][0])) {
        $filesCount = count($_FILES['additional_gallery_files']['name']);
        for ($i = 0; $i < $filesCount; $i++) {
            if ($_FILES['additional_gallery_files']['error'][$i] === UPLOAD_ERR_OK) {
                $singleFile = [
                    'name' => $_FILES['additional_gallery_files']['name'][$i],
                    'type' => $_FILES['additional_gallery_files']['type'][$i],
                    'tmp_name' => $_FILES['additional_gallery_files']['tmp_name'][$i],
                    'error' => $_FILES['additional_gallery_files']['error'][$i],
                    'size' => $_FILES['additional_gallery_files']['size'][$i]
                ];
                $up = handleImageUpload($singleFile);
                if ($up['success']) {
                    $galleryImgs[] = $up['path'];
                }
            }
        }
    }

    if ($img !== '' && !in_array($img, $galleryImgs, true)) {
        array_unshift($galleryImgs, $img);
    }
    $imgsJson = json_encode(array_values(array_unique($galleryImgs)), JSON_UNESCAPED_UNICODE);

    // Характеристики (specs)
    $specsJsonInput = trim((string)($_POST['specs_json'] ?? ''));
    $specs = json_decode($specsJsonInput, true);
    if (!is_array($specs)) {
        $specs = [];
    }
    $specsJson = json_encode($specs, JSON_UNESCAPED_UNICODE);

    // Ссылка на Telegram
    $tgLink = trim((string)($_POST['tg_link'] ?? ''));
    if ($tgLink === '') {
        $tgLink = 'https://telegram.me/theboyforge?text=' . rawurlencode('Здравствуйте! Хочу заказать: ' . $name);
    }

    // Недоступные размеры
    $unavailableMale = $_POST['sizes_unavailable_male'] ?? [];
    if (!is_array($unavailableMale)) $unavailableMale = [];
    $unavailableMale = array_values(array_unique(array_filter(
        array_map(fn($s) => trim((string)$s), $unavailableMale),
        fn($s) => in_array($s, allSizes(), true)
    )));

    $unavailableFemale = $_POST['sizes_unavailable_female'] ?? [];
    if (!is_array($unavailableFemale)) $unavailableFemale = [];
    $unavailableFemale = array_values(array_unique(array_filter(
        array_map(fn($s) => trim((string)$s), $unavailableFemale),
        fn($s) => in_array($s, allSizes(), true)
    )));

    $sizesJson = json_encode([
        'Мужской' => $unavailableMale,
        'Женский' => $unavailableFemale
    ], JSON_UNESCAPED_UNICODE);

    // Slug
    $slugInput = trim((string)($_POST['slug'] ?? ''));
    if ($slugInput === '') {
        $slug = generateSlug($name, 'products', $isEdit ? $id : null, $pdo);
    } else {
        $slug = generateSlug($slugInput, 'products', $isEdit ? $id : null, $pdo);
    }

    if ($isEdit) {
        $sql = "UPDATE products SET
            cat_id = :cat_id,
            cat = :cat,
            name = :name,
            slug = :slug,
            price = :price,
            price_numeric = :price_numeric,
            img = :img,
            imgs = :imgs,
            sub = :sub,
            tags = :tags,
            description = :description,
            specs = :specs,
            tg_link = :tg_link,
            sizes = :sizes,
            is_active = :is_active,
            sort_order = :sort_order
            WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cat_id' => $catId,
            ':cat' => $cat,
            ':name' => $name,
            ':slug' => $slug,
            ':price' => $price,
            ':price_numeric' => $priceNumeric,
            ':img' => $img,
            ':imgs' => $imgsJson,
            ':sub' => $sub,
            ':tags' => $tagsJson,
            ':description' => $description,
            ':specs' => $specsJson,
            ':tg_link' => $tgLink,
            ':sizes' => $sizesJson,
            ':is_active' => $isActive,
            ':sort_order' => $sortOrder,
            ':id' => $id
        ]);
    } else {
        $sql = "INSERT INTO products 
            (cat_id, cat, name, slug, price, price_numeric, img, imgs, sub, tags, description, specs, tg_link, sizes, is_active, sort_order)
            VALUES 
            (:cat_id, :cat, :name, :slug, :price, :price_numeric, :img, :imgs, :sub, :tags, :description, :specs, :tg_link, :sizes, :is_active, :sort_order)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cat_id' => $catId,
            ':cat' => $cat,
            ':name' => $name,
            ':slug' => $slug,
            ':price' => $price,
            ':price_numeric' => $priceNumeric,
            ':img' => $img,
            ':imgs' => $imgsJson,
            ':sub' => $sub,
            ':tags' => $tagsJson,
            ':description' => $description,
            ':specs' => $specsJson,
            ':tg_link' => $tgLink,
            ':sizes' => $sizesJson,
            ':is_active' => $isActive,
            ':sort_order' => $sortOrder
        ]);
        $id = (int)$pdo->lastInsertId();
    }

    syncProductsJs($pdo);

    $saveAction = $_POST['save_action'] ?? 'save_close';
    if ($saveAction === 'save_close') {
        header('Location: index.php?msg=saved');
        exit;
    } else {
        header("Location: edit.php?id={$id}&msg=saved");
        exit;
    }
}
