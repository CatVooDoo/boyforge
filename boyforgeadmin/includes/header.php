<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

requireAdminAuth();

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>BOYFORGE ADMIN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
</head>
<body>

<?php
$currentScript = basename($_SERVER['PHP_SELF']);
$isProductsActive = ($currentScript === 'index.php' || $currentScript === 'edit.php');
$isCategoriesActive = ($currentScript === 'categories.php');
?>
<header class="admin-header">
  <div class="header-container">
    <div class="header-brand">
      <a href="index.php" class="brand-logo">BOYFORGE</a>
      <nav class="admin-nav">
        <a href="index.php" class="nav-link <?= $isProductsActive ? 'active' : '' ?>">Товары</a>
        <a href="categories.php" class="nav-link <?= $isCategoriesActive ? 'active' : '' ?>">Категории</a>
      </nav>
    </div>

    <div class="header-actions">
      <a href="../catalog.php" target="_blank" class="btn btn-secondary btn-sm" title="Открыть каталог в новой вкладке">
        <?= renderSvgIcon('external') ?>
        <span>Каталог на сайте</span>
      </a>

      <a href="logout.php" class="btn btn-danger btn-sm" title="Завершить сеанс">
        <?= renderSvgIcon('logout') ?>
        <span>Выход</span>
      </a>
    </div>
  </div>
</header>

<main class="admin-main">
