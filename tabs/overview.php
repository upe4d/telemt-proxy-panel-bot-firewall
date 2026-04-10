<?php
/**
 * tabs/overview.php — Вкладка Обзор
 * @version 1.0.0
 * @updated 2026-04-10
 *
 * CHANGELOG:
 * v1.0.0 - Начальная версия
 */
require_once __DIR__ . '/../config.php';
$current  = @json_decode(file_get_contents(FILE_CURRENT), true) ?? [];
// Маскируем IP в списке активных
if (!empty($current['active_ips_list'])) {
    foreach ($current['active_ips_list'] as &$item) {
        $parts = explode('.', $item['ip'] ?? '');
        if (count($parts) === 4) {
            $parts[2] = 'xxx';
            $parts[3] = 'xxx';
            $item['ip'] = implode('.', $parts);
        }
    }
    unset($item);
}
$fw       = @json_decode(file_get_contents(FILE_FW), true) ?? [];
$countries = @json_decode(file_get_contents(FILE_COUNTRIES), true) ?? [];

function fmt_bytes($b) {
    if ($b >= 1e9) return round($b/1e9,1).' GB';
    if ($b >= 1e6) return round($b/1e6,1).' MB';
    if ($b >= 1e3) return round($b/1e3,1).' KB';
    return $b.' B';
}
function fmt_uptime($s) {
    $d = floor($s/86400); $h = floor(($s%86400)/3600); $m = floor(($s%3600)/60);
    return "{$d}д {$h}ч {$m}м";
}

$total_conn = $current['total_connections'] ?? 0;
$active_ips = $current['active_ips'] ?? 0;
$traffic    = $current['total_traffic'] ?? 0;
$uptime     = $current['uptime_seconds'] ?? 0;
$cpu        = $current['cpu'] ?? 0;
$ram        = $current['ram'] ?? 0;
$disk       = $current['disk'] ?? '—';
$logs       = $current['logs_size'] ?? 0;

$tsp_pkts   = $fw['tspublock']['pkts'] ?? 0;
$gov_pkts   = $fw['govblock']['pkts'] ?? 0;
$total_pkts = $fw['total_pkts'] ?? 0;
$total_bytes_fw = $fw['total_bytes'] ?? 0;

// Топ стран
$sorted_countries = array_filter($countries, fn($k) => $k !== '??', ARRAY_FILTER_USE_KEY);
uasort($sorted_countries, fn($a,$b) => ($b['sessions']??0) - ($a['sessions']??0));
$top_countries = array_slice($sorted_countries, 0, 8, true);
$max_sessions = max(array_column($top_countries, 'sessions') ?: [1]);

function flag_emoji($code) {
    if (!$code || $code === '??') return '🌐';
    $chars = str_split(strtoupper($code));
    $result = '';
    foreach ($chars as $c) {
        $result .= mb_chr(ord($c) + 127397, 'UTF-8');
    }
    return $result;
}
?>

<!-- МЕТРИКИ -->
<div class="metrics-grid" style="grid-template-columns:repeat(5,minmax(0,1fr))">
  <div class="metric">
    <div class="metric-lbl">Подключений</div>
    <div class="metric-val c-green"><?= number_format($total_conn, 0, '.', ' ') ?></div>
    <div class="metric-sub">активных сейчас</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Активных IP</div>
    <div class="metric-val c-blue"><?= number_format($active_ips, 0, '.', ' ') ?></div>
    <div class="metric-sub">уникальных</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Трафик</div>
    <div class="metric-val c-amber"><?= fmt_bytes($traffic) ?></div>
    <div class="metric-sub">за всё время</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">RST задропано</div>
    <div class="metric-val c-red"><?= number_format($total_pkts, 0, '.', ' ') ?></div>
    <div class="metric-sub">TSPUBLOCK + GOVIPS</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Uptime</div>
    <div class="metric-val" style="font-size:16px;padding-top:5px"><?= fmt_uptime($uptime) ?></div>
    <div class="metric-sub">CPU <?= $cpu ?>% · RAM <?= $ram ?>%</div>
  </div>
</div>

