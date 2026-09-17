<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$dbHost = env_get('DB_HOST');
$dbPort = env_get('DB_PORT');
$dbName = env_get('DB_DATABASE');
$dbUser = env_get('DB_USERNAME');
$dbPass = env_get('DB_PASSWORD');

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Database connection error');
}
