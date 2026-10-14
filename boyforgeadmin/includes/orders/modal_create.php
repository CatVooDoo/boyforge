<?php
declare(strict_types=1);
/**
 * @var array $productsData
 */
?>
<!-- Модальное окно создания ручного заказа -->
<div id="createManualModal" class="modal-overlay">
    <div class="modal-content">
        <svg id="closeCreateModal" class="modal-close icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        
        <h2 style="font-family: var(--font-family); font-size: 20px; font-weight: 800; margin-bottom: 24px;">Создание ручного заказа 5Post</h2>
        
        <form id="createManualForm">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">ФИО клиента *</label>
                    <input type="text" id="crFio" class="form-input" required placeholder="Иван Иванов">
                </div>
                <div class="form-group">
                    <label class="form-label">Телефон *</label>
                    <input type="text" id="crPhone" class="form-input" placeholder="+7..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="crEmail" class="form-input" placeholder="client@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Название товара *</label>
                    <select id="crProduct" class="form-input" required>
                        <option value="">Выберите товар...</option>
                        <?php foreach ($productsData as $prod): ?>
                            <option value="<?= htmlspecialchars($prod['name']) ?>" data-id="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Пол *</label>
                    <select id="crGender" class="form-input" required disabled>
                        <option value="">Сначала выберите товар</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Размер *</label>
                    <select id="crSize" class="form-input" required disabled>
                        <option value="">Сначала выберите пол</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Сумма (₽) *</label>
                    <input type="number" id="crPrice" class="form-input" value="0" required>
                </div>
            </div>
            <div class="form-group" style="margin-top: 14px;">
                <label class="form-label">Выбор точки 5Post на карте *</label>
                
                <input type="hidden" id="crPointId" required>
                <input type="hidden" id="crAddress" required>
                <input type="hidden" id="crPointType">
                <input type="hidden" id="crPointName">
                <input type="hidden" id="crPointDetails">

                <div class="fivepost-selected-card" id="adminFivepostSelectedCard" style="display:none; margin-bottom: 12px;">
                    <div class="fivepost-selected-details">
                        <div class="fivepost-selected-header">
                            <strong class="fivepost-selected-name" id="adminFivepostPointNameDisplay">Пятёрочка</strong>
                        </div>
                        <div class="fivepost-selected-address" id="adminFivepostPointAddressDisplay">Адрес</div>
                        <div class="fivepost-selected-extra" id="adminFivepostPointExtraDisplay">Выдача заказа 5Post</div>
                    </div>
                    <button type="button" class="fivepost-change-btn" id="adminFivepostChangeBtn">Изменить</button>
                </div>

                <div class="fivepost-map-wrapper" id="adminFivepostMapWrapper">
                    <div class="bf-city-search-wrap">
                        <div class="bf-city-search-box">
                            <svg class="bf-search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" id="adminFivepostCitySearch" class="ozon-input bf-city-search-input" placeholder="Поиск по городу или улице (например, Пенза, Победы…)" autocomplete="off">
                            <button type="button" id="adminFivepostCityClear" class="bf-clear-btn" style="display:none;" aria-label="Очистить">&times;</button>
                        </div>
                    </div>

                    <div id="adminFivepostCustomMap" class="bf-custom-map" style="width: 100%; height: 340px; background: #f3f4f6; position:relative;">
                        <div class="fivepost-map-loader" id="adminFivepostMapLoader" style="display:none;">
                            <div class="fivepost-spinner"></div>
                            <span>Загрузка карты и точек выдачи…</span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top: 24px; display:flex; gap: 12px; justify-content: flex-end;">
                <button type="button" id="btnCancelCreate" class="btn btn-secondary">Отмена</button>
                <button type="submit" id="btnSubmitCreate" class="btn btn-primary">Создать и отправить</button>
            </div>
        </form>
    </div>
</div>
