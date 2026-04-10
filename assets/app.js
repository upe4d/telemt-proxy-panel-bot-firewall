/**
 * app.js — Главный JS дашборда Telemt MTProxy Stats
 * @version 1.0.0
 * @updated 2026-04-10
 *
 * CHANGELOG:
 * v1.0.0 - Начальная версия, вкладочная навигация, AJAX загрузка вкладок
 */

/* === ПЕРЕКЛЮЧЕНИЕ ВКЛАДОК === */
function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            // Убираем активный класс со всех кнопок
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            // Загружаем вкладку
            loadTab(tab);
            // Сохраняем в URL
            history.replaceState(null, '', '?tab=' + tab);
        });
    });

    // Восстанавливаем вкладку из URL
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab') || 'overview';
    const activeBtn = document.querySelector(`.tab-btn[data-tab="${tab}"]`);
    if (activeBtn) activeBtn.click();
    else document.querySelector('.tab-btn').click();
}

/* === AJAX ЗАГРУЗКА ВКЛАДОК === */
const tabCache = {};

function loadTab(tab) {
    const content = document.getElementById('tab-content');

    // Если вкладка уже загружена — показываем из кэша (protection всегда свежая)
    const noCache = ['protection', 'map', 'history', 'users', 'overview'];
    if (tabCache[tab] && !noCache.includes(tab)) {
        content.innerHTML = tabCache[tab];
        initTabScripts(tab);
        return;
    }

    content.innerHTML = '<div class="loading">Загрузка</div>';

    fetch(`tabs/${tab}.php`)
        .then(r => r.text())
        .then(html => {
            tabCache[tab] = html;
            content.innerHTML = html;
            // Выполняем script теги из загруженного HTML
            content.querySelectorAll('script').forEach(old => {
                const s = document.createElement('script');
                s.textContent = old.textContent;
                document.body.appendChild(s);
                document.body.removeChild(s);
            });
            initTabScripts(tab);
        })
        .catch(() => {
            content.innerHTML = '<div class="loading">Ошибка загрузки вкладки</div>';
        });
}

/* === ИНИЦИАЛИЗАЦИЯ СКРИПТОВ ВКЛАДКИ === */
function initTabScripts(tab) {
    if (tab === 'overview') { mapInstance = null; initOverview(); setTimeout(initMap, 300); }
    if (tab === 'map') { /* карта инициализируется в map.php */ }
    if (tab === 'protection') initProtection();
    if (tab === 'countries') initCountries();
    if (tab === 'users') initUsers();
    if (tab === 'history') initHistory();
    if (tab === 'whitelist') initWhitelist();
}

/* === ФОРМАТИРОВАНИЕ ЧИСЕЛ === */
function fmtNum(n) {
    if (!n) return '0';
    if (n >= 1e9) return (n / 1e9).toFixed(1) + ' GB';
    if (n >= 1e6) return (n / 1e6).toFixed(1) + ' MB';
    if (n >= 1e3) return (n / 1e3).toFixed(1) + ' KB';
    return n + ' B';
}

function fmtInt(n) {
    if (!n) return '0';
    return n.toLocaleString('ru-RU');
}

/* === ТОСТ УВЕДОМЛЕНИЯ === */
function showToast(msg, duration = 2500) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.opacity = '1';
    t.style.transform = 'translateX(-50%) translateY(0)';
    setTimeout(() => {
        t.style.opacity = '0';
        t.style.transform = 'translateX(-50%) translateY(20px)';
    }, duration);
}

/* === КОПИРОВАНИЕ === */
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => showToast('Скопировано'));
}

/* === ОБЗОР === */
function initOverview() {
    fetch('data/current.json').then(r => r.json()).then(d => {
        setEl('ov-conn',    fmtInt(d.total_connections));
        setEl('ov-ip',      fmtInt(d.active_ips));
        setEl('ov-traffic', fmtNum(d.total_traffic));
        setEl('ov-cpu',     d.cpu + '%');
        setEl('ov-ram',     d.ram + '%');
        setEl('ov-disk',    d.disk);
        setEl('ov-logs',    fmtNum(d.logs_size));
        setEl('ov-uptime',  fmtUptime(d.uptime_seconds));
    }).catch(() => {});

    fetch('data/fw_stats.json').then(r => r.json()).then(d => {
        setEl('ov-rst',  fmtInt(d.total_pkts));
        setEl('ov-tsp',  fmtInt(d.tspublock?.pkts));
        setEl('ov-gov',  fmtInt(d.govblock?.pkts));
    }).catch(() => {});

    fetch('data/countries.json').then(r => r.json()).then(d => {
        // Считаем страны
        const count = Object.keys(d).filter(k => k !== '??').length;
        setEl('ov-countries', count);
        // Топ стран
        renderTopCountries(d);
    }).catch(() => {});
}

