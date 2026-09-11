<?php
declare(strict_types=1);

$pageTitle = 'Логирование 5Post API';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/fivepost.php';

$logFile = __DIR__ . '/../logs/5post.log';
$fpClient = new FivePostClient();
$envVars = FivePostClient::loadEnv();

$action = $_GET['action'] ?? '';
$feedbackMsg = null;
$feedbackType = 'info';

// Обработка действий
if ($action === 'clear' && checkCsrfToken($_GET['csrf_token'] ?? '')) {
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    header('Location: logs.php?cleared=1');
    exit;
}

if ($action === 'download') {
    if (file_exists($logFile)) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="5post_' . date('Y-m-d_H-i-s') . '.log"');
        readfile($logFile);
        exit;
    }
}

$testResult = null;
if (isset($_POST['test_api_key']) && checkCsrfToken($_POST['csrf_token'] ?? '')) {
    $testResult = $fpClient->testConnection();
}

$logContent = file_exists($logFile) ? (string)file_get_contents($logFile) : '';
$logSize = file_exists($logFile) ? filesize($logFile) : 0;
$logSizeFormatted = $logSize > 1048576 ? round($logSize / 1048576, 2) . ' МБ' : round($logSize / 1024, 1) . ' КБ';

// Разбиваем лог на отдельные блоки
$blocks = [];
if (!empty($logContent)) {
    $rawBlocks = explode(str_repeat('=', 80), $logContent);
    foreach ($rawBlocks as $b) {
        $b = trim($b);
        if (empty($b)) continue;
        
        $type = 'info';
        if (strpos($b, 'HTTP_Code: 401') !== false || strpos($b, 'ABORTED') !== false || strpos($b, 'Error') !== false) {
            $type = 'error';
        } elseif (strpos($b, 'HTTP_Code: 200') !== false || strpos($b, 'CREATED') !== false) {
            $type = 'success';
        } elseif (strpos($b, 'REQUEST') !== false) {
            $type = 'request';
        }

        $blocks[] = [
            'type' => $type,
            'text' => $b
        ];
    }
}
$blocks = array_reverse($blocks); // Новые записи сверху
?>

<div class="page-head">
  <div>
    <h1 class="page-title">Логи 5Post API & Webhooks</h1>
    <div class="page-subtitle">Сквозной мониторинг всех запросов авторизации, регистрации заказов C2C и ответов шлюза X5</div>
  </div>
  <div class="head-actions" style="display: flex; gap: 10px; align-items: center;">
    <form method="POST" style="margin:0;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <button type="submit" name="test_api_key" value="1" class="btn btn-primary btn-sm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
        Проверить статус API-ключа в 5Post
      </button>
    </form>

    <a href="logs.php?action=download" class="btn btn-secondary btn-sm" title="Скачать лог-файл целиком">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
      Скачать лог
    </a>

    <?php if ($logSize > 0): ?>
      <a href="logs.php?action=clear&csrf_token=<?= urlencode($csrfToken) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Вы уверены, что хотите очистить журнал логов?');" title="Очистить лог-файл">
        <?= renderSvgIcon('trash') ?>
        Очистить
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (isset($_GET['cleared'])): ?>
  <div class="alert alert-success" style="margin-bottom: 20px; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:12px 16px; border-radius:8px;">
    ✓ Журнал логов 5Post успешно очищен.
  </div>
<?php endif; ?>

