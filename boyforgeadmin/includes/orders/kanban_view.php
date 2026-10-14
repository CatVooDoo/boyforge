<?php
declare(strict_types=1);
/**
 * @var array $stagesData
 */
?>
<div id="ordersKanbanView" class="kanban-board-wrap" style="display:none;">
    <div class="kanban-board">
        <?php foreach ($stagesData as $stageKey => $stage): ?>
            <div class="kanban-col" data-col-stage="<?= $stageKey ?>">
                <div class="kanban-col-header">
                    <div class="col-title-group">
                        <span class="col-dot" style="background-color: <?= $stage['color'] ?>;"></span>
                        <span class="col-title"><?= htmlspecialchars($stage['title']) ?></span>
                        <span class="col-badge" id="badge-<?= $stageKey ?>"><?= count($stage['orders']) ?></span>
                    </div>
                    <div class="col-sum" id="sum-<?= $stageKey ?>"><?= number_format($stage['sum'], 0, '', ' ') ?> ₽</div>
                </div>

                <div class="kanban-cards-container" id="colCards-<?= $stageKey ?>">
                    <?php foreach ($stage['orders'] as $order): ?>
                        <?php 
                            $orderJson = htmlspecialchars(json_encode($order, JSON_UNESCAPED_UNICODE));
                            $barcode = $order['fivepost_barcode'] ?: '';
                            $addr = $order['fivepost_point_address'] ?: '';
                            $timeStr = date('d M, H:i', strtotime($order['created_at']));
                            if (date('Y-m-d') === date('Y-m-d', strtotime($order['created_at']))) {
                                $timeStr = 'Сегодня ' . date('H:i', strtotime($order['created_at']));
                            }
                        ?>
                        <div class="k-card <?= ($stageKey === 'cancelled') ? 'cancelled' : '' ?>" 
                             data-order='<?= $orderJson ?>' 
                             data-stage="<?= $stageKey ?>"
                             data-payment="<?= htmlspecialchars($order['payment_status']) ?>">
                            
                            <div class="k-card-top">
                                <span class="k-card-id"><?= htmlspecialchars($order['order_id']) ?></span>
                                <span class="k-card-time"><?= $timeStr ?></span>
                            </div>

                            <div class="k-card-cust">
                                <div class="k-card-name"><?= htmlspecialchars($order['fio'] ?: 'Покупатель') ?></div>
                                <div class="k-card-phone">
                                    <span><?= htmlspecialchars($order['phone']) ?></span>
                                    <?php if (!empty($order['phone'])): ?>
                                        <button type="button" class="copy-btn-inline" data-copy="<?= htmlspecialchars($order['phone']) ?>" title="Копировать номер" style="background:none; border:none; padding:0; cursor:pointer; color:var(--text-dim);">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="k-card-prod">
                                <span class="k-card-prod-title" title="<?= htmlspecialchars($order['product_name']) ?>"><?= htmlspecialchars($order['product_name']) ?></span>
                                <span class="k-card-prod-size"><?= htmlspecialchars($order['size'] ?: '—') ?></span>
                            </div>

                            <?php if (!empty($order['fivepost_status']) || $barcode || $addr): ?>
                                <div class="k-card-delivery">
                                    <div class="k-card-delivery-row">
                                        <?= getDeliveryBadge($order['fivepost_status']) ?>
                                        <?php if ($barcode): ?>
                                            <span class="k-barcode-chip" title="Штрихкод 5Post">
                                                <?= htmlspecialchars($barcode) ?>
                                                <button type="button" class="copy-btn-inline" data-copy="<?= htmlspecialchars($barcode) ?>" title="Копировать трек" style="background:none; border:none; padding:0; cursor:pointer; color:#059669;">
                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                                </button>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($addr): ?>
                                        <div class="k-card-address" title="<?= htmlspecialchars($addr) ?>">
                                            📍 <?= htmlspecialchars($addr) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="k-card-footer">
                                <span class="k-card-price"><?= number_format((float)$order['price'], 0, '', ' ') ?> ₽</span>
                                <div>
                                    <?= getPaymentBadge($order['payment_status'], $order['mark_code']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