/* === КАРТА === */
let mapInstance = null;
function initMap() {
    if (mapInstance) return;
    const mapEl = document.getElementById('map') || document.getElementById('map-mini');
    if (!mapEl) return;
    if (!window.L) { setTimeout(initMap, 200); return; }
    fetch('data/current.json').then(r => r.json()).then(d => {
        const pts = (d.active_ips_list || []).filter(p => p.lat && p.lon);
        mapInstance = L.map(mapEl, {zoomControl: true}).setView([55, 37], 3);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '© CartoDB', maxZoom: 18
        }).addTo(mapInstance);
        pts.forEach(p => {
            const maskedIp = p.ip.replace(/(\d+\.\d+)\.\d+\.\d+/, '$1.xxx.xxx');
            L.circleMarker([p.lat, p.lon], {
                radius: 5, fillColor: '#22c55e', color: '#22c55e',
                weight: 1, opacity: 0.8, fillOpacity: 0.6
            }).bindPopup(`${maskedIp}<br>${p.city || ''} ${p.country || ''}`).addTo(mapInstance);
        });
        const el = document.getElementById('map-pts-count');
        if (el) el.textContent = pts.length + ' точек';
    }).catch(() => {});
}

/* === ЗАЩИТА === */
function initProtection() {
    fetch('data/fw_stats.json').then(r => r.json()).then(d => {
        // Метрики
        setEl('prot-total',      fmtInt(d.total_pkts));
        setEl('prot-total-bytes', fmtNum(d.total_bytes));
        setEl('prot-tsp-pkts',   fmtInt(d.tspublock?.pkts));
        setEl('prot-tsp-bytes',  fmtNum(d.tspublock?.bytes));
        setEl('prot-gov-pkts',   fmtInt(d.govblock?.pkts));
        setEl('prot-gov-bytes',  fmtNum(d.govblock?.bytes));
        setEl('prot-upd',        d.updated_at);

        // Топ IP TSPUBLOCK
        renderTopIps('prot-tsp-ips', d.tspublock?.top_ips || []);
        // Топ IP GOVBLOCK
        renderTopIps('prot-gov-ips', d.govblock?.top_ips || []);

        // Спарклайн по часам
        renderHourSpark('prot-tsp-spark', d.tspublock?.by_hour || {}, 'spark-blue');
        renderHourSpark('prot-gov-spark', d.govblock?.by_hour  || {}, 'spark-green');
    }).catch(() => {});
}

/* === СТРАНЫ === */
function initCountries() {
    Promise.all([
        fetch('data/countries.json').then(r => r.json()),
        fetch('data/country_traffic.json').then(r => r.json())
    ]).then(([countries, traffic]) => {
        renderCountriesTable('countries-tbl', countries, traffic);
    }).catch(() => {});
}

/* === ПОЛЬЗОВАТЕЛИ === */
function initUsers() {
    fetch('data/users.json').then(r => r.json()).then(d => {
        renderUsersTable('users-tbl', d);
    }).catch(() => {});
}

/* === ИСТОРИЯ === */
function initHistory() {
    Promise.all([
        fetch('data/history.json').then(r => r.json()),
        fetch('data/hours.json').then(r => r.json()),
        fetch('data/new_by_day.json').then(r => r.json()),
        fetch('data/new_vs_repeat.json').then(r => r.json()),
    ]).then(([history, hours, newByDay, nvr]) => {
        renderHistoryChart('hist-chart', history);
        renderHoursChart('hours-chart', hours);
        setEl('nvr-new',    nvr.new    || 0);
        setEl('nvr-repeat', nvr.repeat || 0);
    }).catch(() => {});
}

/* === БЕЛЫЙ СПИСОК === */
function initWhitelist() {
    fetch('data/whitelist.json').then(r => r.json()).then(d => {
        const enabled = d.enabled || false;
        const el = document.getElementById('wl-status');
        if (el) el.textContent = enabled ? 'включён' : 'выключён';
    }).catch(() => {});
}

/* === ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ === */
function setEl(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val ?? '—';
}

function fmtUptime(secs) {
    if (!secs) return '—';
    const d = Math.floor(secs / 86400);
    const h = Math.floor((secs % 86400) / 3600);
    const m = Math.floor((secs % 3600) / 60);
    return `${d}д ${h}ч ${m}м`;
}

function renderTopCountries(countries) {
    const el = document.getElementById('ov-top-countries');
    if (!el) return;
    const sorted = Object.entries(countries)
        .filter(([k]) => k !== '??')
        .sort((a, b) => (b[1].sessions || 0) - (a[1].sessions || 0))
        .slice(0, 8);
    const max = sorted[0]?.[1].sessions || 1;
    el.innerHTML = sorted.map(([code, info]) => `
        <div class="country-item">
            <span class="country-flag">${getFlagEmoji(code)}</span>
            <span class="country-name">${info.name || code}</span>
            <div class="country-bar"><div class="country-fill" style="width:${Math.round((info.sessions/max)*100)}%"></div></div>
            <span class="country-cnt">${fmtInt(info.ips?.length || 0)}</span>
        </div>
    `).join('');
}

