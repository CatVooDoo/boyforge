<?php
declare(strict_types=1);
?>
<!-- Модальное окно просмотра заказа -->
<div id="orderDetailModal" class="modal-overlay">
    <div class="modal-content">
        <svg id="closeDetailModal" class="modal-close icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        
        <div style="margin-bottom: 20px;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
                <h2 id="mdlOrderId" style="font-family: var(--font-family); font-size: 20px; font-weight: 800; margin:0;">Заказ #...</h2>
                <span id="mdlStageBadge" class="status-badge">...</span>
            </div>
            <div id="mdlOrderDate" style="font-size: 12px; color: var(--text-muted);">...</div>
        </div>

        <!-- Визуальный таймлайн стадий заказа -->
        <div class="order-lifecycle" id="mdlLifecycleBar">
            <div class="lifecycle-step" id="step-created">
                <div class="step-circle">1</div>
                <div class="step-label">Создан</div>
            </div>
            <div class="lifecycle-step" id="step-paid">
                <div class="step-circle">2</div>
                <div class="step-label">Оплачен</div>
            </div>
            <div class="lifecycle-step" id="step-shipped">
                <div class="step-circle">3</div>
                <div class="step-label">В 5Post</div>
            </div>
            <div class="lifecycle-step" id="step-pickup">
                <div class="step-circle">4</div>
                <div class="step-label">В ПВЗ</div>
            </div>
            <div class="lifecycle-step" id="step-done">
                <div class="step-circle">5</div>
                <div class="step-label">Вручен</div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-section-title">Данные клиента</div>
            <div class="info-grid">
                <div class="info-item"><div class="lbl">ФИО</div><div class="val" id="mdlFio"></div></div>
                <div class="info-item">
                    <div class="lbl">Телефон</div>
                    <div class="val" style="display:flex; align-items:center; gap:6px;">
                        <span id="mdlPhone"></span>
                        <button type="button" id="mdlCopyPhoneBtn" style="background:none; border:none; cursor:pointer; color:var(--text-dim);" title="Копировать телефон">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="info-item"><div class="lbl">Email</div><div class="val" id="mdlEmail"></div></div>
                <div class="info-item"><div class="lbl">Telegram</div><div class="val" id="mdlTg"></div></div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-section-title">Товар и расчет</div>
            <div class="info-grid">
                <div class="info-item"><div class="lbl">Название</div><div class="val" id="mdlProduct"></div></div>
                <div class="info-item"><div class="lbl">Сумма</div><div class="val" id="mdlPrice" style="font-family: var(--font-family); font-weight:800; color:#000;"></div></div>
                <div class="info-item"><div class="lbl">Размер</div><div class="val" id="mdlSize"></div></div>
                <div class="info-item"><div class="lbl">Статус платежа</div><div class="val" id="mdlPaymentStatus"></div></div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-section-title">Доставка 5Post</div>
            <div class="info-grid" style="margin-bottom: 12px;">
                <div class="info-item" style="grid-column: span 2;"><div class="lbl">Адрес ПВЗ</div><div class="val" id="mdl5pAddress"></div></div>
                <div class="info-item"><div class="lbl">ID Точки (UUID)</div><div class="val" id="mdl5pPointId" style="font-family:monospace; font-size:12px;"></div></div>
                <div class="info-item"><div class="lbl">ID Заказа (5Post)</div><div class="val" id="mdl5pOrderId" style="font-family:monospace; font-size:12px;"></div></div>
                <div class="info-item">
                    <div class="lbl">Штрихкод / Трек-номер</div>
                    <div class="val" style="display:flex; align-items:center; gap:6px;">
                        <span id="mdl5pBarcode" style="color:#059669; font-weight:700; font-family:monospace;"></span>
                        <button type="button" id="mdlCopyBarcodeBtn" style="background:none; border:none; cursor:pointer; color:#059669;" title="Копировать трек">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="info-item"><div class="lbl">Статус 5Post</div><div class="val" id="mdl5pStatus"></div></div>
            </div>
            <div id="mdlTrackingLinkWrap" style="display:none; margin-top:8px;">
                <a id="mdlTrackingLink" href="#" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:6px; font-size:12px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    Открыть на fivepost.ru
                </a>
            </div>
        </div>

        <div class="modal-section" id="mdlActionsSection">
            <div class="modal-section-title">Действия с заказом</div>
            
            <div id="receiptContainer" style="display:none; background: #fafaf8; padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 16px;">
                <div style="font-size: 12px; font-weight: 700; margin-bottom: 8px;">Маркировка и закрывающий чек 54-ФЗ</div>
                <div style="display:flex; gap: 8px;">
                    <input type="text" id="mdlMarkCode" class="form-input" placeholder="Пикните сканером код маркировки (Честный ЗНАК)" style="font-family: monospace; font-size:12px; flex: 1;">
                    <button id="btnSendReceipt" class="btn btn-primary btn-sm">Отправить чек</button>
                </div>
            </div>

            <div style="display:flex; gap: 12px; flex-wrap: wrap;">
                <button id="btnManual5Post" class="btn btn-secondary btn-sm" style="display:none; color:#10b981; border-color:#10b981; font-weight:700;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Отправить в 5Post вручную
                </button>
                <button id="btnCancelOrder" class="btn btn-danger btn-sm" style="display:none;">
                    Отменить заказ
                </button>
            </div>
        </div>
    </div>
</div>
