<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireAdminAuth();

$pageTitle = 'Заказы (Закрывающие чеки)';
$csrfToken = getCsrfToken();

$stmt = $pdo->query("
    SELECT * FROM orders 
    WHERE payment_status = 'paid' 
    ORDER BY created_at DESC 
    LIMIT 200
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$settingsFile = dirname(__DIR__) . '/includes/settings.json';
$tgEnabled = true;
$tgToken = '';
$tgChatId = '';
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    if (isset($settings['tg_notifications_enabled'])) {
        $tgEnabled = (bool)$settings['tg_notifications_enabled'];
    }
    $tgToken = $settings['tg_bot_token'] ?? '';
    $tgChatId = $settings['tg_chat_id'] ?? '';
}

require __DIR__ . '/includes/header.php';
?>

<style>
.cancelled-row td {
    position: relative;
    opacity: 0.5;
}
.cancelled-row td::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    width: 100%;
    height: 1px;
    background-color: #6b7280;
    pointer-events: none;
    z-index: 10;
}
#btnTgSettings .icon {
    transition: transform 0.6s ease-out;
}
#btnTgSettings:hover .icon {
    transform: rotate(720deg);
}
</style>

<div class="admin-main">
  <div class="admin-header-flex" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
    <h1 class="admin-title">Управление заказами и чеками</h1>
    <div style="display: flex; align-items: center; gap: 16px;">
        <button id="btnOpenReportModal" class="btn btn-secondary btn-sm" title="Выгрузка отчетов по заказам">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            Отчет по заказам
        </button>
        <button id="btnTgSettings" class="btn btn-secondary btn-sm" title="Настройки Telegram">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            Настройки
        </button>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 14px; font-weight: 500; color: #4b5563;">Уведомления в Telegram</span>
            <label class="switch-label" title="Включить/выключить уведомления">
                <input type="checkbox" id="tgNotificationToggle" <?= $tgEnabled ? 'checked' : '' ?>>
                <span class="slider"></span>
            </label>
        </div>
    </div>
  </div>

  <div class="admin-card">
    <div style="padding: 20px;">
        <p style="margin-bottom: 15px; color: #4b5563;">
            Здесь отображаются все оплаченные заказы. Для товаров, подлежащих маркировке (одежда), 
            перед передачей в 5Post необходимо пробить закрывающий чек («Полный расчет») с указанием кода Честного ЗНАКа.
        </p>
    </div>

    <?php if (empty($orders)): ?>
      <div style="padding: 40px; text-align: center; color: #6b7280;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 16px; opacity: 0.5;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <h3>Оплаченных заказов пока нет</h3>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Дата и Заказ</th>
              <th>Покупатель</th>
              <th>Товар</th>
              <th>Доставка 5Post</th>
              <th>Закрывающий чек (Маркировка)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
              <?php $isCancelled = ($order['mark_code'] === '__CANCELLED__'); ?>
              <tr id="order-row-<?= htmlspecialchars($order['order_id']) ?>" class="<?= $isCancelled ? 'cancelled-row' : '' ?>">
                <td>
                  <div style="font-weight:600; margin-bottom:4px;"><?= htmlspecialchars($order['order_id']) ?></div>
                  <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($order['created_at']) ?></div>
                </td>
                <td>
                  <div><?= htmlspecialchars($order['fio']) ?></div>
                  <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($order['phone']) ?></div>
                </td>
                <td>
                  <div style="font-weight:500;"><?= htmlspecialchars($order['product_name']) ?></div>
                  <div style="font-size:12px; color:#6b7280;">
                    <?= htmlspecialchars($order['size']) ?> — <?= number_format((float)$order['price'], 0, '', ' ') ?> ₽
                  </div>
                </td>
                <td>
                  <?php if (!empty($order['fivepost_barcode'])): ?>
                    <div style="font-weight:600; color:#10b981;"><?= htmlspecialchars($order['fivepost_barcode']) ?></div>
                    <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($order['fivepost_point_address']) ?></div>
                  <?php else: ?>
                    <span style="color:#ef4444; font-size:13px;">Ошибка 5Post</span>
                  <?php endif; ?>
                </td>
                <td style="min-width: 320px;">
                  <?php if ($order['receipt_sent'] == 1): ?>
                    <div style="display:inline-flex; align-items:center; gap:6px; color:#10b981; font-size:14px; font-weight:500;">
                        <?= renderSvgIcon('check', 'icon') ?> Чек пробит
                    </div>
                    <?php if (!empty($order['mark_code'])): ?>
                        <div style="font-size:11px; color:#6b7280; margin-top:4px; word-break: break-all; max-width: 250px;">
                            <?= htmlspecialchars($order['mark_code']) ?>
                        </div>
                    <?php endif; ?>
                  <?php elseif ($isCancelled): ?>
                    <div style="color:#ef4444; font-size:14px; font-weight:500;">
                        Отменено
                    </div>
                  <?php else: ?>
                    <div class="receipt-form-container">
                        <div style="display:flex; gap: 8px; margin-bottom: 8px;">
                            <input type="text" class="form-input mark-input" placeholder="Пикните сканером код маркировки" style="font-family: monospace; font-size:12px; flex: 1;" data-order="<?= htmlspecialchars($order['order_id']) ?>">
                            <button type="button" class="btn btn-danger btn-sm cancel-order-btn" data-order="<?= htmlspecialchars($order['order_id']) ?>">
                                Отменить
                            </button>
                        </div>
                        <button type="button" class="btn-primary btn-sm send-receipt-btn" data-order="<?= htmlspecialchars($order['order_id']) ?>" style="width: 100%;">
                            Отправить чек
                        </button>
                        <div class="receipt-status" style="font-size: 12px; margin-top: 6px; display: none;"></div>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="cancelModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:24px; border-radius:8px; width:320px; text-align:center; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
        <h3 style="margin-top:0; margin-bottom:16px; font-size:18px;">Вы точно хотите сделать отмену заказа?</h3>
        <div style="display:flex; gap:12px; justify-content:center;">
            <button id="cancelModalYes" class="btn btn-danger">Да</button>
            <button id="cancelModalNo" class="btn btn-secondary">Отмена</button>
        </div>
    </div>
