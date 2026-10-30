<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
requireAdminAuth();
$pageTitle = 'Правила корзины';
$error = '';
$codeValue = '';
$categoryValue = '';
$percentValue = '10';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('Сессия устарела. Обновите страницу.');
        $action = (string)($_POST['action'] ?? 'create');
        if ($action === 'toggle') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $active = (string)($_POST['active'] ?? '');
            if (!$id || !in_array($active, ['0', '1'], true)) throw new InvalidArgumentException('Некорректный промокод.');
            $stmt = $pdo->prepare('UPDATE promocodes SET is_active = ? WHERE id = ?');
            $stmt->execute([(int)$active, $id]);
        } elseif ($action === 'create') {
            $codeValue = trim((string)($_POST['code'] ?? ''));
            $categoryValue = (string)($_POST['category_id'] ?? '');
            $percentValue = (string)($_POST['discount_percent'] ?? '');
            $percent = filter_var($percentValue, FILTER_VALIDATE_INT);
            if ($percent === false || $percent < 1 || $percent > 99) throw new InvalidArgumentException('Укажите целую скидку от 1 до 99%.');
            $category = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND is_active = 1');
            $category->execute([$categoryValue]);
            $categoryId = $category->fetchColumn();
            if (!$categoryId) throw new InvalidArgumentException('Выберите активную категорию товаров.');
            $generated = $codeValue === '';
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $code = $generated ? generatePromoCode() : validatePromoCode($codeValue);
                try {
                    $stmt = $pdo->prepare('INSERT INTO promocodes (code, category_id, discount_percent) VALUES (?, ?, ?)');
                    $stmt->execute([$code, $categoryId, $percent]);
                    break;
                } catch (PDOException $e) {
                    if (($e->errorInfo[1] ?? 0) !== 1062) throw $e;
                    if (!$generated) throw new InvalidArgumentException('Такой промокод уже существует.');
                    if ($attempt === 4) throw new RuntimeException('Не удалось сгенерировать уникальный код. Повторите попытку.');
                }
            }
        } else {
            throw new InvalidArgumentException('Неизвестное действие.');
        }
        $_SESSION['promo_notice'] = $action === 'create' ? 'Промокод «' . $code . '» создан.' : 'Статус промокода обновлён.';
        header('Location: cart_rules.php');
        exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[Promocode admin] ' . $e->getMessage());
        $error = 'Не удалось сохранить изменения. Повторите попытку.';
    }
}
$categories = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$promos = $pdo->query('SELECT p.*, c.name AS category_name, c.is_active AS category_active
    FROM promocodes p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.created_at DESC, p.id DESC')->fetchAll();
$activeCount = count(array_filter($promos, static fn(array $p): bool => (bool)$p['is_active'] && (bool)$p['category_active']));
$notice = $_SESSION['promo_notice'] ?? '';
unset($_SESSION['promo_notice']);
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/cart_rules.css?v=<?= filemtime(__DIR__ . '/assets/css/cart_rules.css') ?>">
<div class="page-head">
  <div><h1 class="page-title">Правила корзины</h1><div class="page-subtitle">Промокоды и скидки на категории товаров</div></div>
  <span class="promo-count"><?= $activeCount ?> активных / <?= count($promos) ?> всего</span>
</div>
<?php if ($error !== ''): ?><div class="promo-feedback is-error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="promo-feedback" role="status"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<div class="promo-admin-layout">
  <section class="table-card promo-generator">
    <div class="promo-section-heading"><span class="promo-icon"><?= renderSvgIcon('tag') ?></span><div><h2>Новый промокод</h2><p>Свой код или случайная комбинация</p></div></div>
    <form method="post" class="promo-admin-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="action" value="create">
      <label for="promoCode">Промокод</label>
      <div class="promo-code-controls">
        <input class="form-input" id="promoCode" name="code" maxlength="32" value="<?= htmlspecialchars($codeValue) ?>" placeholder="Например, BOYFORGE10" autocomplete="off">
        <button class="btn btn-secondary" type="button" id="generatePromoBtn" title="Сгенерировать случайный промокод" aria-label="Сгенерировать случайный промокод"><?= renderSvgIcon('refresh') ?></button>
      </div>
      <p class="promo-field-hint">Буквы, цифры и символы, без пробелов. Пустое поле — случайный код.</p>
      <label for="promoCategory">Категория товаров</label>
      <select class="form-input" id="promoCategory" name="category_id" required>
        <option value="">Выберите категорию</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int)$category['id'] ?>" <?= $categoryValue === (string)$category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="promoPercent">Скидка, %</label>
      <input class="form-input" id="promoPercent" name="discount_percent" type="number" min="1" max="99" step="1" required value="<?= htmlspecialchars($percentValue) ?>">
      <p class="promo-field-hint">От 1 до 99%. Скидка действует только на товары выбранной категории. Стоимость доставки не уменьшается.</p>
      <button type="submit" class="btn btn-primary" <?= !$categories ? 'disabled' : '' ?>><?= renderSvgIcon('plus') ?> Создать промокод</button>
    </form>
  </section>
  <section class="table-card promo-list">
    <div class="promo-list-head"><h2>Промокоды</h2><p>Один промокод на заказ · многоразовое применение</p></div>
    <div class="table-responsive"><table class="admin-table"><thead><tr><th>Промокод</th><th>Категория</th><th>Скидка</th><th>Статус</th><th></th></tr></thead><tbody>
    <?php if (!$promos): ?><tr><td colspan="5" class="promo-empty">Промокодов пока нет.<br>Создайте первый код в генераторе.</td></tr><?php endif; ?>
    <?php foreach ($promos as $promo): $active = (bool)$promo['is_active'] && (bool)$promo['category_active']; ?>
      <tr>
        <td><strong class="promo-code-text"><?= htmlspecialchars($promo['code']) ?></strong><small class="promo-created"><?= htmlspecialchars($promo['created_at']) ?></small></td>
        <td><?= htmlspecialchars($promo['category_name'] ?? 'Категория удалена') ?></td>
        <td><strong>−<?= (int)$promo['discount_percent'] ?>%</strong></td>
        <td><span class="promo-state <?= $active ? 'is-active' : '' ?>"><?= $active ? 'Активен' : 'Неактивен' ?></span></td>
        <td><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$promo['id'] ?>"><input type="hidden" name="active" value="<?= $promo['is_active'] ? '0' : '1' ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= $promo['is_active'] ? 'Отключить' : 'Включить' ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </section>
</div>
<script src="assets/js/cart_rules.js?v=<?= filemtime(__DIR__ . '/assets/js/cart_rules.js') ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
