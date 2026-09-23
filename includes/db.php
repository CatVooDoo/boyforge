<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$dbHost = env_get('DB_HOST');
$dbPort = env_get('DB_PORT');
$dbName = env_get('DB_DATABASE');
$dbUser = env_get('DB_USERNAME');
$dbPass = env_get('DB_PASSWORD');

/**
 * УСТАНОВКА ЧАСОВОГО ПОЯСА
 * 
 * Критически важно для корректной работы с 54-ФЗ и ЮKassa:
 * - Все чеки должны иметь корректное время в часовом поясе ККТ (Москва)
 * - Время создания заказов должно отображаться корректно для администратора
 * - ФФД требует указания timezone в чеках (для Москвы = 2)
 */
try {
    // Устанавливаем часовой пояс MySQL на Москву (UTC+3)
    // Это гарантирует что NOW() будет возвращать московское время
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    // Устанавливаем часовой пояс сессии MySQL на Москву
    // Используем SET time_zone = '+03:00' для явного указания UTC+3
    $pdo->exec("SET time_zone = '+03:00'");
    
} catch (PDOException $e) {
    die('Database connection error');
}
