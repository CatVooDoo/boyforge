<?php
declare(strict_types=1);
/**
 * @var array $kpi
 * @var ?int $syncDiffMinutes
 * @var int $lastSyncTs
 */
?>
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-title">Всего заказов</span>
            <div class="kpi-icon-wrap">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            </div>
        </div>
        <div class="kpi-val"><?= number_format($kpi['totalOrders'], 0, '', ' ') ?></div>
        <div class="kpi-meta">
            <span>В базе магазина</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-title">Выручка (Оплачено)</span>
            <div class="kpi-icon-wrap" style="color: #10b981; background: rgba(16, 185, 129, 0.1);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
        </div>
        <div class="kpi-val"><?= number_format($kpi['totalRevenue'], 0, '', ' ') ?> ₽</div>
        <div class="kpi-meta">
            <span style="color: #10b981; font-weight:600;">Успешные и ручные оплаты</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-title">К отправке / Сборка</span>
            <div class="kpi-icon-wrap" style="color: #2563eb; background: rgba(37, 99, 235, 0.1);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
            </div>
        </div>
        <div class="kpi-val"><?= $kpi['toShipCount'] ?></div>
        <div class="kpi-meta">
            <span>Оплачены, ждут отправку в 5Post</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-title">В доставке 5Post</span>
            <div class="kpi-icon-wrap" style="color: #7c3aed; background: rgba(124, 58, 237, 0.1);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polyline></svg>
            </div>
        </div>
        <div class="kpi-val"><?= ($kpi['inTransitCount'] + $kpi['readyPickupCount']) ?></div>
        <div class="kpi-meta" id="kpi5postMeta">
            <?php if ($lastSyncTs > 0): ?>
                <span>Синхронизировано <?= ($syncDiffMinutes === 0 ? 'только что' : "{$syncDiffMinutes} мин назад") ?></span>
            <?php else: ?>
                <span>Синхронизация активна</span>
            <?php endif; ?>
        </div>
    </div>
</div>
