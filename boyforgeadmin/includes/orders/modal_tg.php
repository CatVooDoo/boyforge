<?php
declare(strict_types=1);
/**
 * @var string $tgToken
 * @var string $tgChatId
 */
?>
<!-- Модалка настроек ТГ -->
<div id="tgSettingsModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 520px;">
        <svg id="tgSettingsCloseIcon" class="modal-close icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        <h3 style="margin-top:0; margin-bottom:16px; font-size:18px; font-family:var(--font-family); font-weight:800;">Секреты Telegram</h3>
        <p style="font-size:13px; color:var(--text-muted); margin-bottom: 16px;">Укажите данные бота и канала. Все уведомления маршрутизируются через Cloudflare Worker.</p>
        
        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label">Telegram Bot Token (HTTP API):</label>
            <input type="text" id="tgBotTokenInput" class="form-input" value="<?= htmlspecialchars($tgToken) ?>" placeholder="123456:ABC-DEF...">
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label">Telegram Chat ID (ID чата или канала):</label>
            <input type="text" id="tgChatIdInput" class="form-input" value="<?= htmlspecialchars($tgChatId) ?>" placeholder="-1003895656779">
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
