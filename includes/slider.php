<?php
declare(strict_types=1);

/**
 * Создаёт таблицу слайдов главного баннера, если она ещё не существует.
 * Изначально таблица пустая (без предустановленных изображений).
 */
function ensureSliderSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS slider_slides (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        device_type ENUM('desktop', 'mobile') NOT NULL DEFAULT 'desktop',
        image_path VARCHAR(500) NOT NULL,
        alt_text VARCHAR(255) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_slider_device_active_sort (device_type, is_active, sort_order, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Возвращает слайды, сгруппированные по типу устройства: ['desktop' => [...], 'mobile' => [...]]
 */
function getSliderSlidesByDevice(PDO $pdo, bool $onlyActive = true): array {
    $result = [
        'desktop' => [],
        'mobile'  => [],
    ];

    try {
        $sql = $onlyActive
            ? "SELECT * FROM slider_slides WHERE is_active = 1 ORDER BY sort_order ASC, id ASC"
            : "SELECT * FROM slider_slides ORDER BY sort_order ASC, id ASC";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $device = ($row['device_type'] ?? 'desktop') === 'mobile' ? 'mobile' : 'desktop';
            $result[$device][] = $row;
        }
    } catch (PDOException $e) {
        // До миграции витрина остаётся доступной с пустым баннером.
        if (($e->errorInfo[1] ?? null) !== 1146) {
            error_log("Slider query failed: " . $e->getMessage());
        }
    }

    return $result;
}

/**
 * Загружает изображение слайдера в директорию uploads/slider/
 */
function handleSliderImageUpload(array $file, string $deviceType = 'desktop'): array {
    $deviceType = $deviceType === 'mobile' ? 'mobile' : 'desktop';

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Некорректные параметры файла.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'error' => 'Размер файла превышает лимит сервера.'];
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'error' => 'Файл не был выбран.'];
            default:
                return ['success' => false, 'error' => 'Ошибка загрузки файла (код: ' . $file['error'] . ').'];
        }
    }

    if (!isset($file['tmp_name'], $file['size']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Некорректный загруженный файл.'];
    }

    if ($file['size'] <= 0 || $file['size'] > 15 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Размер файла превышает 15 МБ.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($allowedMimes[$mimeType])) {
        return ['success' => false, 'error' => 'Недопустимый формат. Разрешены JPG, PNG, WEBP, GIF.'];
    }

    $imgSize = @getimagesize($file['tmp_name']);
    if (!$imgSize || $imgSize[0] < 1 || $imgSize[1] < 1 || $imgSize[0] > 12000 || $imgSize[1] > 12000 || $imgSize[0] * $imgSize[1] > 40000000) {
        return ['success' => false, 'error' => 'Некорректное изображение или слишком большое разрешение (до 40 Мп и 12000 px по стороне).'];
    }

    $ext = $allowedMimes[$mimeType];
    $filename = 'slider_' . $deviceType . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $uploadDir = dirname(__DIR__) . '/uploads/slider';

    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['success' => false, 'error' => 'Каталог изображений недоступен для записи.'];
    }

    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Не удалось сохранить загруженный файл.'];
    }

    $imgSize = @getimagesize($targetPath);
    $width = is_array($imgSize) ? (int)$imgSize[0] : 0;
    $height = is_array($imgSize) ? (int)$imgSize[1] : 0;

    return [
        'success' => true,
        'path'    => 'uploads/slider/' . $filename,
        'width'   => $width,
        'height'  => $height,
    ];
}

/**
 * Удаляет физический файл слайда из uploads/slider/, если он больше не используется другими записями.
 */
function deleteSliderFileIfUnused(PDO $pdo, string $imagePath, int $excludeId = 0): void {
    $cleanPath = ltrim(trim($imagePath), '/');
    if (!str_starts_with($cleanPath, 'uploads/slider/')) {
        return;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM slider_slides WHERE image_path = :path AND id != :id");
    $stmt->execute([':path' => $cleanPath, ':id' => $excludeId]);
    if ((int)$stmt->fetchColumn() > 0) {
        return;
    }

    $fullPath = realpath(dirname(__DIR__) . '/' . $cleanPath);
    $allowedBase = realpath(dirname(__DIR__) . '/uploads/slider');
    if ($fullPath && $allowedBase && str_starts_with($fullPath, $allowedBase . DIRECTORY_SEPARATOR) && is_file($fullPath)) {
        @unlink($fullPath);
    }
}