<!-- Результат проверки API -->
<?php if ($testResult !== null): ?>
  <div class="content-card" style="margin-bottom: 24px; border-left: 4px solid <?= $testResult['is_valid'] ? '#10b981' : '#f59e0b' ?>;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
      <h3 style="font-size:16px; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;">
        <?php if ($testResult['is_valid']): ?>
          <span style="color:#10b981;">●</span> API 5Post подключен успешно (HTTP 200)
        <?php else: ?>
          <span style="color:#f59e0b;">●</span> Ответ шлюза X5: HTTP <?= htmlspecialchars((string)$testResult['http_code']) ?>
        <?php endif; ?>
      </h3>
      <span style="font-size:12px; color:var(--text-muted); font-family:monospace;">
        Время отклика: <?= $testResult['duration_s'] ?> сек
      </span>
    </div>

    <div style="font-size:13px; line-height:1.6; margin-bottom: 14px;">
      <?php if ($testResult['is_valid']): ?>
        <p style="color:#065f46; margin:0;">
          <strong>Отлично!</strong> Ключ активен на шлюзе X5 Gravitee. Авторизационный Bearer токен успешно сгенерирован. Все заказы покупателей регистрируются в 5Post в режиме реального времени.
        </p>
      <?php elseif ($testResult['http_code'] === 401): ?>
        <p style="color:#92400e; margin:0;">
          <strong>Обратите внимание:</strong> Шлюз X5 отклонил ключ с сообщением: <code>"API Key is not valid or is expired / revoked"</code>.<br>
          Это стандартная ситуация до того, как менеджер 5Post активирует ключ в личном кабинете партнёра. <br>
          <strong>Как это работает прямо сейчас:</strong> код интернет-магазина полностью готов и отправляет все C2C-заказы в 5Post. При 401 ответе система мягко фиксирует заказ в MariaDB со статусом <code>PENDING_REGISTRATION</code>, формирует трек-код <code>5P-...</code>, передаёт всё в Google Таблицу и покупателю без сбоев. Как только менеджер активирует ключ — заказы сразу получат статус <code>CREATED</code> и официальный штрихкод 5Post.
        </p>
      <?php else: ?>
        <p style="color:#b91c1c; margin:0;">
          Ошибка соединения: <?= htmlspecialchars($testResult['curl_error'] ?: 'Код ответа ' . $testResult['http_code']) ?>
        </p>
      <?php endif; ?>
    </div>

    <div style="background:#18181b; color:#e4e4e7; border-radius:8px; padding:12px 16px; font-family:monospace; font-size:12px; overflow-x:auto;">
      <div style="color:#a1a1aa; margin-bottom:4px;"># Запрос:</div>
      <div>POST <?= htmlspecialchars($testResult['endpoint']) ?></div>
      <div style="color:#a1a1aa; margin:8px 0 4px;"># Ответ сервера 5Post:</div>
      <div style="color:<?= $testResult['is_valid'] ? '#4ade80' : '#fbbf24' ?>;"><?= htmlspecialchars($testResult['raw']) ?></div>
    </div>
  </div>
<?php endif; ?>

<!-- Карточки параметров интеграции -->
<div class="stats-grid" style="margin-bottom: 24px;">
  <div class="stat-card">
    <div class="stat-info">
      <span class="stat-value" style="font-size:16px; font-family:monospace;"><?= strtoupper($envVars['5POST_ENV'] ?? 'prod') ?></span>
      <span class="stat-label">Среда 5Post (Шлюз)</span>
      <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">
        <?= ($envVars['5POST_ENV'] ?? 'prod') === 'preprod' ? 'https://api-preprod-omni.x5.ru' : 'https://api-omni.x5.ru' ?>
      </div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <span class="stat-value" style="font-size:14px; font-family:monospace;">
        <?= substr($envVars['5POST_API_KEY'] ?? '', 0, 8) ?>...<?= substr($envVars['5POST_API_KEY'] ?? '', -4) ?>
      </span>
      <span class="stat-label">Ключ API (5POST_API_KEY)</span>
      <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Задаётся в файле .env</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <span class="stat-value" style="font-size:16px;"><?= count($blocks) ?></span>
      <span class="stat-label">Записей в логе</span>
      <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Общий размер: <?= $logSizeFormatted ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-info">
      <span class="stat-value" style="font-size:15px; color:#10b981;">Раздел 18</span>
      <span class="stat-label">Стандарт интеграции</span>
      <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">C2C Заказы и тарифы</div>
    </div>
  </div>
</div>

