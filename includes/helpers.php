<?php
declare(strict_types=1);

function allSizes(): array {
    return ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'];
}

function sizesByGender(): array {
    return [
        'Женский' => ['XS', 'S', 'M', 'L', 'XL'],
        'Мужской' => ['S', 'M', 'L', 'XL', '2XL', '3XL'],
    ];
}

function productUnavailableSizes($sizesRaw): array {
    if (is_array($sizesRaw)) {
        $parsed = $sizesRaw;
    } else {
        $parsed = json_decode((string)($sizesRaw ?? ''), true);
    }
    
    $result = ['Мужской' => [], 'Женский' => []];
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
    } else {
        foreach (['Мужской', 'Женский'] as $gender) {
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
