/**
 * Boyforge 5Post Yandex Maps Integration (Admin CRM)
 */
(function() {
    let adminMapInitialized = false;
    let adminMap = null;
    let adminPointsCollection = null;
    let currentAdminPoints = [];
    let customAdminPinLayout = null;
    const renderedAdminPointIds = new Set();
    let adminBoundsDebounce = null;
    let isAdminProgrammaticMove = false;
    let currentAdminCity = 'Пенза';

    function setAdminProgrammaticMove() {
        isAdminProgrammaticMove = true;
        setTimeout(() => isAdminProgrammaticMove = false, 700);
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function handleSelectAdminPoint(point) {
        if (!point) return;
        const pointType = point.type || "POSTAMAT";
        const pointName = point.name || (pointType === "POSTAMAT" ? "Постамат 5Post" : "Касса «Пятёрочка»");
        const pointAddr = point.full_address || point.resultAddress || point.address || "";
        const pointExtra = [point.additional, point.work_hours].filter(Boolean).join(" · ");

        const fPointId = document.getElementById('crPointId');
        const fAddress = document.getElementById('crAddress');
        const fPointType = document.getElementById('crPointType');
        const fPointName = document.getElementById('crPointName');
        const fPointDetails = document.getElementById('crPointDetails');

        if (fPointId) fPointId.value = point.id || "";
        if (fAddress) fAddress.value = pointAddr;
        if (fPointType) fPointType.value = pointType;
        if (fPointName) fPointName.value = pointName;
        if (fPointDetails) fPointDetails.value = pointExtra;

        const dName = document.getElementById('adminFivepostPointNameDisplay');
        const dAddr = document.getElementById('adminFivepostPointAddressDisplay');
        const dExtra = document.getElementById('adminFivepostPointExtraDisplay');

        if (dName) dName.textContent = pointName;
        if (dAddr) dAddr.textContent = pointAddr;
        if (dExtra) dExtra.textContent = pointExtra || "Выдача заказа 5Post";

        const card = document.getElementById('adminFivepostSelectedCard');
        const wrap = document.getElementById('adminFivepostMapWrapper');
        if (card) card.style.display = 'flex';
        if (wrap) wrap.style.display = 'none';
        
        if (adminMap && adminMap.balloon) adminMap.balloon.close();
    }

    function createAdminCustomPinLayout() {
        if (customAdminPinLayout || typeof ymaps === "undefined") return customAdminPinLayout;
        customAdminPinLayout = ymaps.templateLayoutFactory.createClass(
            '<div class="bf-map-pin fivepost-pin" title="$[properties.hintContent]">' +
            '<div class="bf-pin-body">' +
            '<img src="/images/pyaterochka.png" alt="Пятёрочка" class="bf-pin-img" width="36" height="36">' +
            '</div>' +
            '<div class="bf-pin-tail"></div>' +
            '</div>'
        );
        return customAdminPinLayout;
    }

    function createAdminPointPlacemark(point, pinLayout) {
        const isPostamat = (point.type === "POSTAMAT");
        const titleName = point.name || (isPostamat ? "Постамат 5Post" : "Касса «Пятёрочка»");
        const fullAddr = point.full_address || point.street || "";

        const balloonHtml = `
            <div class="bf-balloon">
                <div class="bf-balloon-header">
                    <strong class="bf-balloon-name">${escapeHtml(titleName)}</strong>
                </div>
                <div class="bf-balloon-address">${escapeHtml(fullAddr)}</div>
                ${point.work_hours ? `<div class="bf-balloon-extra">${escapeHtml(point.work_hours)}</div>` : ''}
                ${point.additional ? `<div class="bf-balloon-extra">${escapeHtml(point.additional)}</div>` : ''}
                <button type="button" class="bf-balloon-select-btn" data-point-id="${escapeHtml(point.id)}">Выбрать эту точку</button>
            </div>
        `;

        return new ymaps.Placemark([point.lat, point.lng], {
            hintContent: (point.name ? point.name + " · " : "") + fullAddr,
            balloonContent: balloonHtml
        }, {
            iconLayout: pinLayout,
            iconOffset: [0, 0],
            iconShape: { type: "Rectangle", coordinates: [[-18, -44], [18, 0]] },
            hideIconOnBalloonOpen: false,
            balloonOffset: [0, -44],
            balloonCloseButton: true,
            balloonAutoPan: true
        });
    }

    function renderAdminMarkers(points, adjustViewport = true) {
        if (!adminPointsCollection || !adminMap) return;
        adminPointsCollection.removeAll();
        renderedAdminPointIds.clear();
        currentAdminPoints = [];
        if (!points || points.length === 0) return;

        const pinLayout = createAdminCustomPinLayout();
        points.forEach(point => {
            if (!point.lat || !point.lng) return;
            renderedAdminPointIds.add(String(point.id));
            currentAdminPoints.push(point);
            adminPointsCollection.add(createAdminPointPlacemark(point, pinLayout));
        });

        if (adjustViewport) {
            setAdminProgrammaticMove();
            if (points.length === 1) {
                adminMap.setCenter([points[0].lat, points[0].lng], 15, { checkZoomRange: true });
            } else if (points.length > 1) {
                const bounds = adminPointsCollection.getBounds();
                if (bounds) adminMap.setBounds(bounds, { checkZoomRange: true, zoomMargin: 40 });
            }
        }
    }

    function appendAdminMarkers(newPoints) {
        if (!adminPointsCollection || !adminMap || !newPoints || newPoints.length === 0) return;
        const pinLayout = createAdminCustomPinLayout();
        newPoints.forEach(point => {
            if (!point.lat || !point.lng) return;
            const strId = String(point.id);
            if (renderedAdminPointIds.has(strId)) return;
            renderedAdminPointIds.add(strId);
            currentAdminPoints.push(point);
            adminPointsCollection.add(createAdminPointPlacemark(point, pinLayout));
        });
    }

    function loadAdminPointsByBounds() {
        if (!adminMap || !adminMapInitialized) return;
        const bounds = adminMap.getBounds();
        if (!bounds) return;
        const zoom = adminMap.getZoom();
        if (zoom < 8) return;

        const latMin = Math.min(bounds[0][0], bounds[1][0]);
        const latMax = Math.max(bounds[0][0], bounds[1][0]);
        const lngMin = Math.min(bounds[0][1], bounds[1][1]);
        const lngMax = Math.max(bounds[0][1], bounds[1][1]);
        const boundsParam = `${latMin.toFixed(6)},${lngMin.toFixed(6)},${latMax.toFixed(6)},${lngMax.toFixed(6)}`;
        
        fetch(`/api/fivepost-points.php?bounds=${encodeURIComponent(boundsParam)}&zoom=${zoom}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.success && Array.isArray(data.points)) appendAdminMarkers(data.points);
            }).catch(err => console.warn(err));
    }

    function loadAdminPoints(city, search, isInitial) {
        const loader = document.getElementById('adminFivepostMapLoader');
        if (isInitial && loader) loader.style.display = "flex";
        let url = "/api/fivepost-points.php?";
        if (city) url += "city=" + encodeURIComponent(city) + "&";
        if (search) url += "search=" + encodeURIComponent(search);

        fetch(url).then(res => res.json()).then(data => {
            if (data && data.success && Array.isArray(data.points)) {
                renderAdminMarkers(data.points);
                if (data.city) currentAdminCity = data.city;
                if (data.points.length === 0 && search && adminMap && typeof ymaps !== "undefined" && ymaps.geocode) {
                    ymaps.geocode(data.city || search).then(res => {
                        const firstGeo = res.geoObjects.get(0);
                        if (firstGeo) {
                            setAdminProgrammaticMove();
                            adminMap.setCenter(firstGeo.geometry.getCoordinates(), 11, { checkZoomRange: true });
                        }
                    });
                }
            }
        }).catch(err => console.error(err)).finally(() => {
            if (loader) loader.style.display = "none";
        });
    }

    window.initAdminMap = function() {
        if (adminMapInitialized || typeof ymaps === 'undefined') {
            if (adminMap) setTimeout(() => { try { adminMap.container.fitToViewport(); } catch(e){} }, 300);
            return;
        }
        
        const mapContainer = document.getElementById('adminFivepostCustomMap');
        if (!mapContainer) return;

        adminMap = new ymaps.Map('adminFivepostCustomMap', {
            center: [53.20066, 44.99965],
            zoom: 12,
            controls: ['zoomControl', 'fullscreenControl']
        }, { suppressMapOpenBlock: true });

        adminPointsCollection = new ymaps.GeoObjectCollection();
        adminMap.geoObjects.add(adminPointsCollection);
        adminMapInitialized = true;

        adminMap.events.add('boundschange', function () {
            if (isAdminProgrammaticMove) return;
            clearTimeout(adminBoundsDebounce);
            adminBoundsDebounce = setTimeout(loadAdminPointsByBounds, 300);
        });

        loadAdminPoints(currentAdminCity, "", true);
        setTimeout(() => { try { adminMap.container.fitToViewport(); } catch(e){} }, 350);
    };

    document.addEventListener('DOMContentLoaded', function() {
        const customMap = document.getElementById('adminFivepostCustomMap');
        if (customMap) {
            customMap.addEventListener("click", function (e) {
                const btn = e.target.closest(".bf-balloon-select-btn");
                if (btn) {
                    const pid = btn.getAttribute("data-point-id");
                    const pt = currentAdminPoints.find(p => String(p.id) === String(pid));
                    if (pt) handleSelectAdminPoint(pt);
                }
            });
        }

        const citySearch = document.getElementById('adminFivepostCitySearch');
        const cityClear = document.getElementById('adminFivepostCityClear');
        let adminSearchDebounceTimer = null;

        if (citySearch) {
            citySearch.addEventListener("input", function () {
                const val = this.value.trim();
                if (cityClear) cityClear.style.display = val ? "block" : "none";
                clearTimeout(adminSearchDebounceTimer);
                adminSearchDebounceTimer = setTimeout(() => loadAdminPoints("", val, false), 400);
            });
        }

        if (cityClear) {
            cityClear.addEventListener("click", function () {
                if (citySearch) citySearch.value = "";
                this.style.display = "none";
                loadAdminPoints(currentAdminCity, "", false);
            });
        }

        const changeBtn = document.getElementById('adminFivepostChangeBtn');
        if (changeBtn) {
            changeBtn.addEventListener("click", function () {
                const card = document.getElementById('adminFivepostSelectedCard');
                const wrap = document.getElementById('adminFivepostMapWrapper');
                if (card) card.style.display = "none";
                if (wrap) wrap.style.display = "block";
                if (adminMap) setTimeout(() => { try { adminMap.container.fitToViewport(); } catch(e){} }, 100);
            });
        }
    });
})();
