<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/slider.php';

function checkSlider(bool $condition, string $description): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $description);
    }
    echo 'OK: ' . $description . PHP_EOL;
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE slider_slides (id INTEGER PRIMARY KEY, device_type TEXT, image_path TEXT, alt_text TEXT, sort_order INTEGER, is_active INTEGER)');
$insert = $pdo->prepare('INSERT INTO slider_slides VALUES (?, ?, ?, ?, ?, ?)');
$insert->execute([1, 'desktop', '', 'first', 2, 1]);
$insert->execute([2, 'desktop', '', 'second', 1, 0]);
$insert->execute([3, 'mobile', '', 'mobile', 1, 1]);
$insert->execute([4, 'desktop', '', 'tie', 2, 1]);
$active = getSliderSlidesByDevice($pdo);
checkSlider(array_column($active['desktop'], 'id') === [1, 4], 'hidden slides excluded, equal positions ordered deterministically');
checkSlider(array_column($active['mobile'], 'id') === [3], 'mobile and desktop pools independent');
$all = getSliderSlidesByDevice($pdo, false);
checkSlider(array_column($all['desktop'], 'id') === [2, 1, 4], 'admin includes hidden slides in display order');
$pdo->exec('DELETE FROM slider_slides');
checkSlider(getSliderSlidesByDevice($pdo) === ['desktop' => [], 'mobile' => []], 'empty slider has no seeded banners');
checkSlider(!handleSliderImageUpload([])['success'], 'malformed file rejected');
checkSlider(!handleSliderImageUpload(['error' => UPLOAD_ERR_INI_SIZE])['success'], 'server upload limit reported');
checkSlider(!handleSliderImageUpload(['error' => UPLOAD_ERR_NO_FILE])['success'], 'missing file reported');
checkSlider(!handleSliderImageUpload(['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__, 'size' => 100])['success'], 'local file cannot masquerade as HTTP upload');

$root = dirname(__DIR__);
$base = $root . '/uploads/slider';
$suffix = bin2hex(random_bytes(8));
$sibling = $root . '/uploads/slider-review-' . $suffix;
$file = $base . '/review-' . $suffix . '.png';
$outside = $sibling . '/review.png';
if (!is_dir($base) && !mkdir($base, 0755, true) && !is_dir($base)) {
    throw new RuntimeException('Cannot create upload fixture directory');
}
mkdir($sibling, 0755);
try {
    file_put_contents($file, 'test');
    file_put_contents($outside, 'test');
    $path = 'uploads/slider/' . basename($file);
    $insert->execute([1, 'desktop', $path, '', 1, 1]);
    deleteSliderFileIfUnused($pdo, $path);
    checkSlider(is_file($file), 'referenced image retained');
    deleteSliderFileIfUnused($pdo, 'uploads/slider/../' . basename($sibling) . '/review.png');
    checkSlider(is_file($outside), 'path prefix sibling traversal cannot delete outside slider directory');
    $pdo->exec('DELETE FROM slider_slides');
    deleteSliderFileIfUnused($pdo, $path);
    checkSlider(!is_file($file), 'unreferenced slider image removed');
    deleteSliderFileIfUnused($pdo, $path);
    checkSlider(!is_file($file), 'repeated cleanup tolerates missing files');
} finally {
    if (is_file($file)) unlink($file);
    if (is_file($outside)) unlink($outside);
    rmdir($sibling);
}
