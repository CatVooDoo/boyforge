<?php
declare(strict_types=1);

// Called only by authenticated, CSRF-checked api.php requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Требуется POST-запрос.'], JSON_UNESCAPED_UNICODE);
    return;
}

$locked = false;
try {
    // Serialize mutations across administrator sessions, including file replacement.
    $locked = (int)$pdo->query("SELECT GET_LOCK('boyforge_slider_content', 10)")->fetchColumn() === 1;
    if (!$locked) {
        throw new RuntimeException('Слайдер сейчас изменяется. Повторите действие.');
    }
    $result = mutateSlider($pdo, $action);
    echo json_encode(['success' => true] + $result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => $e instanceof PDOException ? 'Не удалось сохранить изменения. Обновите страницу и повторите.' : $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log('Slider mutation failed: ' . $e->getMessage());
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Slider mutation failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить изменения. Повторите попытку.'], JSON_UNESCAPED_UNICODE);
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($locked) {
        $pdo->query("SELECT RELEASE_LOCK('boyforge_slider_content')");
    }
}

function mutateSlider(PDO $pdo, string $action): array {
    if ($action === 'slider_upload' || $action === 'slider_replace') {
        $device = $_POST['device_type'] ?? '';
        if (!in_array($device, ['desktop', 'mobile'], true)) {
            throw new InvalidArgumentException('Выберите десктопный или мобильный слайдер.');
        }
        $old = null;
        if ($action === 'slider_replace') {
            $find = $pdo->prepare('SELECT * FROM slider_slides WHERE id = ? AND device_type = ?');
            $replaceId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$replaceId) {
                throw new InvalidArgumentException('Неверный ID слайда.');
            }
            $find->execute([$replaceId, $device]);
            $old = $find->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                throw new RuntimeException('Слайд уже удалён. Обновите страницу.');
            }
        }
        // One file per request: the browser queues arbitrarily many files without
        // exceeding post_max_size or max_file_uploads with a large batch.
        $upload = handleSliderImageUpload($_FILES['image'] ?? [], $device);
        if (!$upload['success']) {
            throw new InvalidArgumentException($upload['error']);
        }
        try {
            if ($old) {
                $stmt = $pdo->prepare('UPDATE slider_slides SET image_path = ? WHERE id = ?');
                $stmt->execute([$upload['path'], $old['id']]);
                $slide = array_replace($old, ['image_path' => $upload['path']]);
            } else {
                $max = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM slider_slides WHERE device_type = ?');
                $max->execute([$device]);
                $order = (int)$max->fetchColumn();
                $stmt = $pdo->prepare('INSERT INTO slider_slides (device_type, image_path, alt_text, sort_order) VALUES (?, ?, ?, ?)');
                $stmt->execute([$device, $upload['path'], '', $order]);
                $slide = ['id' => (int)$pdo->lastInsertId(), 'device_type' => $device, 'image_path' => $upload['path'], 'alt_text' => '', 'sort_order' => $order, 'is_active' => 1];
            }
        } catch (Throwable $e) {
            deleteSliderFileIfUnused($pdo, $upload['path']);
            throw $e;
        }
        if ($old) {
            deleteSliderFileIfUnused($pdo, $old['image_path']);
        }
        return ['slide' => $slide, 'message' => $old ? 'Изображение заменено' : 'Слайд добавлен'];
    }

    if ($action === 'slider_reorder') {
        $device = $_POST['device_type'] ?? '';
        $rawIds = $_POST['ids'] ?? '';
        if (!is_string($rawIds)) {
            throw new InvalidArgumentException('Некорректный список слайдов.');
        }
        $ids = json_decode($rawIds, true);
        if (!in_array($device, ['desktop', 'mobile'], true) || !is_array($ids) || !array_is_list($ids) || !$ids) {
            throw new InvalidArgumentException('Некорректный список слайдов.');
        }
        foreach ($ids as $id) {
            if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id <= 0) {
                throw new InvalidArgumentException('Некорректный ID слайда.');
            }
        }
        $ids = array_map('intval', $ids);
        $find = $pdo->prepare('SELECT id FROM slider_slides WHERE device_type = ?');
        $find->execute([$device]);
        $existing = array_map('intval', $find->fetchAll(PDO::FETCH_COLUMN));
        $sorted = $ids;
        sort($existing);
        sort($sorted);
        if ($existing !== $sorted) {
            throw new RuntimeException('Список слайдов изменился. Обновите страницу перед сортировкой.');
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE slider_slides SET sort_order = ? WHERE id = ? AND device_type = ?');
        foreach ($ids as $position => $id) {
            $stmt->execute([$position + 1, $id, $device]);
        }
        $pdo->commit();
        return ['message' => 'Порядок сохранён'];
    }

    if (!in_array($action, ['slider_toggle', 'slider_update', 'slider_delete'], true)) {
        throw new InvalidArgumentException('Неизвестное действие.');
    }
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        throw new InvalidArgumentException('Неверный ID слайда.');
    }
    $find = $pdo->prepare('SELECT * FROM slider_slides WHERE id = ?');
    $find->execute([$id]);
    $slide = $find->fetch(PDO::FETCH_ASSOC);
    if (!$slide) {
        throw new RuntimeException('Слайд уже удалён. Обновите страницу.');
    }
    if ($action === 'slider_toggle') {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['0', '1'], true)) {
            throw new InvalidArgumentException('Некорректный статус.');
        }
        $stmt = $pdo->prepare('UPDATE slider_slides SET is_active = ? WHERE id = ?');
        $stmt->execute([(int)$status, $id]);
    } elseif ($action === 'slider_update') {
        $alt = $_POST['alt_text'] ?? '';
        if (!is_string($alt) || mb_strlen($alt) > 255) {
            throw new InvalidArgumentException('Описание должно быть не длиннее 255 символов.');
        }
        // Editing a description must never overwrite the order from a stale tab.
        $stmt = $pdo->prepare('UPDATE slider_slides SET alt_text = ? WHERE id = ?');
        $stmt->execute([trim($alt), $id]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM slider_slides WHERE id = ?');
        $stmt->execute([$id]);
        deleteSliderFileIfUnused($pdo, $slide['image_path']);
    }
    return ['message' => 'Изменения сохранены'];
}
