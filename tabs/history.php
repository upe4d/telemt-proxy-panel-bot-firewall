<?php
/**
 * tabs/history.php — Вкладка История
 * @version 1.0.0
 * @updated 2026-04-10
 *
 * CHANGELOG:
 * v1.0.0 - Начальная версия
 */
require_once __DIR__ . '/../config.php';

$history   = @json_decode(file_get_contents(FILE_HISTORY), true) ?? [];
$hours     = @json_decode(file_get_contents(FILE_HOURS), true) ?? [];
$new_by_day = @json_decode(file_get_contents(FILE_NEW_BY_DAY), true) ?? [];

function fmt_bytes($b) {
    if (!$b) return '0 B';
    if ($b >= 1e9) return round($b/1e9,2).' GB';
    if ($b >= 1e6) return round($b/1e6,2).' MB';
    if ($b >= 1e3) return round($b/1e3,1).' KB';
    return $b.' B';
}

ksort($history);
$days = array_keys($history);
$last7 = array_slice($history, -7, 7, true);
$peak_conn = max(array_column($history, 'connections') ?: [0]);
$peak_traffic = max(array_column($history, 'traffic') ?: [0]);
$total_days = count($history);
?>

<!-- Метрики -->
<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:12px">
  <div class="metric">
    <div class="metric-lbl">Дней работы</div>
    <div class="metric-val c-blue"><?= $total_days ?></div>
    <div class="metric-sub">в истории</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Пик подключений</div>
    <div class="metric-val c-green"><?= number_format($peak_conn, 0, '.', ' ') ?></div>
    <div class="metric-sub">за всё время</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Пик трафика</div>
    <div class="metric-val c-amber"><?= fmt_bytes($peak_traffic) ?></div>
    <div class="metric-sub">за один день</div>
  </div>
</div>

<!-- Графики -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">

  <div class="card">
    <div class="card-hdr">Подключения по дням</div>
    <div style="position:relative;height:180px">
      <canvas id="hist-conn-chart" role="img" aria-label="Подключения по дням">Подключения по дням</canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-hdr">Активность по часам суток</div>
    <div style="position:relative;height:180px">
      <canvas id="hours-chart" role="img" aria-label="Активность по часам">Активность по часам</canvas>
    </div>
  </div>

</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">

  <div class="card">
    <div class="card-hdr">Новые IP по дням</div>
    <div style="position:relative;height:180px">
      <canvas id="new-ips-chart" role="img" aria-label="Новые IP по дням">Новые IP по дням</canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-hdr">Трафик по дням</div>
    <div style="position:relative;height:180px">
      <canvas id="traffic-chart" role="img" aria-label="Трафик по дням">Трафик по дням</canvas>
    </div>
  </div>

</div>

<!-- Таблица по дням -->
<div class="card">
  <div class="card-hdr">История по дням</div>
  <div style="max-height:300px;overflow-y:auto">
    <table class="tbl">
      <thead>
        <tr>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Дата</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Пик подключений</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Пик IP</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Трафик</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Новых IP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($history, true) as $day => $d): ?>
        <tr>
          <td style="color:var(--muted)"><?= htmlspecialchars($day) ?></td>
          <td class="c-green"><?= number_format($d['connections'] ?? 0, 0, '.', ' ') ?></td>
          <td class="c-blue"><?= number_format($d['peak_ips'] ?? 0, 0, '.', ' ') ?></td>
          <td class="c-amber"><?= fmt_bytes($d['traffic'] ?? 0) ?></td>
          <td><?= number_format($new_by_day[$day] ?? 0, 0, '.', ' ') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function() {
  const history = <?= json_encode($history) ?>;
  const hours = <?= json_encode($hours) ?>;
  const newByDay = <?= json_encode($new_by_day) ?>;

  const days = Object.keys(history).sort().slice(-14);
  const labels = days.map(d => d.slice(5));

  const gridColor = '#1e3050';
  const tickColor = '#64748b';

  const baseOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { ticks: { color: tickColor, font: { size: 10 } }, grid: { color: gridColor } },
      y: { ticks: { color: tickColor, font: { size: 10 } }, grid: { color: gridColor } }
    }
  };

  // Подключения
  new Chart(document.getElementById('hist-conn-chart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{ data: days.map(d => history[d]?.connections || 0), backgroundColor: '#378ADD88', borderColor: '#378ADD', borderWidth: 1 }]
    },
    options: baseOpts
  });

  // Часы
  const hourLabels = Array.from({length:24}, (_,i) => String(i).padStart(2,'0'));
  new Chart(document.getElementById('hours-chart'), {
    type: 'line',
    data: {
      labels: hourLabels,
      datasets: [{ data: hourLabels.map(h => hours[String(parseInt(h))] || 0), borderColor: '#22c55e', backgroundColor: '#22c55e22', fill: true, tension: 0.4, pointRadius: 2 }]
    },
    options: baseOpts
  });

  // Новые IP
  new Chart(document.getElementById('new-ips-chart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{ data: days.map(d => newByDay[d] || 0), backgroundColor: '#f59e0b88', borderColor: '#f59e0b', borderWidth: 1 }]
    },
    options: baseOpts
  });

  // Трафик
  new Chart(document.getElementById('traffic-chart'), {
    type: 'line',
    data: {
      labels,
      datasets: [{ data: days.map(d => Math.round((history[d]?.traffic || 0) / 1e9 * 100) / 100), borderColor: '#a78bfa', backgroundColor: '#a78bfa22', fill: true, tension: 0.4, pointRadius: 2 }]
    },
    options: {
      ...baseOpts,
      scales: {
        x: { ticks: { color: tickColor, font: { size: 10 } }, grid: { color: gridColor } },
        y: { ticks: { color: tickColor, font: { size: 10 }, callback: v => v + ' GB' }, grid: { color: gridColor } }
      }
    }
  });
})();
</script>
