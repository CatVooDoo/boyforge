<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/fivepost_sync.php';

requireAdminAuth();

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$force = !empty($input['force']) || isset($_GET['force']);

$result = syncFivepostOrders($pdo, (bool)$force);
echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
