/**
 * Boyforge CRM Orders Client Logic
 */
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Переключение режимов: Таблица / Канбан
    const tabTable = document.getElementById('tabViewTable');
    const tabKanban = document.getElementById('tabViewKanban');
    const tableView = document.getElementById('ordersTableView');
    const kanbanView = document.getElementById('ordersKanbanView');

    function setViewMode(mode) {
        if (!tabTable || !tabKanban || !tableView || !kanbanView) return;
        if (mode === 'kanban') {
            tabTable.classList.remove('active');
            tabKanban.classList.add('active');
            tableView.style.display = 'none';
            kanbanView.style.display = 'block';
            localStorage.setItem('bf_orders_view_mode', 'kanban');
        } else {
            tabKanban.classList.remove('active');
            tabTable.classList.add('active');
            kanbanView.style.display = 'none';
            tableView.style.display = 'block';
            localStorage.setItem('bf_orders_view_mode', 'table');
        }
    }

    const savedMode = localStorage.getItem('bf_orders_view_mode') || 'table';
    setViewMode(savedMode);

    if (tabTable) tabTable.addEventListener('click', () => setViewMode('table'));
    if (tabKanban) tabKanban.addEventListener('click', () => setViewMode('kanban'));

    // 2. Живой поиск и фильтры по чипсам
    const searchInput = document.getElementById('orderSearch');
    const searchClear = document.getElementById('searchClearBtn');
    const filterChips = document.querySelectorAll('.filter-chip');
    const tableRows = document.querySelectorAll('#ordersTableBody .order-row');
    const visibleCountSpan = document.getElementById('visibleCount');

    let currentFilter = 'all';

    function runFilter() {
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        if (searchClear) searchClear.style.display = query ? 'block' : 'none';

        let visibleTotal = 0;

        // Фильтруем таблицу
        tableRows.forEach(row => {
            const data = JSON.parse(row.getAttribute('data-order'));
            const stage = row.getAttribute('data-stage');
            const payment = row.getAttribute('data-payment');
            const isCancelled = (stage === 'cancelled');

            let textMatch = true;
            if (query) {
                const combined = `${data.order_id} ${data.fio} ${data.phone} ${data.product_name} ${data.fivepost_barcode || ''} ${data.fivepost_point_address || ''}`.toLowerCase();
                textMatch = combined.includes(query);
            }

            let chipMatch = true;
            if (currentFilter === 'to_ship') chipMatch = (stage === 'to_ship');
            else if (currentFilter === 'in_transit') chipMatch = (stage === 'in_transit');
            else if (currentFilter === 'ready_pickup') chipMatch = (stage === 'ready_pickup');
            else if (currentFilter === 'paid') chipMatch = (payment === 'paid' && !isCancelled);
            else if (currentFilter === 'manual') chipMatch = (payment === 'manual' && !isCancelled);
            else if (currentFilter === 'pending') chipMatch = (stage === 'pending');
            else if (currentFilter === 'cancelled') chipMatch = (stage === 'cancelled');

            const isShown = textMatch && chipMatch;
            row.style.display = isShown ? '' : 'none';
            if (isShown) visibleTotal++;
        });

        // Фильтруем карточки Канбана и пересчитываем суммы колонок
        document.querySelectorAll('.kanban-col').forEach(col => {
            const colStage = col.getAttribute('data-col-stage');
            const cards = col.querySelectorAll('.k-card');
            let colVisible = 0;
            let colSum = 0;

            cards.forEach(card => {
                const data = JSON.parse(card.getAttribute('data-order'));
                const stage = card.getAttribute('data-stage');
                const payment = card.getAttribute('data-payment');
                const isCancelled = (stage === 'cancelled');

                let textMatch = true;
                if (query) {
                    const combined = `${data.order_id} ${data.fio} ${data.phone} ${data.product_name} ${data.fivepost_barcode || ''} ${data.fivepost_point_address || ''}`.toLowerCase();
                    textMatch = combined.includes(query);
                }

                let chipMatch = true;
                if (currentFilter === 'to_ship') chipMatch = (stage === 'to_ship');
                else if (currentFilter === 'in_transit') chipMatch = (stage === 'in_transit');
                else if (currentFilter === 'ready_pickup') chipMatch = (stage === 'ready_pickup');
                else if (currentFilter === 'paid') chipMatch = (payment === 'paid' && !isCancelled);
                else if (currentFilter === 'manual') chipMatch = (payment === 'manual' && !isCancelled);
                else if (currentFilter === 'pending') chipMatch = (stage === 'pending');
                else if (currentFilter === 'cancelled') chipMatch = (stage === 'cancelled');

                const isShown = textMatch && chipMatch;
                card.style.display = isShown ? '' : 'none';
                if (isShown) {
                    colVisible++;
                    colSum += parseFloat(data.price || 0);
                }
            });

            const badge = document.getElementById('badge-' + colStage);
            const sumEl = document.getElementById('sum-' + colStage);
            if (badge) badge.textContent = colVisible;
            if (sumEl) sumEl.textContent = new Intl.NumberFormat('ru-RU').format(colSum) + ' ₽';
        });

        if (visibleCountSpan) visibleCountSpan.textContent = visibleTotal;
    }

    if (searchInput) searchInput.addEventListener('input', runFilter);
    if (searchClear) {
        searchClear.addEventListener('click', () => {
            searchInput.value = '';
            runFilter();
        });
    }

    filterChips.forEach(chip => {
        chip.addEventListener('click', function() {
            filterChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.getAttribute('data-filter');
            runFilter();
        });
    });

    // 3. Быстрое копирование в буфер
    const copyToast = document.getElementById('copyToast');
    let toastTimeout = null;
    function showToast(text) {
        if (!copyToast) return;
        copyToast.textContent = text || 'Скопировано в буфер';
        copyToast.style.display = 'block';
        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => copyToast.style.display = 'none', 1800);
    }
    window.showToast = showToast;

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.copy-btn-inline');
        if (btn) {
            e.stopPropagation();
            const text = btn.getAttribute('data-copy');
            if (text) {
                navigator.clipboard.writeText(text).then(() => showToast('Скопировано: ' + text));
            }
        }
    });

    // 4. Синхронизация 5Post по кнопке
    const btnSync5Post = document.getElementById('btnSync5Post');
    const syncIcon = document.getElementById('syncIcon');
    const syncBtnText = document.getElementById('syncBtnText');

    if (btnSync5Post) {
        btnSync5Post.addEventListener('click', function() {
            if (syncIcon) syncIcon.style.animation = 'spin 1s linear infinite';
            if (syncBtnText) syncBtnText.textContent = 'Обновление…';
            btnSync5Post.disabled = true;

            fetch('api_sync_5post.php?force=1', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.result.message || 'Статусы 5Post обновлены');
                        setTimeout(() => location.reload(), 600);
                    } else {
                        alert('Ошибка: ' + (data.result?.error || 'Сбой обновления'));
                    }
                })
                .catch(() => alert('Сетевая ошибка при обращении к 5Post'))
                .finally(() => {
                    if (syncIcon) syncIcon.style.animation = '';
                    if (syncBtnText) syncBtnText.textContent = 'Обновить 5Post';
                    btnSync5Post.disabled = false;
                });
        });
    }

    // 5. Модальное окно деталей заказа
    const detailModal = document.getElementById('orderDetailModal');
    let currentOrderData = null;

    function openOrderDetail(data) {
        currentOrderData = data;
        
        document.getElementById('mdlOrderId').textContent = 'Заказ #' + data.order_id;
        document.getElementById('mdlOrderDate').textContent = 'Оформлен: ' + data.created_at;
        document.getElementById('mdlFio').textContent = data.fio || 'Покупатель';
        document.getElementById('mdlPhone').textContent = data.phone || '—';
        document.getElementById('mdlEmail').textContent = data.email || '—';
        document.getElementById('mdlTg').textContent = data.tg_username ? ('@' + data.tg_username.replace(/^@/, '')) : '—';
        document.getElementById('mdlProduct').textContent = data.product_name || '—';
        document.getElementById('mdlPrice').textContent = (new Intl.NumberFormat('ru-RU').format(data.price || 0)) + ' ₽';
        document.getElementById('mdlSize').textContent = data.size || '—';
        
        const isCancelled = (data.mark_code === '__CANCELLED__' || data.payment_status === 'refunded');
        
        let payStr = data.payment_status;
        if (isCancelled) payStr = 'Отменён';
        else if (payStr === 'paid') payStr = 'Оплачен (ЮKassa)';
        else if (payStr === 'manual') payStr = 'Ручной заказ';
        else payStr = 'Ожидает оплаты';
        document.getElementById('mdlPaymentStatus').textContent = payStr;

        document.getElementById('mdl5pAddress').textContent = data.fivepost_point_address || '—';
        document.getElementById('mdl5pPointId').textContent = data.fivepost_point_id || '—';
        document.getElementById('mdl5pOrderId').textContent = data.fivepost_order_id || '—';
        document.getElementById('mdl5pBarcode').textContent = data.fivepost_barcode || '—';
        document.getElementById('mdl5pStatus').textContent = data.fivepost_status || 'Без отправки';

        // Трекинг ссылка
        const trWrap = document.getElementById('mdlTrackingLinkWrap');
        const trLink = document.getElementById('mdlTrackingLink');
        if (data.fivepost_barcode) {
            trWrap.style.display = 'block';
            trLink.href = 'https://fivepost.ru/';
        } else {
            trWrap.style.display = 'none';
        }

        // Стадия и Lifecycle таймлайн
        const stBadge = document.getElementById('mdlStageBadge');
        const fpSt = data.fivepost_status || '';
        const isPaid = (data.payment_status === 'paid' || data.payment_status === 'manual');
        const isSent = Boolean(data.fivepost_order_id);
        const isPickup = ['PLACED_IN_POSTAMAT', 'READY_FOR_WITHDRAW_FROM_PICKUP_POINT', 'WAITING_FOR_REPICKUP'].includes(fpSt);
        const isDone = ['DONE', 'PICKED_UP'].includes(fpSt);

        const sCreated = document.getElementById('step-created');
        const sPaid = document.getElementById('step-paid');
        const sShipped = document.getElementById('step-shipped');
        const sPickup = document.getElementById('step-pickup');
        const sDone = document.getElementById('step-done');

        [sCreated, sPaid, sShipped, sPickup, sDone].forEach(s => {
            if (s) s.classList.remove('active', 'done');
        });

        if (sCreated) sCreated.classList.add('done');
        if (sPaid) {
            if (isPaid) sPaid.classList.add('done');
            else sPaid.classList.add('active');
        }

        if (sShipped) {
            if (isSent) sShipped.classList.add('done');
            else if (isPaid) sShipped.classList.add('active');
        }

        if (sPickup) {
            if (isPickup) sPickup.classList.add('done');
            else if (isSent && !isDone) sPickup.classList.add('active');
        }

        if (sDone && isDone) sDone.classList.add('done');

        if (stBadge) {
            if (isCancelled) {
                stBadge.className = 'status-badge badge-cancelled';
                stBadge.textContent = 'Отменён';
            } else if (isDone) {
                stBadge.className = 'status-badge badge-paid';
                stBadge.textContent = 'Выполнен';
            } else if (isPickup) {
                stBadge.className = 'status-badge';
                stBadge.style.color = '#059669';
                stBadge.style.background = 'rgba(16,185,129,0.1)';
                stBadge.textContent = 'В пункте выдачи';
            } else if (isSent) {
                stBadge.className = 'status-badge';
                stBadge.style.color = '#7c3aed';
                stBadge.style.background = 'rgba(124,58,237,0.1)';
                stBadge.textContent = 'В пути 5Post';
            } else if (isPaid) {
                stBadge.className = 'status-badge badge-manual';
                stBadge.textContent = 'К отправке';
            } else {
                stBadge.className = 'status-badge badge-pending';
                stBadge.textContent = 'Ожидает оплаты';
            }
        }

        // Кнопки действий
        const receiptCont = document.getElementById('receiptContainer');
        const btnCancel = document.getElementById('btnCancelOrder');
        const btnManual = document.getElementById('btnManual5Post');
        const markInput = document.getElementById('mdlMarkCode');

        if (receiptCont) receiptCont.style.display = 'none';
        if (btnCancel) btnCancel.style.display = 'none';
        if (btnManual) btnManual.style.display = 'none';
        if (markInput) markInput.value = '';

        if (data.receipt_sent == 1 && receiptCont) {
            receiptCont.style.display = 'block';
            receiptCont.innerHTML = `<div style="color:#059669; font-weight:700;">Чек 54-ФЗ успешно пробит</div><div style="font-size:11px; margin-top:4px; font-family:monospace;">${data.mark_code}</div>`;
        } else if (!isCancelled && data.payment_status === 'paid' && receiptCont) {
            receiptCont.style.display = 'block';
            receiptCont.innerHTML = `
                <div style="font-size: 12px; font-weight: 700; margin-bottom: 8px;">Маркировка и закрывающий чек 54-ФЗ</div>
                <div style="display:flex; gap: 8px;">
                    <input type="text" id="mdlMarkCode" class="form-input" placeholder="Пикните сканером код маркировки" style="font-family: monospace; font-size:12px; flex: 1;">
                    <button id="btnSendReceipt" class="btn btn-primary btn-sm">Отправить чек</button>
                </div>
            `;
            const btnSnd = document.getElementById('btnSendReceipt');
            if (btnSnd) btnSnd.addEventListener('click', handleSendReceipt);
        }

        // Ручная отправка в 5Post если не отправлен
        if (!isCancelled && !data.fivepost_order_id && data.fivepost_point_id && isPaid && btnManual) {
            btnManual.style.display = 'inline-flex';
        }

        if (!isCancelled && data.payment_status !== 'manual' && btnCancel) {
            btnCancel.style.display = 'inline-flex';
        }

        if (detailModal) detailModal.style.display = 'flex';
    }

    // Слушатели открытия модалки по клику
    document.querySelectorAll('.order-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.closest('.copy-btn-inline')) return;
            const data = JSON.parse(this.getAttribute('data-order'));
            openOrderDetail(data);
        });
    });

    document.querySelectorAll('.k-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.closest('.copy-btn-inline')) return;
            const data = JSON.parse(this.getAttribute('data-order'));
            openOrderDetail(data);
        });
    });

    const closeDetail = document.getElementById('closeDetailModal');
    if (closeDetail) closeDetail.addEventListener('click', () => detailModal.style.display = 'none');

    const copyPhoneBtn = document.getElementById('mdlCopyPhoneBtn');
    if (copyPhoneBtn) {
        copyPhoneBtn.addEventListener('click', () => {
            if (currentOrderData && currentOrderData.phone) {
                navigator.clipboard.writeText(currentOrderData.phone).then(() => showToast('Телефон скопирован'));
            }
        });
    }

    const copyBarcodeBtn = document.getElementById('mdlCopyBarcodeBtn');
    if (copyBarcodeBtn) {
        copyBarcodeBtn.addEventListener('click', () => {
            if (currentOrderData && currentOrderData.fivepost_barcode) {
                navigator.clipboard.writeText(currentOrderData.fivepost_barcode).then(() => showToast('Штрихкод скопирован'));
            }
        });
    }

    // Отправка чека
    function handleSendReceipt() {
        const markInput = document.getElementById('mdlMarkCode');
        const markCode = markInput ? markInput.value.trim() : '';
        if (!markCode) return alert('Введите код маркировки');
        
        const btn = document.getElementById('btnSendReceipt');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Отправка…';
        }
        
        fetch('api_receipt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: currentOrderData.order_id, mark_code: markCode })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('Чек пробит!');
                location.reload();
            } else {
                alert('Ошибка: ' + d.error);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Отправить чек';
                }
            }
        });
    }

    // Ручная отправка в 5Post из модалки
    const btnManual5P = document.getElementById('btnManual5Post');
    if (btnManual5P) {
        btnManual5P.addEventListener('click', function() {
            if (!confirm('Отправить заказ ' + currentOrderData.order_id + ' в 5Post сейчас?')) return;
            this.disabled = true;
            this.textContent = 'Отправка…';

            fetch('api_manual_5post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: currentOrderData.order_id })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    alert('Заказ успешно зарегистрирован в 5Post!');
                    location.reload();
                } else {
                    alert('Ошибка 5Post: ' + (d.error || 'Неизвестная ошибка'));
                    this.disabled = false;
                    this.textContent = 'Отправить в 5Post вручную';
                }
            })
            .catch(() => {
                alert('Сетевая ошибка');
                this.disabled = false;
                this.textContent = 'Отправить в 5Post вручную';
            });
        });
    }

    // Отмена заказа
    const btnCancelOrd = document.getElementById('btnCancelOrder');
    if (btnCancelOrd) {
        btnCancelOrd.addEventListener('click', function() {
            if (!confirm('Точно отменить заказ ' + currentOrderData.order_id + '?')) return;
            this.disabled = true;
            
            fetch('api_cancel_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: currentOrderData.order_id })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) location.reload();
                else { alert(d.error); this.disabled = false; }
            });
        });
    }

    // 6. Модалка создания ручного заказа
    const createModal = document.getElementById('createManualModal');
    const btnCreate = document.getElementById('btnCreateOrder');
    if (btnCreate) {
        btnCreate.addEventListener('click', () => {
            if (createModal) createModal.style.display = 'flex';
            setTimeout(() => {
                if (typeof ymaps !== 'undefined' && window.initAdminMap) ymaps.ready(window.initAdminMap);
            }, 100);
        });
    }
    
    const closeCreate = document.getElementById('closeCreateModal');
    if (closeCreate) closeCreate.addEventListener('click', () => createModal.style.display = 'none');
    
    const cancelCreate = document.getElementById('btnCancelCreate');
    if (cancelCreate) cancelCreate.addEventListener('click', () => createModal.style.display = 'none');

    // Каскадные выпадающие списки (Товар -> Пол -> Размер -> Цена)
    const selectCrProduct = document.getElementById('crProduct');
    const selectCrGender = document.getElementById('crGender');
    const selectCrSize = document.getElementById('crSize');
    const inputCrPrice = document.getElementById('crPrice');
    const adminProductsData = window.adminProductsData || [];

    if (selectCrProduct) {
        selectCrProduct.addEventListener('change', function() {
            if (!selectCrGender || !selectCrSize) return;
            selectCrGender.innerHTML = '<option value="">Выберите пол...</option>';
            selectCrSize.innerHTML = '<option value="">Сначала выберите пол</option>';
            selectCrGender.disabled = true;
            selectCrSize.disabled = true;
            
            const selVal = this.value;
            if (!selVal) return;
            
            const prod = adminProductsData.find(p => p.name === selVal);
            if (prod) {
                if (inputCrPrice) inputCrPrice.value = prod.price_numeric || 0;
                if (prod.sizes && Object.keys(prod.sizes).length > 0) {
                    const genders = Object.keys(prod.sizes);
                    genders.forEach(g => {
                        const opt = document.createElement('option');
                        opt.value = g;
                        opt.textContent = g;
                        selectCrGender.appendChild(opt);
                    });
                    selectCrGender.disabled = false;
                    if (genders.length === 1) {
                        selectCrGender.value = genders[0];
                        selectCrGender.dispatchEvent(new Event('change'));
                    }
                } else {
                    selectCrGender.innerHTML = '<option value="-">Нет данных о поле</option>';
                    selectCrSize.innerHTML = '<option value="-">Нет данных о размере</option>';
                    selectCrGender.disabled = false;
                    selectCrSize.disabled = false;
                }
            }
        });
    }

    if (selectCrGender) {
        selectCrGender.addEventListener('change', function() {
            if (!selectCrSize || !selectCrProduct) return;
            selectCrSize.innerHTML = '<option value="">Выберите размер...</option>';
            selectCrSize.disabled = true;
            
            const selGender = this.value;
            if (!selGender) return;
            
            const prod = adminProductsData.find(p => p.name === selectCrProduct.value);
            if (prod && prod.sizes && prod.sizes[selGender]) {
                const sizes = prod.sizes[selGender];
                sizes.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s;
                    selectCrSize.appendChild(opt);
                });
                selectCrSize.disabled = false;
                if (sizes.length === 1) {
                    selectCrSize.value = sizes[0];
                }
            }
        });
    }

    const formCreate = document.getElementById('createManualForm');
    if (formCreate) {
        formCreate.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitCreate');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Отправка...';
            }

            const payload = {
                fio: document.getElementById('crFio').value,
                phone: document.getElementById('crPhone').value,
                email: document.getElementById('crEmail').value,
                product_name: document.getElementById('crProduct').value,
                price: document.getElementById('crPrice').value,
                size: (document.getElementById('crGender').value !== '-' ? document.getElementById('crGender').value + ' ' : '') + document.getElementById('crSize').value,
                fivepost_point_id: document.getElementById('crPointId').value,
                fivepost_point_address: document.getElementById('crAddress').value
            };

            fetch('api_create_manual_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    alert('Успешно создано и отправлено в 5Post!');
                    location.reload();
                } else {
                    alert(d.error);
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Создать и отправить';
                    }
                }
            });
        });
    }
    
    // 7. Telegram Settings
    const tgSettingsModal = document.getElementById('tgSettingsModal');
    const btnTgSettings = document.getElementById('btnTgSettings');
    const tgSettingsClose = document.getElementById('tgSettingsClose');
    const tgSettingsCloseIcon = document.getElementById('tgSettingsCloseIcon');
    const tgSettingsSave = document.getElementById('tgSettingsSave');
    const tgTestBtn = document.getElementById('tgTestBtn');

    if (btnTgSettings && tgSettingsModal) {
        btnTgSettings.addEventListener('click', () => tgSettingsModal.style.display = 'flex');
        if (tgSettingsClose) tgSettingsClose.addEventListener('click', () => tgSettingsModal.style.display = 'none');
        if (tgSettingsCloseIcon) tgSettingsCloseIcon.addEventListener('click', () => tgSettingsModal.style.display = 'none');

        if (tgSettingsSave) {
            tgSettingsSave.addEventListener('click', function() {
                const token = document.getElementById('tgBotTokenInput').value.trim();
                const chatId = document.getElementById('tgChatIdInput').value.trim();
                const originalText = this.textContent;
                this.textContent = 'Сохранение...';
                this.disabled = true;

                fetch('api_tg_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tg_bot_token: token, tg_chat_id: chatId })
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
                .catch(() => alert('Сетевая ошибка'))
                .finally(() => { this.textContent = originalText; this.disabled = false; });
            });
        }
        
        if (tgTestBtn) {
            tgTestBtn.addEventListener('click', function() {
                const originalText = this.textContent;
                this.textContent = 'Отправка...';
                this.disabled = true;
                fetch('api_tg_test.php', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) alert('Тестовое сообщение успешно отправлено!');
                    else alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                })
                .catch(() => alert('Сетевая ошибка'))
                .finally(() => { this.textContent = originalText; this.disabled = false; });
            });
        }
    }

});