function renderTopIps(elId, ips) {
    const el = document.getElementById(elId);
    if (!el) return;
    if (!ips.length) { el.innerHTML = '<div class="c-muted" style="font-size:11px;padding:8px 0;">Данные накапливаются...</div>'; return; }
    el.innerHTML = ips.slice(0, 10).map(([ip, cnt]) => `
        <div class="list-item">
            <div>
                <div class="list-main">${ip}</div>
            </div>
            <div class="list-val c-red">${fmtInt(cnt)}</div>
        </div>
    `).join('');
}

function renderHourSpark(elId, byHour, cls) {
    const el = document.getElementById(elId);
    if (!el) return;
    const hours = Array.from({length: 24}, (_, i) => byHour[String(i)] || 0);
    const max = Math.max(...hours, 1);
    el.innerHTML = hours.map(v => `
        <div class="spark-bar ${cls}" style="height:${Math.max(5, Math.round((v/max)*100))}%"></div>
    `).join('');
}

function renderCountriesTable(elId, countries, traffic) {
    const el = document.getElementById(elId);
    if (!el) return;
    const sorted = Object.entries(countries)
        .filter(([k]) => k !== '??')
        .sort((a, b) => (b[1].sessions || 0) - (a[1].sessions || 0));
    el.innerHTML = sorted.map(([code, info]) => {
        const tr = traffic[code] || {};
        return `<tr>
            <td>${getFlagEmoji(code)} ${info.name || code}</td>
            <td class="c-blue">${fmtInt(info.ips?.length || 0)}</td>
            <td>${fmtInt(info.sessions || 0)}</td>
            <td class="c-amber">${fmtNum(tr.bytes || 0)}</td>
        </tr>`;
    }).join('');
}

function renderUsersTable(elId, users) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.innerHTML = (users || []).map(u => `<tr>
        <td>${u.username}</td>
        <td class="c-green">${fmtInt(u.connections)}</td>
        <td class="c-blue">${fmtInt(u.active_ips)}</td>
        <td class="c-amber">${fmtNum(u.traffic)}</td>
    </tr>`).join('');
}

function renderHistoryChart(elId, history) {
    const el = document.getElementById(elId);
    if (!el || !window.Chart) return;
    const days = Object.keys(history).sort().slice(-14);
    new Chart(el, {
        type: 'bar',
        data: {
            labels: days.map(d => d.slice(5)),
            datasets: [{
                label: 'Подключений',
                data: days.map(d => history[d].connections || 0),
                backgroundColor: '#378ADD88',
                borderColor: '#378ADD',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#64748b' }, grid: { color: '#1e3050' } },
                y: { ticks: { color: '#64748b' }, grid: { color: '#1e3050' } }
            }
        }
    });
}

function renderHoursChart(elId, hours) {
    const el = document.getElementById(elId);
    if (!el || !window.Chart) return;
    const labels = Array.from({length: 24}, (_, i) => String(i).padStart(2,'0'));
    new Chart(el, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'IP по часам',
                data: labels.map(h => hours[String(parseInt(h))] || 0),
                borderColor: '#22c55e',
                backgroundColor: '#22c55e22',
                fill: true,
                tension: 0.4,
                pointRadius: 2
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#64748b' }, grid: { color: '#1e3050' } },
                y: { ticks: { color: '#64748b' }, grid: { color: '#1e3050' } }
            }
        }
    });
}

function getFlagEmoji(code) {
    if (!code || code === '??') return '🌐';
    try {
        return code.toUpperCase().replace(/./g, c =>
            String.fromCodePoint(c.charCodeAt(0) + 127397));
    } catch { return '🌐'; }
}

/* === АВТООБНОВЛЕНИЕ === */
let autoRefreshTimer = null;

function startAutoRefresh(interval = 60000) {
    stopAutoRefresh();
    autoRefreshTimer = setInterval(() => {
        const activeTab = document.querySelector('.tab-btn.active')?.dataset.tab;
        if (activeTab) {
            delete tabCache[activeTab];
            loadTab(activeTab);
        }
    }, interval);
}

function stopAutoRefresh() {
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
}

/* === ИНИЦИАЛИЗАЦИЯ === */
document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    startAutoRefresh(300000); // обновляем каждые 5 минут

    // Кнопка ручного обновления
    document.getElementById('btn-refresh')?.addEventListener('click', () => {
        const activeTab = document.querySelector('.tab-btn.active')?.dataset.tab;
        if (activeTab) {
            delete tabCache[activeTab];
            loadTab(activeTab);
            showToast('Обновлено');
        }
    });
});
