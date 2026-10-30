<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

$currentScript = basename($_SERVER['SCRIPT_NAME']);
if (isset($_COOKIE['last_order_id']) && $currentScript !== 'payment-success.php') {
    $lastOrderId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_COOKIE['last_order_id']);
    if ($lastOrderId) {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT payment_status FROM orders WHERE order_id = :oid LIMIT 1");
            $stmt->execute([':oid' => $lastOrderId]);
            $status = $stmt->fetchColumn();
            if ($status === 'paid') {
                setcookie("last_order_id", "", time() - 3600, "/");
                header("Location: /payment-success.php?orderId=" . $lastOrderId);
                exit;
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
  <title><?= htmlspecialchars($pageTitle ?? 'BOYFORGE') ?></title>
  <meta name="description" content="Одежда с авторскими принтами. Пошив в России, DTF-печать. Заказ через Telegram.">
  <link rel="icon" href="/images/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/style.css?v=<?= filemtime(__DIR__ . '/../../css/style.css') ?>">
  <!-- Yandex.Metrika counter -->
  <script type="text/javascript">
      (function(m,e,t,r,i,k,a){
          m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
          m[i].l=1*new Date();
          for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
          k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
      })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=112433225', 'ym');

      ym(112433225, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
  </script>
  <noscript><div><img src="https://mc.yandex.ru/watch/112433225" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
  <!-- /Yandex.Metrika counter -->
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">

  <!-- ===== HEADER ===== -->
  <header class="site-header">
    <div class="container header-inner">
      <a href="/" class="logo-photo" aria-label="BOYFORGE — на главную">
        <img class="logo-dark" src="/images/logo.png" alt="BOYFORGE"
             onerror="this.replaceWith(document.createTextNode('BOYFORGE'))">
      </a>
      <div class="header-actions">
        <button type="button" class="header-cart-btn" id="cartToggleBtn" aria-label="Открыть корзину" aria-expanded="false">
          <svg class="header-cart-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <path d="M16 10a4 4 0 0 1-8 0"></path>
          </svg>
          <span class="header-cart-badge" id="cartCountBadge" hidden>0</span>
        </button>
        <button class="burger" aria-label="Открыть меню" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>

  <!-- ===== SIDE MENU ===== -->
  <div class="menu-overlay overlay" hidden></div>
  <nav class="mobile-menu" aria-hidden="true" aria-label="Основное меню">
    <button class="menu-close" aria-label="Закрыть меню">&times;</button>

    <div class="menu-group">
      <a href="/">Главная</a>
      <a href="/catalog">Каталог</a>
      <a href="#cart" class="mobile-menu-cart-link" data-open-cart>
        <span>Корзина</span>
        <span class="mobile-menu-cart-count" id="mobileMenuCartCount" hidden>0</span>
      </a>
    </div>

    <span class="menu-divider" aria-hidden="true"></span>

    <div class="menu-group">
      <a href="/delivery">Доставка</a>
      <a href="/payment">Оплата</a>
      <a href="/returns">Возврат</a>
    </div>

    <span class="menu-divider" aria-hidden="true"></span>

    <div class="menu-group">
      <a href="/about">О бренде</a>
      <a href="/reviews">Отзывы</a>
      <a href="/contacts">Контакты</a>
    </div>
  </nav>