<!-- ОСНОВНАЯ СЕТКА -->
<div class="grid-2" style="margin-bottom:12px">

  <!-- ЛЕВАЯ КОЛОНКА: мини-карта -->
  <div class="card">
    <div class="card-hdr">
      Активные пользователи
      <span class="card-hdr-right" id="map-pts-count">загрузка...</span>
    </div>
    <div id="map-mini" style="height:340px;border-radius:8px;overflow:hidden;background:var(--bg3)"></div>
  </div>

  <!-- ПРАВАЯ КОЛОНКА -->
  <div style="display:flex;flex-direction:column;gap:12px">

    <!-- Защита -->
    <div class="card">
      <div class="card-hdr">Защита от ТСПУ</div>
      <div style="display:flex;flex-direction:column;gap:7px">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;background:var(--bg3);border-radius:7px">
          <div style="display:flex;align-items:center;gap:8px">
            <div style="width:26px;height:26px;border-radius:6px;background:#0c2a4a;display:flex;align-items:center;justify-content:center">
              <i class="bi bi-shield-fill" style="color:var(--blue);font-size:12px"></i>
            </div>
            <div>
              <div style="font-size:12px;font-weight:700">TSPUBLOCK</div>
              <div style="font-size:10px;color:var(--muted)">CyberOK Skipa · 145 IP</div>
            </div>
          </div>
          <div style="text-align:right">
            <div style="font-size:16px;font-weight:700;color:var(--blue)"><?= number_format($tsp_pkts, 0, '.', ' ') ?></div>
            <div style="font-size:10px;color:var(--muted)">пакетов задропано</div>
          </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;background:var(--bg3);border-radius:7px">
          <div style="display:flex;align-items:center;gap:8px">
            <div style="width:26px;height:26px;border-radius:6px;background:#052e16;display:flex;align-items:center;justify-content:center">
              <i class="bi bi-shield-fill" style="color:var(--green);font-size:12px"></i>
            </div>
            <div>
              <div style="font-size:12px;font-weight:700">GOVIPS</div>
              <div style="font-size:10px;color:var(--muted)">Госорганы РФ · 1145 подсетей</div>
            </div>
          </div>
          <div style="text-align:right">
            <div style="font-size:16px;font-weight:700;color:var(--green)"><?= number_format($gov_pkts, 0, '.', ' ') ?></div>
            <div style="font-size:10px;color:var(--muted)">пакетов задропано</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Топ стран -->
    <div class="card">
      <div class="card-hdr">Топ стран</div>
      <div class="countries-grid" style="overflow:hidden">
        <?php foreach ($top_countries as $code => $info):
          $pct = $max_sessions > 0 ? round(($info['sessions']/$max_sessions)*100) : 0;
          $cnt = count($info['ips'] ?? []);
        ?>
        <div class="country-item">
          <span class="country-flag" style="font-family:sans-serif"><?= flag_emoji($code) ?></span>
          <span class="country-name"><?= htmlspecialchars($info['name'] ?? $code) ?></span>
          <div class="country-bar"><div class="country-fill" style="width:<?= $pct ?>%"></div></div>
          <span class="country-cnt"><?= number_format($cnt, 0, '.', ' ') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<!-- НИЖНЯЯ СЕТКА -->
