<?php
declare(strict_types=1);

/**
 * Полная размерная сетка магазина (используется и на сайте, и в админке)
 */
function allSizes(): array {
    return ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'];
}

/**
 * Диапазоны размеров по полу (должны совпадать с SIZES_BY_GENDER в js/product.js)
 */
function sizesByGender(): array {
    return [
        'Женский' => ['XS', 'S', 'M', 'L', 'XL'],
        'Мужской' => ['S', 'M', 'L', 'XL', '2XL', '3XL'],
    ];
}

/**
 * Возвращает список НЕдоступных размеров товара из колонки products.sizes (JSON)
 *
 * @param mixed $sizesRaw Значение колонки sizes (JSON-строка или массив)
 * @return string[] Массив отмеченных как недоступные размеров (например ["S","2XL"])
 */
function productUnavailableSizes($sizesRaw): array {
    if (is_array($sizesRaw)) {
        $parsed = $sizesRaw;
    } else {
        $parsed = json_decode((string)($sizesRaw ?? ''), true);
    }
    if (!is_array($parsed)) {
        return [];
    }
    $known = allSizes();
    $result = [];
    foreach ($parsed as $s) {
        $s = trim((string)$s);
        if ($s !== '' && in_array($s, $known, true) && !in_array($s, $result, true)) {
            $result[] = $s;
        }
    }
    return $result;
}

/**
 * Генерирует URL-friendly slug из текста (транслитерация кириллицы)
 * 
 * @param string $text Исходный текст (например, "Футболка «Братья Святославичи»")
 * @param string $table Таблица для проверки уникальности ('products' или 'categories')
 * @param int|null $currentId ID текущей записи (чтобы не конфликтовать с самим собой)
 * @param PDO $pdo Объект PDO для запросов к БД
 * @return string Уникальный slug (например, "futbolka-bratya-svyatoslavichi")
 */
function generateSlug(string $text, string $table, ?int $currentId, PDO $pdo): string {
    // Таблица транслитерации (ГОСТ 7.79-2000 система B)
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
    
    // Транслитерация
    $slug = strtr($text, $transliteration);
    
    // Приведение к нижнему регистру
    $slug = mb_strtolower($slug, 'UTF-8');
    
    // Замена всех не-буквенно-цифровых символов на дефисы
    $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug);
    
    // Удаление дефисов в начале и конце
    $slug = trim($slug, '-');
    
    // Если slug пустой — используем 'item'
    if ($slug === '') {
        $slug = 'item';
    }
    
    // Ограничение длины (для products 255, для categories 64)
    $maxLength = ($table === 'categories') ? 64 : 255;
    if (mb_strlen($slug) > $maxLength) {
        $slug = mb_substr($slug, 0, $maxLength);
        $slug = rtrim($slug, '-');
    }
    
    // Проверка уникальности и добавление суффикса при необходимости
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
            break; // Slug уникален
        }
        
        // Добавляем суффикс
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}
