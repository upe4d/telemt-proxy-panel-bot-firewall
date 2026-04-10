<?php
/**
 * tabs/map.php — Вкладка Карта
 * @version 1.0.0
 * @updated 2026-04-10
 *
 * CHANGELOG:
 * v1.0.0 - Начальная версия
 */
require_once __DIR__ . '/../config.php';
$current = @json_decode(file_get_contents(FILE_CURRENT), true) ?? [];
$countries = @json_decode(file_get_contents(FILE_COUNTRIES), true) ?? [];

$active_ips = $current['active_ips'] ?? 0;
$total_conn = $current['total_connections'] ?? 0;

// Список стран для фильтра
$country_list = array_filter($countries, fn($k) => $k !== '??', ARRAY_FILTER_USE_KEY);
uasort($country_list, fn($a,$b) => ($b['sessions']??0) - ($a['sessions']??0));
?>

<!-- Метрики над картой -->
<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:14px">
  <div class="metric">
    <div class="metric-lbl">Точек на карте</div>
    <div class="metric-val c-green" id="map-pts-total"><?= $active_ips ?></div>
    <div class="metric-sub">активных IP с координатами</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Подключений</div>
    <div class="metric-val c-blue"><?= number_format($total_conn, 0, '.', ' ') ?></div>
    <div class="metric-sub">активных сейчас</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Стран</div>
    <div class="metric-val c-amber"><?= count($country_list) ?></div>
    <div class="metric-sub">уникальных</div>
  </div>
</div>

<!-- Фильтры -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap">
  <select id="map-filter-country" class="wl-input" style="width:180px">
    <option value="">Все страны</option>
    <?php foreach ($country_list as $code => $info): ?>
    <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($info['name'] ?? $code) ?> (<?= count($info['ips']??[]) ?>)</option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-blue" id="map-filter-btn">Применить</button>
  <button class="btn" id="map-reset-btn">Сбросить</button>
  <span style="font-size:11px;color:var(--muted)" id="map-filter-info"></span>
</div>

<!-- Карта -->
<div class="card" style="padding:0;overflow:hidden">
  <div id="map-full" style="height:520px"></div>
</div>

<script>
(function() {
  let allPts = [];
  let mapFull = null;
  let markers = [];

  function maskIp(ip) {
    return ip.replace(/(\d+\.\d+)\.\d+\.\d+/, '$1.xxx.xxx');
  }

  function renderMarkers(pts) {
    markers.forEach(m => m.remove());
    markers = [];
    pts.forEach(p => {
      const m = L.circleMarker([p.lat, p.lon], {
        radius: 5, fillColor: '#22c55e', color: '#22c55e',
        weight: 1, opacity: 0.8, fillOpacity: 0.6
      }).bindPopup(`${maskIp(p.ip)}<br>${p.city || ''} ${p.country_name || p.country || ''}`);
      m.addTo(mapFull);
      markers.push(m);
    });
    document.getElementById('map-pts-total').textContent = pts.length;
  }

  fetch('data/current.json').then(r=>r.json()).then(d => {
    allPts = (d.active_ips_list||[]).filter(p=>p.lat&&p.lon);

    if (!window.L) { setTimeout(() => location.reload(), 500); return; }

    mapFull = L.map('map-full', {zoomControl:true}).setView([55,37],3);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
      attribution: '© CartoDB', maxZoom:18
    }).addTo(mapFull);

    renderMarkers(allPts);

    // Фильтр по стране
    document.getElementById('map-filter-btn').addEventListener('click', () => {
      const country = document.getElementById('map-filter-country').value;
      const filtered = country ? allPts.filter(p => p.country === country) : allPts;
      renderMarkers(filtered);
      document.getElementById('map-filter-info').textContent =
        country ? `Показано: ${filtered.length} точек` : '';
      if (filtered.length > 0) {
        const bounds = L.latLngBounds(filtered.map(p => [p.lat, p.lon]));
        mapFull.fitBounds(bounds, {padding:[20,20]});
      }
    });

    // Сброс фильтра
    document.getElementById('map-reset-btn').addEventListener('click', () => {
      document.getElementById('map-filter-country').value = '';
      document.getElementById('map-filter-info').textContent = '';
      renderMarkers(allPts);
      mapFull.setView([55,37],3);
    });

  }).catch(()=>{});
})();
</script>
