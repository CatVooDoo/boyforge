<?php
declare(strict_types=1);
/**
 * @var array $kpi
 */
?>
<div class="crm-control-bar">
    <div class="control-top-row">
        <div class="control-left-group">
            <!-- View Switcher Tabs -->
            <div class="view-switch-tabs" role="tablist">
                <button type="button" class="view-tab-btn active" id="tabViewTable" title="Табличный вид списка">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    Список
                </button>
                <button type="button" class="view-tab-btn" id="tabViewKanban" title="Канбан-доска стадий">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="18" rx="1"></rect><rect x="14" y="3" width="7" height="11" rx="1"></rect></svg>
                    Канбан
                </button>
            </div>

            <!-- Live Search -->
            <div class="crm-search-box">
                <svg class="crm-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="orderSearch" class="crm-search-input" placeholder="Поиск по номеру заказа, ФИО, телефону, товару или треку 5Post…">
                <button type="button" id="searchClearBtn" class="crm-search-clear">&times;</button>
            </div>
        </div>

        <div style="font-size:12px; color:var(--text-muted); font-weight:600;" id="searchStatsText">
            Показано заказов: <span id="visibleCount" style="color:#000;"><?= $kpi['totalOrders'] ?></span>
        </div>
    </div>

    <!-- Filter Chips -->
    <div class="filter-chips-row">
        <button type="button" class="filter-chip active" data-filter="all">
            Все заказы <span class="chip-cnt"><?= $kpi['totalOrders'] ?></span>
        </button>
        <button type="button" class="filter-chip" data-filter="to_ship">
            К отправке <span class="chip-cnt"><?= $kpi['toShipCount'] ?></span>
        </button>
        <button type="button" class="filter-chip" data-filter="in_transit">
            В пути 5Post <span class="chip-cnt"><?= $kpi['inTransitCount'] ?></span>
        </button>
        <button type="button" class="filter-chip" data-filter="ready_pickup">
            В пункте выдачи <span class="chip-cnt"><?= $kpi['readyPickupCount'] ?></span>
        </button>
        <button type="button" class="filter-chip" data-filter="paid">
            Оплаченные <span class="chip-cnt"><?= $kpi['paidCount'] ?></span>
        </button>
        <button type="button" class="filter-chip" data-filter="manual">
            Ручные <span class="chip-cnt"><?= $kpi['manualCount'] ?></span>
        </button>
        <button type="button" class="filter-chip" data-filter="pending">
            Ожидают оплаты <span class="chip-cnt"><?= $kpi['pendingCount'] ?></span>
        </button>
        <button type="button" class="filter-chip" data-filter="cancelled">
            Отмененные <span class="chip-cnt"><?= $kpi['cancelledCount'] ?></span>
        </button>
    </div>
</div>
