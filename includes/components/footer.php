<?php
declare(strict_types=1);
?>
  <!-- ===== FOOTER ===== -->
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-col">
          <h4>Компания</h4>
          <a href="/about">О бренде</a>
          <a href="/contacts">Контакты</a>
        </div>
        <div class="footer-col">
          <h4>Помощь</h4>
          <a href="/delivery">Доставка</a>
          <a href="/payment">Оплата</a>
          <a href="/returns">Возврат товара</a>
        </div>
        <div class="footer-col">
          <h4>Мы в соцсетях</h4>
          <a href="https://t.me/boyforge" target="_blank" rel="noopener">Telegram</a>
        </div>
      </div>
      <div class="footer-bottom">
        <span>© <span id="year">2026</span> BOYFORGE. Все права защищены</span>
        <a href="/policy">Политика обработки персональных данных</a>
        <a href="/privacy">Политика конфиденциальности</a>
        <a href="/terms">Пользовательское соглашение</a>
        <a href="/offer">Публичная оферта</a>
      </div>
    </div>
  </footer>

  <script>
    function applyFilter(key, val) {
      const url = new URL(window.location.href);
      url.searchParams.set(key, val);
      window.location.href = url.toString();
    }
  </script>
  <script src="js/main.js?v=<?= filemtime(__DIR__ . '/../../js/main.js') ?>"></script>
<?php
$currentPage = basename($_SERVER['PHP_SELF']);
if ($currentPage === 'product.php') {
    echo '  <script src="/js/product.js?v=' . filemtime(__DIR__ . '/../../js/product.js') . '"></script>' . "\n";
    echo '  <script src="/js/checkout.js?v=' . filemtime(__DIR__ . '/../../js/checkout.js') . '"></script>' . "\n";
}
?>
</body>
</html>
