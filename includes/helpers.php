<?php
declare(strict_types=1);

function allSizes(): array {
    return ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'];
}

function commonSizes(): array {
    return ['S', 'M', 'L', 'XL', '2XL'];
}

function productHasDualSizes(array $product): bool {
    return ($product['size_chart_mode'] ?? 'single') === 'dual';
}

/** Режим категории применяется и на витрине, и при серверной проверке заказа. */
function withProductSizeMode(PDO $pdo, array $product): array {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE slug = ? OR name = ? ORDER BY (slug = ?) DESC LIMIT 1');
    $slug = (string)($product['cat_id'] ?? '');
    $stmt->execute([$slug, (string)($product['cat'] ?? ''), $slug]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    $product['size_chart_mode'] = ($category['size_chart_mode'] ?? 'single') === 'dual' ? 'dual' : 'single';
    $product['category_size_rules'] = categorySizeRules($category['size_chart_rules'] ?? null);
    return $product;
}

/** Правила категории хранятся отдельно от наличия размеров конкретного товара. */
function categorySizeRules($raw): array {
    $parsed = is_array($raw) ? $raw : json_decode((string)($raw ?? ''), true);
    $rules = [];
    foreach (['Общая' => commonSizes()] + sizesByGender() as $chart => $sizes) {
        foreach (['hidden', 'blocked'] as $state) {
            $list = $parsed[$chart][$state] ?? [];
            $rules[$chart][$state] = is_array($list) ? array_values(array_intersect($sizes, array_filter($list, 'is_string'))) : [];
        }
    }
    return $rules;
}

function productHiddenSizes(array $product, string $chart): array {
    return $product['category_size_rules'][$chart]['hidden'] ?? [];
}

function effectiveProductUnavailableSizes(array $product): array {
    $unavailable = productUnavailableSizes($product['sizes'] ?? null);
    foreach ($unavailable as $chart => $sizes) {
        $unavailable[$chart] = array_values(array_unique(array_merge($sizes, $product['category_size_rules'][$chart]['blocked'] ?? [])));
    }
    return $unavailable;
}

function sizesByGender(): array {
    return [
        'Женский' => ['XS', 'S', 'M', 'L', 'XL'],
        'Мужской' => ['S', 'M', 'L', 'XL', '2XL', '3XL'],
    ];
}

/** Единая проверка варианта товара для корзины и создания платежа. */
function productVariantError(array $product, string $gender, string $size): string {
    $chart = productHasDualSizes($product) ? $gender : 'Общая';
    if (in_array($size, productHiddenSizes($product, $chart), true)) {
        return 'Выберите доступный размер для товара «' . (string)($product['name'] ?? 'Товар BOYFORGE') . '»';
    }
    if (!productHasDualSizes($product)) {
        $name = (string)($product['name'] ?? 'Товар BOYFORGE');
        if (!in_array($size, commonSizes(), true)) {
            return 'Выберите доступный размер для товара «' . $name . '»';
        }
        $unavailable = effectiveProductUnavailableSizes($product);
        return in_array($size, $unavailable['Общая'], true)
            ? sprintf('Размер %s для товара «%s» закончился', $size, $name) : '';
    }
    $sizes = sizesByGender();
    $name = (string)($product['name'] ?? 'Товар BOYFORGE');
    if (!isset($sizes[$gender])) {
        return 'Выберите пол для товара «' . $name . '»';
    }
    if ($size === '' || !in_array($size, $sizes[$gender], true)) {
        return 'Выберите доступный размер для товара «' . $name . '»';
    }
    $unavailable = effectiveProductUnavailableSizes($product);
    if (in_array($size, $unavailable[$gender] ?? [], true)) {
        return sprintf('Размер %s (%s) для товара «%s» закончился', $size, $gender, $name);
    }
    return '';
}

function productUnavailableSizes($sizesRaw): array {
    if (is_array($sizesRaw)) {
        $parsed = $sizesRaw;
    } else {
        $parsed = json_decode((string)($sizesRaw ?? ''), true);
    }
    
    $result = ['Мужской' => [], 'Женский' => [], 'Общая' => []];
    $known = allSizes();
    
    if (!is_array($parsed)) {
        return $result;
    }
    
    if (isset($parsed[0]) || empty($parsed)) {
        $flat = [];
        foreach ($parsed as $s) {
            $s = trim((string)$s);
            if ($s !== '' && in_array($s, $known, true) && !in_array($s, $flat, true)) {
                $flat[] = $s;
            }
        }
        $result['Мужской'] = $flat;
        $result['Женский'] = $flat;
        $result['Общая'] = $flat;
    } else {
        foreach (['Мужской', 'Женский', 'Общая'] as $gender) {
            if (isset($parsed[$gender]) && is_array($parsed[$gender])) {
                foreach ($parsed[$gender] as $s) {
                    $s = trim((string)$s);
                    if ($s !== '' && in_array($s, $known, true) && !in_array($s, $result[$gender], true)) {
                        $result[$gender][] = $s;
                    }
                }
            }
        }
    }
    
    return $result;
}

function generateSlug(string $text, string $table, ?int $currentId, PDO $pdo): string {
    $transliteration = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo',
        'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M',
        'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U',
        'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch',
        'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
    ];
    
    $slug = strtr($text, $transliteration);
    
    $slug = mb_strtolower($slug, 'UTF-8');
    
    $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug);
    
    $slug = trim($slug, '-');
    
    if ($slug === '') {
        $slug = 'item';
    }
    
    $maxLength = ($table === 'categories') ? 64 : 255;
    if (mb_strlen($slug) > $maxLength) {
        $slug = mb_substr($slug, 0, $maxLength);
        $slug = rtrim($slug, '-');
    }
    
    $baseSlug = $slug;
    $counter = 2;
    
    while (true) {
        $checkSql = "SELECT COUNT(*) FROM {$table} WHERE slug = :slug";
        $params = [':slug' => $slug];
        
        if ($currentId !== null) {
            $checkSql .= " AND id != :id";
            $params[':id'] = $currentId;
        }
        
        $stmt = $pdo->prepare($checkSql);
        $stmt->execute($params);
        $count = (int)$stmt->fetchColumn();
        
        if ($count === 0) {
            break;
        }
        
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}
