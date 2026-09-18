<?php
declare(strict_types=1);

$pageTitle = 'Страница не найдена — BOYFORGE';
$bodyClass = 'page-404';

require_once __DIR__ . '/includes/components/header.php';
?>

<main>
    <div class="container" style="text-align: center; padding: 100px 0;">
        <h1 style="font-size: 80px; font-weight: 800; color: var(--text-muted); margin-bottom: 20px;">404</h1>
        <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 20px;">Страница не найдена</h2>
        <p style="margin-bottom: 40px; color: var(--text-dim);">Возможно, она была удалена или вы ввели неверный адрес.</p>
        <a href="/" class="btn-primary" style="display: inline-block; padding: 14px 28px;">На главную</a>
    </div>
</main>

<?php require_once __DIR__ . '/includes/components/footer.php'; ?>