</div>

<div id="tgSettingsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:32px; border-radius:8px; width:520px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
        <h3 style="margin-top:0; margin-bottom:16px; font-size:18px;">Секреты Telegram</h3>
        <p style="font-size:13px; color:#6b7280; margin-bottom: 16px;">Укажите данные, чтобы уведомления приходили в нужный вам чат/группу. При пустых полях будут использоваться дефолтные из .env.</p>
        
        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label">Telegram Bot Token (HTTP API):</label>
            <input type="text" id="tgBotTokenInput" class="form-input" value="<?= htmlspecialchars($tgToken) ?>" placeholder="Например: 123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11">
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label">Telegram Chat ID (ID чата или канала):</label>
            <input type="text" id="tgChatIdInput" class="form-input" value="<?= htmlspecialchars($tgChatId) ?>" placeholder="Например: -1003895656779">
        </div>

        <div style="display:flex; gap:12px; justify-content:space-between; align-items:center; margin-top: 16px;">
            <button id="tgTestBtn" class="btn btn-secondary" style="font-size: 13px;">Отправить тест</button>
            <div style="display:flex; gap:12px;">
                <button id="tgSettingsClose" class="btn btn-secondary">Отмена</button>
                <button id="tgSettingsSave" class="btn btn-primary">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<div id="orderReportModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:32px; border-radius:12px; width:520px; max-width:92%; box-shadow:0 10px 25px rgba(0,0,0,0.15);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0; font-size:18px; font-weight:700;">Отчет по заказам</h3>
            <button type="button" id="orderReportCloseCross" style="font-size:24px; line-height:1; color:#9ca3af; cursor:pointer; background:none; border:none;" title="Закрыть">&times;</button>
        </div>
        <p style="font-size:13px; color:#6b7280; margin-bottom: 20px;">
            Выберите период для формирования отчета в Excel (.xlsx). Вы можете сразу скачать файл или отправить его в рабочий Telegram-чат.
        </p>

        <!-- Быстрые пресеты дат -->
        <div style="margin-bottom: 16px;">
            <label class="form-label" style="margin-bottom:8px; display:block; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280;">Быстрый выбор периода:</label>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm preset-date-btn" data-preset="today">Сегодня</button>
                <button type="button" class="btn btn-secondary btn-sm preset-date-btn" data-preset="yesterday">Вчера</button>
                <button type="button" class="btn btn-secondary btn-sm preset-date-btn" data-preset="week">7 дней</button>
                <button type="button" class="btn btn-secondary btn-sm preset-date-btn" data-preset="month">Этот месяц</button>
                <button type="button" class="btn btn-secondary btn-sm preset-date-btn" data-preset="all">За всё время</button>
            </div>
        </div>

        <!-- Выбор дат -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
            <div class="form-group">
                <label class="form-label" for="reportDateFrom">Дата с:</label>
                <input type="date" id="reportDateFrom" class="form-input" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="reportDateTo">Дата по:</label>
                <input type="date" id="reportDateTo" class="form-input" value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <!-- Статус заказов -->
        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label" for="reportStatusSelect">Статус заказов:</label>
            <select id="reportStatusSelect" class="form-input">
                <option value="paid" selected>Только оплаченные (без отмен)</option>
                <option value="all">Все заказы</option>
                <option value="cancelled">Только отмененные</option>
            </select>
        </div>

        <!-- Статус отправки / уведомление -->
        <div id="reportActionStatus" style="display:none; padding:10px 14px; border-radius:8px; margin-bottom:18px; font-size:13px;"></div>

        <!-- Действия -->
        <div style="display:flex; gap:10px; justify-content:space-between; align-items:center; flex-wrap:wrap;">
            <button type="button" id="orderReportClose" class="btn btn-secondary">Закрыть</button>
            <div style="display:flex; gap:10px;">
                <button type="button" id="btnSendReportTg" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px;">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    <span>В Telegram</span>
                </button>
                <button type="button" id="btnDownloadReportExcel" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:6px;">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <span>Скачать Excel</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tgToggle = document.getElementById('tgNotificationToggle');
    if (tgToggle) {
        tgToggle.addEventListener('change', function() {
            fetch('api_tg_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled: this.checked })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert('Ошибка при сохранении настроек: ' + (data.error || 'Неизвестная ошибка'));
                    this.checked = !this.checked;
                } else {
                    document.getElementById('tgTestBtn').disabled = !this.checked;
                }
            })
            .catch(err => {
                alert('Сетевая ошибка при сохранении настроек');
                this.checked = !this.checked;
            });
        });
    }

    const tgSettingsModal = document.getElementById('tgSettingsModal');
    const btnTgSettings = document.getElementById('btnTgSettings');
    const tgSettingsClose = document.getElementById('tgSettingsClose');
    const tgSettingsSave = document.getElementById('tgSettingsSave');
    const tgTestBtn = document.getElementById('tgTestBtn');

    if (tgToggle && tgTestBtn) {
        tgTestBtn.disabled = !tgToggle.checked;
    }

    if (tgTestBtn) {
        tgTestBtn.addEventListener('click', function() {
            if (tgToggle && !tgToggle.checked) {
                alert('Включите уведомления (тумблер) перед отправкой теста!');
                return;
            }
            const originalText = this.textContent;
            this.textContent = 'Отправка...';
            this.disabled = true;

            fetch('api_tg_test.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Тестовое сообщение успешно отправлено!');
                } else {
                    alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                }
            })
            .catch(err => alert('Сетевая ошибка'))
            .finally(() => {
                this.textContent = originalText;
                this.disabled = false;
            });
        });
    }

    if (btnTgSettings && tgSettingsModal) {
        btnTgSettings.addEventListener('click', () => {
            tgSettingsModal.style.display = 'flex';
        });

        tgSettingsClose.addEventListener('click', () => {
            tgSettingsModal.style.display = 'none';
        });

        tgSettingsSave.addEventListener('click', function() {
            const token = document.getElementById('tgBotTokenInput').value.trim();
            const chatId = document.getElementById('tgChatIdInput').value.trim();
            
            const originalText = this.textContent;
            this.textContent = 'Сохранение...';
            this.disabled = true;

            fetch('api_tg_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tg_bot_token: token,
                    tg_chat_id: chatId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    tgSettingsModal.style.display = 'none';
                    alert('Настройки Telegram успешно сохранены!');
                } else {
                    alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                }
            })
            .catch(err => alert('Сетевая ошибка'))
            .finally(() => {
                this.textContent = originalText;
                this.disabled = false;
            });
        });
    }

    // --- Модальное окно выгрузки отчетов по заказам ---
    const orderReportModal = document.getElementById('orderReportModal');
    const btnOpenReportModal = document.getElementById('btnOpenReportModal');
    const orderReportClose = document.getElementById('orderReportClose');
    const orderReportCloseCross = document.getElementById('orderReportCloseCross');
    const reportDateFrom = document.getElementById('reportDateFrom');
    const reportDateTo = document.getElementById('reportDateTo');
    const reportStatusSelect = document.getElementById('reportStatusSelect');
    const reportActionStatus = document.getElementById('reportActionStatus');
    const btnDownloadReportExcel = document.getElementById('btnDownloadReportExcel');
    const btnSendReportTg = document.getElementById('btnSendReportTg');

    if (btnOpenReportModal && orderReportModal) {
        btnOpenReportModal.addEventListener('click', () => {
            if (reportActionStatus) {
                reportActionStatus.style.display = 'none';
            }
            orderReportModal.style.display = 'flex';
        });
    }

    const closeReportModal = () => {
        if (orderReportModal) {
            orderReportModal.style.display = 'none';
        }
    };

    if (orderReportClose) orderReportClose.addEventListener('click', closeReportModal);
    if (orderReportCloseCross) orderReportCloseCross.addEventListener('click', closeReportModal);

    // Пресеты дат
    const formatYmd = (d) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    document.querySelectorAll('.preset-date-btn').forEach(presetBtn => {
        presetBtn.addEventListener('click', function() {
            const preset = this.getAttribute('data-preset');
            const now = new Date();
            let from = new Date();
            let to = new Date();

            if (preset === 'today') {
                from = now;
                to = now;
            } else if (preset === 'yesterday') {
                from = new Date(now);
                from.setDate(now.getDate() - 1);
                to = new Date(from);
            } else if (preset === 'week') {
                from = new Date(now);
                from.setDate(now.getDate() - 6);
                to = now;
            } else if (preset === 'month') {
                from = new Date(now.getFullYear(), now.getMonth(), 1);
                to = now;
            } else if (preset === 'all') {
                from = new Date(2024, 0, 1);
                to = now;
            }

            if (reportDateFrom) reportDateFrom.value = formatYmd(from);
            if (reportDateTo) reportDateTo.value = formatYmd(to);
        });
    });

    // Скачивание Excel
    if (btnDownloadReportExcel) {
        btnDownloadReportExcel.addEventListener('click', function() {
            const from = reportDateFrom ? reportDateFrom.value : '';
            const to = reportDateTo ? reportDateTo.value : '';
            const status = reportStatusSelect ? reportStatusSelect.value : 'paid';

            if (!from || !to) {
                alert('Пожалуйста, выберите диапазон дат.');
                return;
            }

            const downloadUrl = 'api_export_orders.php?action=download&date_from=' + encodeURIComponent(from) + '&date_to=' + encodeURIComponent(to) + '&status=' + encodeURIComponent(status);
            window.location.href = downloadUrl;
        });
    }

    // Отправка в Telegram
    if (btnSendReportTg) {
        btnSendReportTg.addEventListener('click', function() {
            const from = reportDateFrom ? reportDateFrom.value : '';
            const to = reportDateTo ? reportDateTo.value : '';
            const status = reportStatusSelect ? reportStatusSelect.value : 'paid';

            if (!from || !to) {
                alert('Пожалуйста, выберите диапазон дат.');
                return;
            }

            const originalHtml = this.innerHTML;
            this.innerHTML = '<span>Отправка...</span>';
            this.disabled = true;

            if (reportActionStatus) {
                reportActionStatus.style.display = 'block';
                reportActionStatus.style.background = '#f1f5f9';
                reportActionStatus.style.color = '#475569';
                reportActionStatus.style.border = '1px solid #e2e8f0';
                reportActionStatus.textContent = 'Формирование отчета и отправка в Telegram...';
            }

            const params = new URLSearchParams();
            params.append('action', 'telegram');
            params.append('date_from', from);
            params.append('date_to', to);
            params.append('status', status);

            fetch('api_export_orders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (reportActionStatus) {
                        reportActionStatus.style.display = 'block';
                        reportActionStatus.style.background = '#dcfce7';
                        reportActionStatus.style.color = '#15803d';
                        reportActionStatus.style.border = '1px solid #bbf7d0';
                        reportActionStatus.style.whiteSpace = 'pre-line';
                        reportActionStatus.textContent = data.message || 'Отчет успешно отправлен в Telegram!';
                    }
                } else {
                    if (reportActionStatus) {
                        reportActionStatus.style.display = 'block';
                        reportActionStatus.style.background = '#fee2e2';
                        reportActionStatus.style.color = '#b91c1c';
                        reportActionStatus.style.border = '1px solid #fecaca';
                        reportActionStatus.textContent = 'Ошибка: ' + (data.error || 'Не удалось отправить отчет в Telegram');
                    }
                }
            })
            .catch(err => {
                if (reportActionStatus) {
                    reportActionStatus.style.display = 'block';
                    reportActionStatus.style.background = '#fee2e2';
                    reportActionStatus.style.color = '#b91c1c';
                    reportActionStatus.style.border = '1px solid #fecaca';
                    reportActionStatus.textContent = 'Сетевая ошибка при отправке отчета.';
                }
            })
            .finally(() => {
                this.innerHTML = originalHtml;
                this.disabled = false;
            });
        });
    }

    const buttons = document.querySelectorAll('.send-receipt-btn');
    
    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order');
            const row = document.getElementById('order-row-' + orderId);
            const input = row.querySelector('.mark-input');
            const statusDiv = row.querySelector('.receipt-status');
            
            const markCode = input.value.trim();
            
            if (!markCode) {
                statusDiv.style.display = 'block';
                statusDiv.style.color = '#ef4444';
                statusDiv.textContent = 'Ошибка: отсканируйте код маркировки.';
                input.focus();
                return;
            }
            
            this.disabled = true;
            this.textContent = 'Отправка...';
            statusDiv.style.display = 'none';
            
            fetch('api_receipt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: orderId,
                    mark_code: markCode
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    row.querySelector('.receipt-form-container').innerHTML = `
                        <div style="display:inline-flex; align-items:center; gap:6px; color:#10b981; font-size:14px; font-weight:500;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> 
                            Чек пробит
                        </div>
                        <div style="font-size:11px; color:#6b7280; margin-top:4px; word-break: break-all; max-width: 250px;">
                            ${markCode.replace(/</g, '&lt;')}
                        </div>
                    `;
                } else {
                    this.disabled = false;
                    this.textContent = 'Отправить чек';
                    statusDiv.style.display = 'block';
                    statusDiv.style.color = '#ef4444';
                    statusDiv.textContent = data.error || 'Произошла ошибка при отправке.';
                }
            })
            .catch(err => {
                this.disabled = false;
                this.textContent = 'Отправить чек';
                statusDiv.style.display = 'block';
                statusDiv.style.color = '#ef4444';
                statusDiv.textContent = 'Сетевая ошибка.';
            });
        });
    });

    const cancelModal = document.getElementById('cancelModal');
    let orderToCancel = null;

    document.querySelectorAll('.cancel-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            orderToCancel = this.getAttribute('data-order');
            cancelModal.style.display = 'flex';
        });
    });

    document.getElementById('cancelModalNo').addEventListener('click', function() {
        cancelModal.style.display = 'none';
        orderToCancel = null;
    });

    document.getElementById('cancelModalYes').addEventListener('click', function() {
        if (!orderToCancel) return;
        
        const row = document.getElementById('order-row-' + orderToCancel);
        const btn = row.querySelector('.cancel-order-btn');
        const originalText = btn.textContent;
        btn.textContent = '...';
        btn.disabled = true;
        cancelModal.style.display = 'none';
        
        fetch('api_cancel_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderToCancel })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                row.classList.add('cancelled-row');
                const container = row.querySelector('.receipt-form-container');
                if (container) {
                    container.innerHTML = '<div style="color:#ef4444; font-size:14px; font-weight:500;">Отменено</div>';
                }
            } else {
                alert(data.error || 'Ошибка отмены');
                btn.textContent = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            alert('Сетевая ошибка');
            btn.textContent = originalText;
            btn.disabled = false;
        });
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
