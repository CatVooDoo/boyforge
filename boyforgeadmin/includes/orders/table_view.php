<?php
declare(strict_types=1);
/**
 * @var array $orders
 */
?>
<div id="ordersTableView" class="orders-table-container">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 140px;">ID Заказа</th>
                    <th style="width: 130px;">Дата</th>
                    <th>Покупатель</th>
                    <th>Товар и размер</th>
                    <th style="width: 170px;">Доставка (5Post)</th>
                    <th style="width: 120px;">Сумма</th>
                    <th style="width: 140px;">Оплата</th>
                </tr>
            </thead>
            <tbody id="ordersTableBody">
                <?php foreach ($orders as $order): ?>
                    <?php 
                        $stage = getOrderStage($order);
                        $isCancelled = ($stage === 'cancelled'); 
                        $orderJson = htmlspecialchars(json_encode($order, JSON_UNESCAPED_UNICODE));
                        $barcode = $order['fivepost_barcode'] ?: '';
                    ?>
                    <tr class="order-row <?= $isCancelled ? 'cancelled' : '' ?>" 
                        data-order='<?= $orderJson ?>' 
                        data-stage="<?= $stage ?>"
                        data-payment="<?= htmlspecialchars($order['payment_status']) ?>">
                        <td>
                            <div style="font-family: var(--font-family); font-weight: 700; font-size: 13px; color: #000;">
                                <?= htmlspecialchars($order['order_id']) ?>
                            </div>
                        </td>
                        <td style="white-space: nowrap; font-size:12px; color:var(--text-muted);">
                            <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
                        </td>
                        <td>
                            <div style="font-weight:600; color:#111;"><?= htmlspecialchars($order['fio'] ?: 'Покупатель') ?></div>
                            <div style="font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:4px; margin-top:2px;">
                                <span><?= htmlspecialchars($order['phone']) ?></span>
                                <?php if (!empty($order['phone'])): ?>
                                    <button type="button" class="copy-btn-inline" data-copy="<?= htmlspecialchars($order['phone']) ?>" title="Копировать номер" style="background:none; border:none; padding:0; cursor:pointer; color:var(--text-dim);">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600; color:#111;"><?= htmlspecialchars($order['product_name']) ?></div>
                            <div style="margin-top:2px;">
                                <span style="font-size:11px; font-weight:600; background:var(--bg-main); border:1px solid var(--border-color); padding:1px 6px; border-radius:4px; color:var(--text-muted);">
                                    <?= htmlspecialchars($order['size'] ?: '—') ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                                <?= getDeliveryBadge($order['fivepost_status']) ?>
                                <?php if ($barcode): ?>
                                    <div class="k-barcode-chip" title="Штрихкод 5Post">
                                        <span><?= htmlspecialchars($barcode) ?></span>
                                        <button type="button" class="copy-btn-inline" data-copy="<?= htmlspecialchars($barcode) ?>" title="Копировать трек-номер" style="background:none; border:none; padding:0; cursor:pointer; color:#059669;">
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        </button>
                                    </div>
                                <?php elseif (!empty($order['fivepost_point_address'])): ?>
                                    <span style="font-size:11px; color:var(--text-muted); max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($order['fivepost_point_address']) ?>">
                                        <?= htmlspecialchars($order['fivepost_point_address']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="price-cell" style="font-family: var(--font-family); font-weight:800; font-size:14px;">
                            <?= number_format((float)$order['price'], 0, '', ' ') ?> ₽
                        </td>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                                <?= getPaymentBadge($order['payment_status'], $order['mark_code']) ?>
                                <?php if ($order['receipt_sent'] == 1): ?>
                                    <span style="font-size:10px; font-weight:700; color:#10b981; display:flex; align-items:center; gap:2px;">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg> Чек пробит
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 48px; color: var(--text-muted);">
                            Заказов пока нет
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