<!-- Фильтры логов -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px; flex-wrap:wrap; gap:10px;">
  <div style="display:flex; gap:8px;">
    <button type="button" class="btn btn-sm filter-btn active" onclick="filterLogs('all', this)">Все события (<?= count($blocks) ?>)</button>
    <button type="button" class="btn btn-sm filter-btn" onclick="filterLogs('error', this)">Ошибки и 401</button>
    <button type="button" class="btn btn-sm filter-btn" onclick="filterLogs('request', this)">Запросы C2C / JWT</button>
    <button type="button" class="btn btn-sm filter-btn" onclick="filterLogs('success', this)">Успешные (200 OK)</button>
  </div>
  
  <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-muted);">
    <input type="checkbox" id="autoRefreshToggle" onchange="toggleAutoRefresh(this)" style="cursor:pointer;">
    <label for="autoRefreshToggle" style="cursor:pointer;">Автообновление (10с)</label>
  </div>
</div>

<!-- Список логов -->
<div class="content-card" style="padding: 0; overflow:hidden;">
  <?php if (empty($blocks)): ?>
    <div class="empty-state" style="padding: 50px 20px;">
      <div class="empty-icon"><?= renderSvgIcon('box') ?></div>
      <div class="empty-title">Журнал логов 5Post пуст</div>
      <div class="empty-desc">Запросы на получение токена, создание C2C заказов и вебхуки CloudPayments будут автоматически записываться сюда.</div>
    </div>
  <?php else: ?>
    <div id="logBlocksContainer" style="display:flex; flex-direction:column;">
      <?php foreach ($blocks as $idx => $block): 
          $badgeColor = '#6b7280';
          $badgeText = 'ИНФО';
          $badgeBg = '#f3f4f6';
          
          if ($block['type'] === 'error') {
              $badgeColor = '#991b1b';
              $badgeBg = '#fee2e2';
              $badgeText = 'ОШИБКА / 401';
          } elseif ($block['type'] === 'success') {
              $badgeColor = '#065f46';
              $badgeBg = '#d1fae5';
              $badgeText = '200 OK';
          } elseif ($block['type'] === 'request') {
              $badgeColor = '#1e40af';
              $badgeBg = '#dbeafe';
              $badgeText = 'ЗАПРОС';
          }
      ?>
        <div class="log-entry log-entry-<?= $block['type'] ?>" style="padding:16px 20px; border-bottom:1px solid var(--border-color); background: #ffffff;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <div style="display:flex; align-items:center; gap:10px;">
              <span class="badge" style="background:<?= $badgeBg ?>; color:<?= $badgeColor ?>; font-weight:700; font-size:11px; padding:3px 8px; border-radius:4px;">
                <?= $badgeText ?>
              </span>
              <span style="font-family:monospace; font-size:12px; color:var(--text-muted);">
                #<?= count($blocks) - $idx ?>
              </span>
            </div>
            <button type="button" class="btn btn-sm" style="font-size:11px; padding:2px 8px; color:var(--text-muted); border:1px solid var(--border-color);" onclick="copyLogEntry(this)">
              Копировать
            </button>
          </div>
          
          <pre style="margin:0; font-family:'JetBrains Mono', Consolas, Monaco, monospace; font-size:12px; line-height:1.5; color:#18181b; background:#fafafa; border:1px solid #e5e5e5; border-radius:6px; padding:12px 14px; overflow-x:auto; white-space:pre-wrap; word-break:break-word;"><?= htmlspecialchars($block['text']) ?></pre>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<style>
.filter-btn {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  color: var(--text-main);
  transition: all 0.2s;
}
.filter-btn.active {
  background: #000000;
  color: #ffffff;
  border-color: #000000;
}
</style>

<script>
function filterLogs(type, btn) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const entries = document.querySelectorAll('.log-entry');
  entries.forEach(e => {
    if (type === 'all' || e.classList.contains('log-entry-' + type)) {
      e.style.display = 'block';
    } else {
      e.style.display = 'none';
    }
  });
}

function copyLogEntry(btn) {
  const pre = btn.closest('.log-entry').querySelector('pre');
  if (pre) {
    navigator.clipboard.writeText(pre.innerText).then(() => {
      const orig = btn.innerText;
      btn.innerText = 'Скопировано!';
      setTimeout(() => btn.innerText = orig, 1500);
    });
  }
}

let refreshTimer = null;
function toggleAutoRefresh(checkbox) {
  if (checkbox.checked) {
    refreshTimer = setInterval(() => {
      window.location.reload();
    }, 10000);
  } else if (refreshTimer) {
    clearInterval(refreshTimer);
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
