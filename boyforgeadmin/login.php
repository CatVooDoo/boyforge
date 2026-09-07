<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = time();
    $attempts = $_SESSION['admin_login_attempts'] ?? 0;
    $lockoutTime = $_SESSION['admin_login_lockout'] ?? 0;

    if ($lockoutTime > $now) {
        $remaining = ceil(($lockoutTime - $now) / 60);
        $error = "Превышено количество попыток входа. Подождите {$remaining} мин.";
    } else {
        $password = trim((string)($_POST['password'] ?? ''));

        if ($password !== '' && hash_equals(BOYFORGE_ADMIN_PASS, $password)) {
            unset($_SESSION['admin_login_attempts'], $_SESSION['admin_login_lockout']);
            session_regenerate_id(true);
            $_SESSION['boyforge_admin_logged'] = true;
            $_SESSION['boyforge_admin_time'] = time();

            header('Location: index.php');
            exit;
        } else {
            $attempts++;
            $_SESSION['admin_login_attempts'] = $attempts;

            if ($attempts >= 5) {
                $_SESSION['admin_login_lockout'] = $now + 300;
                $error = 'Слишком много неверных попыток. Доступ заблокирован на 5 минут.';
            } else {
                $rem = 5 - $attempts;
                $error = "Неверный пароль. Осталось попыток: {$rem}";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Вход в панель управления — BOYFORGE ADMIN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
</head>
<body class="login-page">

  <div class="login-card">
    <div class="login-header">
      <div class="login-logo">BOYFORGE</div>
      <div class="login-subtitle">Панель управления товарами</div>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert-error">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
      <div class="form-group">
        <label for="password" class="form-label">Пароль администратора</label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          class="form-input" 
          placeholder="Введите пароль" 
          required 
          autofocus
        >
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
        Войти в систему
      </button>
    </form>
  </div>

</body>
</html>