<div class="grid-3">

  <!-- Система -->
  <div class="card">
    <div class="card-hdr">Система</div>
    <div class="sys-row">
      <span class="sys-lbl">CPU</span>
      <div class="sys-right">
        <div class="sys-bar"><div class="sys-fill" style="width:<?= min($cpu,100) ?>%;background:<?= $cpu>80?'var(--red)':($cpu>60?'var(--amber)':'var(--green)') ?>"></div></div>
        <span class="sys-val"><?= $cpu ?>%</span>
      </div>
    </div>
    <div class="sys-row">
      <span class="sys-lbl">RAM</span>
      <div class="sys-right">
        <div class="sys-bar"><div class="sys-fill" style="width:<?= min($ram,100) ?>%;background:<?= $ram>80?'var(--red)':($ram>60?'var(--amber)':'var(--blue)') ?>"></div></div>
        <span class="sys-val"><?= $ram ?>%</span>
      </div>
    </div>
    <div class="sys-row">
      <span class="sys-lbl">Диск</span>
      <div class="sys-right">
        <span style="font-size:11px;font-weight:700;color:var(--text)"><?= htmlspecialchars($disk) ?></span>
      </div>
    </div>
    <div class="sys-row">
      <span class="sys-lbl">Логи</span>
      <div class="sys-right">
        <span style="font-size:11px;font-weight:700;color:var(--text)"><?= fmt_bytes($logs) ?></span>
      </div>
    </div>
    <div class="sys-row">
      <span class="sys-lbl">RST трафик</span>
      <div class="sys-right">
        <span style="font-size:11px;font-weight:700;color:var(--red)"><?= fmt_bytes($total_bytes_fw) ?></span>
      </div>
    </div>
  </div>

  <!-- Новые vs повторные -->
  <div class="card">
    <div class="card-hdr">Новые vs повторные IP</div>
    <div id="ov-nvr-content">
      <?php
      $nvr = @json_decode(file_get_contents(FILE_NVR), true) ?? [];
      $new = $nvr['new'] ?? 0;
      $rep = $nvr['repeat'] ?? 0;
      $total_nvr = max($new + $rep, 1);
      $pct_new = round(($new/$total_nvr)*100);
      $pct_rep = 100 - $pct_new;
      ?>
      <div style="height:8px;background:var(--bg4);border-radius:4px;overflow:hidden;margin-bottom:10px">
        <div style="height:100%;width:<?= $pct_new ?>%;background:var(--blue);border-radius:4px"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--muted);margin-bottom:14px">
        <span>Новые <?= $pct_new ?>%</span>
        <span>Повторные <?= $pct_rep ?>%</span>
      </div>
      <div style="display:flex;gap:12px">
        <div style="flex:1;text-align:center">
          <div style="font-size:22px;font-weight:700;color:var(--blue)"><?= number_format($new, 0, '.', ' ') ?></div>
          <div style="font-size:10px;color:var(--muted)">новых сегодня</div>
        </div>
        <div style="flex:1;text-align:center">
          <div style="font-size:22px;font-weight:700;color:var(--text)"><?= number_format($rep, 0, '.', ' ') ?></div>
          <div style="font-size:10px;color:var(--muted)">повторных</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Активность по часам -->
  <div class="card">
    <div class="card-hdr">Активность по часам</div>
    <div style="position:relative;height:100px">
      <canvas id="hours-mini-chart" role="img" aria-label="Активность по часам суток">Активность по часам</canvas>
    </div>
  </div>

</div>

<script>
// Мини-карта
function initMiniMap() {
  fetch('data/current.json').then(r=>r.json()).then(d => {
    const pts = (d.active_ips_list||[]).filter(p=>p.lat&&p.lon);
    document.getElementById('map-pts-count').textContent = pts.length + ' точек';
    if (!window.L) { setTimeout(initMiniMap, 300); return; }
    const map = L.map('map-mini', {zoomControl:false, attributionControl:false}).setView([55,37],3);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png').addTo(map);
    pts.forEach(p => L.circleMarker([p.lat,p.lon],{
      radius:4, fillColor:'#22c55e', color:'#22c55e',
      weight:1, opacity:0.8, fillOpacity:0.6
    }).addTo(map));
  }).catch(()=>{});
})();

// График по часам
(function() {
  fetch('data/hours.json').then(r=>r.json()).then(d => {
    const labels = Array.from({length:24},(_,i)=>String(i).padStart(2,'0'));
    new Chart(document.getElementById('hours-mini-chart'), {
      type: 'line',
      data: {
        labels,
        datasets: [{
          data: labels.map(h=>d[String(parseInt(h))]||0),
          borderColor: '#22c55e',
          backgroundColor: '#22c55e22',
          fill: true, tension: 0.4, pointRadius: 0
        }]
      },
      options: {
        responsive:true, maintainAspectRatio:false,
        plugins:{legend:{display:false}},
        scales:{
          x:{ticks:{color:'#64748b',font:{size:9}},grid:{color:'#1e3050'}},
          y:{ticks:{color:'#64748b',font:{size:9}},grid:{color:'#1e3050'}}
        }
      }
    });
  }).catch(()=>{});
})();
initMiniMap();
</script>
