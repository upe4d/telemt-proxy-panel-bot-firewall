<?php
/**
 * tabs/countries.php — Вкладка Страны
 * @version 1.2.0
 * @updated 2026-04-10
 *
 * CHANGELOG:
 * v1.2.0 - Правильная структура: 4 карточки, нормальный скролл
 * v1.1.0 - Скролл, пояснения
 * v1.0.0 - Начальная версия
 */
require_once __DIR__ . '/../config.php';

$countries = @json_decode(file_get_contents(FILE_COUNTRIES), true) ?? [];
$traffic   = @json_decode(file_get_contents(FILE_COUNTRY_TRAFFIC), true) ?? [];
$cities    = @json_decode(file_get_contents(FILE_CITIES), true) ?? [];

function fmt_bytes($b) {
    if (!$b) return '0 B';
    if ($b >= 1e9) return round($b/1e9,2).' GB';
    if ($b >= 1e6) return round($b/1e6,2).' MB';
    if ($b >= 1e3) return round($b/1e3,1).' KB';
    return $b.' B';
}
function flag_emoji($code) {
    if (!$code || $code === '??') return '🌐';
    $chars = str_split(strtoupper($code));
    $r = '';
    foreach ($chars as $c) $r .= mb_chr(ord($c) + 127397, 'UTF-8');
    return $r;
}

$countries = array_filter($countries, fn($k) => $k !== '??', ARRAY_FILTER_USE_KEY);
uasort($countries, fn($a,$b) => ($b['sessions']??0) - ($a['sessions']??0));
$total_ips = array_sum(array_map(fn($c) => count($c['ips']??[]), $countries));
$total_sessions = array_sum(array_column($countries, 'sessions'));

uasort($cities, fn($a,$b) => ($b['sessions']??0) - ($a['sessions']??0));
$top_cities = array_slice($cities, 0, 20, true);
$max_city = max(array_column($top_cities, 'sessions') ?: [1]);

uasort($traffic, fn($a,$b) => ($b['bytes']??0) - ($a['bytes']??0));
$total_bytes = array_sum(array_column($traffic, 'bytes'));
$ru_pct = $total_bytes > 0 ? round(($traffic['RU']['bytes']??0)/$total_bytes*100,1) : 0;
?>

<!-- Блок 1: Метрики -->
<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:12px">
  <div class="metric">
    <div class="metric-lbl">Стран</div>
    <div class="metric-val c-blue"><?= count($countries) ?></div>
    <div class="metric-sub">уникальных</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Уникальных IP</div>
    <div class="metric-val c-green"><?= number_format($total_ips, 0, '.', ' ') ?></div>
    <div class="metric-sub">всего в истории</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Сессий</div>
    <div class="metric-val c-amber"><?= number_format($total_sessions, 0, '.', ' ') ?></div>
    <div class="metric-sub">за всё время</div>
  </div>
</div>

<!-- Блок 2: Страны + Города -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">

  <div class="card">
    <div class="card-hdr">
      Страны
      <input type="text" id="country-search" class="wl-input" placeholder="Поиск..." style="width:120px;padding:3px 8px;font-size:11px">
    </div>
    <div style="max-height:360px;overflow-y:auto">
      <table class="tbl" id="countries-tbl">
        <thead>
          <tr>
            <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Страна</th>
            <th style="position:sticky;top:0;background:var(--bg2);z-index:1">IP</th>
            <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Сессий</th>
            <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Трафик</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($countries as $code => $info):
          $ip_cnt = count($info['ips'] ?? []);
          $tr = $traffic[$code] ?? [];
        ?>
          <tr>
            <td><span style="font-family:sans-serif"><?= flag_emoji($code) ?></span> <?= htmlspecialchars($info['name'] ?? $code) ?></td>
            <td class="c-blue"><?= number_format($ip_cnt, 0, '.', ' ') ?></td>
            <td><?= number_format($info['sessions']??0, 0, '.', ' ') ?></td>
            <td class="c-amber"><?= fmt_bytes($tr['bytes']??0) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-hdr">Топ городов</div>
    <div style="max-height:360px;overflow-y:auto;padding-right:8px">
      <?php foreach ($top_cities as $city):
        $pct = $max_city > 0 ? round(($city['sessions']/$max_city)*100) : 0;
      ?>
      <div class="bar-row">
        <div class="bar-lbl" style="width:120px;font-size:10px"><?= htmlspecialchars($city['city']) ?></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:var(--blue)"></div></div>
        <div class="bar-val"><?= number_format($city['sessions'], 0, '.', ' ') ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- Блок 3: О данных + Статистика -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">

  <div class="card">
    <div class="card-hdr">О данных</div>
    <div style="font-size:11px;color:var(--muted);line-height:1.8">
      <p style="margin-bottom:8px">Трафик по странам — <strong style="color:var(--amber)">косвенный</strong> расчёт на основе среднего трафика на IP.</p>
      <p style="margin-bottom:8px">Формула: <code style="color:var(--blue)">общий_трафик / активных_IP × IP_страны</code></p>
      <p>Реальное распределение может отличаться — пользователи с высоким трафиком могут быть из любой страны.</p>
    </div>
  </div>

  <div class="card">
    <div class="card-hdr">Статистика трафика</div>
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:5px">
        <span style="color:var(--muted)">Россия</span>
        <span style="color:var(--amber);font-weight:700"><?= $ru_pct ?>%</span>
      </div>
      <div style="height:6px;background:var(--bg4);border-radius:3px;overflow:hidden">
        <div style="width:<?= $ru_pct ?>%;height:100%;background:var(--amber);border-radius:3px"></div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;font-size:11px">
      <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border)">
        <span style="color:var(--muted)">Всего стран</span>
        <span style="color:var(--text);font-weight:700"><?= count($traffic) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border)">
        <span style="color:var(--muted)">Всего трафика</span>
        <span style="color:var(--blue);font-weight:700"><?= fmt_bytes($total_bytes) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:5px 0">
        <span style="color:var(--muted)">Трафик России</span>
        <span style="color:var(--amber);font-weight:700"><?= fmt_bytes($traffic['RU']['bytes']??0) ?></span>
      </div>
    </div>
  </div>

</div>

<!-- Блок 4: Трафик по странам -->
<div class="card">
  <div class="card-hdr">Трафик по странам (косвенный)</div>
  <div style="max-height:300px;overflow-y:auto">
    <table class="tbl">
      <thead>
        <tr>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Страна</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Трафик</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Сессий</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Доля</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($traffic, 0, 30, true) as $code => $tr):
          if ($code === '??') continue;
          $pct = $total_bytes > 0 ? round(($tr['bytes']??0)/$total_bytes*100, 1) : 0;
        ?>
        <tr>
          <td><span style="font-family:sans-serif"><?= flag_emoji($code) ?></span> <?= htmlspecialchars($tr['name']??$code) ?></td>
          <td class="c-amber"><?= fmt_bytes($tr['bytes']??0) ?></td>
          <td><?= number_format($tr['sessions']??0, 0, '.', ' ') ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <div style="width:80px;height:4px;background:var(--bg4);border-radius:2px;overflow:hidden">
                <div style="width:<?= $pct ?>%;height:100%;background:var(--amber);border-radius:2px"></div>
              </div>
              <span style="font-size:10px;color:var(--muted)"><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.getElementById('country-search').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#countries-tbl tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
