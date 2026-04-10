<?php
/**
 * tabs/users.php — Вкладка Пользователи
 * @version 1.0.0
 * @updated 2026-04-10
 *
 * CHANGELOG:
 * v1.0.0 - Начальная версия
 */
require_once __DIR__ . '/../config.php';

$users   = @json_decode(file_get_contents(FILE_USERS), true) ?? [];
$ips     = @json_decode(file_get_contents(FILE_IPS), true) ?? [];
$nvr     = @json_decode(file_get_contents(FILE_NVR), true) ?? [];
$current = @json_decode(file_get_contents(FILE_CURRENT), true) ?? [];

function fmt_bytes($b) {
    if (!$b) return '0 B';
    if ($b >= 1e9) return round($b/1e9,2).' GB';
    if ($b >= 1e6) return round($b/1e6,2).' MB';
    if ($b >= 1e3) return round($b/1e3,1).' KB';
    return $b.' B';
}

$total_conn = array_sum(array_column($users, 'connections'));
$total_ips_active = array_sum(array_column($users, 'active_ips'));
$total_traffic = array_sum(array_column($users, 'traffic'));
$new_today = $nvr['new'] ?? 0;
$repeat_today = $nvr['repeat'] ?? 0;
$total_today = max($new_today + $repeat_today, 1);
$pct_new = round($new_today/$total_today*100);

// Топ активных IP
uasort($ips, fn($a,$b) => ($b['sessions']??0) - ($a['sessions']??0));
$top_ips = array_slice($ips, 0, 20, true);
?>

<!-- Метрики -->
<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:12px">
  <div class="metric">
    <div class="metric-lbl">Подключений</div>
    <div class="metric-val c-green"><?= number_format($total_conn, 0, '.', ' ') ?></div>
    <div class="metric-sub">активных сейчас</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Активных IP</div>
    <div class="metric-val c-blue"><?= number_format($total_ips_active, 0, '.', ' ') ?></div>
    <div class="metric-sub">уникальных</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Новых сегодня</div>
    <div class="metric-val c-amber"><?= number_format($new_today, 0, '.', ' ') ?></div>
    <div class="metric-sub"><?= $pct_new ?>% от активных</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Трафик</div>
    <div class="metric-val c-purple"><?= fmt_bytes($total_traffic) ?></div>
    <div class="metric-sub">за всё время</div>
  </div>
</div>

<!-- Пользователи + Новые vs повторные -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:12px;margin-bottom:12px">

  <!-- Таблица пользователей -->
  <div class="card">
    <div class="card-hdr">Пользователи telemt</div>
    <table class="tbl">
      <thead>
        <tr>
          <th>Пользователь</th>
          <th>Подключений</th>
          <th>Активных IP</th>
          <th>Трафик</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td style="font-weight:700;color:var(--text)"><?= htmlspecialchars($u['username'] ?? '—') ?></td>
          <td class="c-green"><?= number_format($u['connections'] ?? 0, 0, '.', ' ') ?></td>
          <td class="c-blue"><?= number_format($u['active_ips'] ?? 0, 0, '.', ' ') ?></td>
          <td class="c-amber"><?= fmt_bytes($u['traffic'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Новые vs повторные -->
  <div style="display:flex;flex-direction:column;gap:12px">
    <div class="card">
      <div class="card-hdr">Новые vs повторные IP</div>
      <div style="height:8px;background:var(--bg4);border-radius:4px;overflow:hidden;margin-bottom:10px">
        <div style="height:100%;width:<?= $pct_new ?>%;background:var(--blue);border-radius:4px"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--muted);margin-bottom:16px">
        <span>Новые <?= $pct_new ?>%</span>
        <span>Повторные <?= 100-$pct_new ?>%</span>
      </div>
      <div style="display:flex;gap:12px">
        <div style="flex:1;text-align:center;padding:10px;background:var(--bg3);border-radius:7px">
          <div style="font-size:22px;font-weight:700;color:var(--blue)"><?= number_format($new_today, 0, '.', ' ') ?></div>
          <div style="font-size:10px;color:var(--muted);margin-top:3px">новых сегодня</div>
        </div>
        <div style="flex:1;text-align:center;padding:10px;background:var(--bg3);border-radius:7px">
          <div style="font-size:22px;font-weight:700;color:var(--text)"><?= number_format($repeat_today, 0, '.', ' ') ?></div>
          <div style="font-size:10px;color:var(--muted);margin-top:3px">повторных</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-hdr">Всего IP в истории</div>
      <div style="font-size:28px;font-weight:700;color:var(--blue);margin-bottom:4px"><?= number_format(count($ips), 0, '.', ' ') ?></div>
      <div style="font-size:11px;color:var(--muted)">уникальных адресов за всё время</div>
    </div>
  </div>

</div>

<!-- Топ активных IP -->
<div class="card">
  <div class="card-hdr">
    Топ IP по сессиям
    <input type="text" id="ip-search" class="wl-input" placeholder="Поиск IP..." style="width:150px;padding:3px 8px;font-size:11px">
  </div>
  <div style="max-height:320px;overflow-y:auto">
    <table class="tbl" id="ips-tbl">
      <thead>
        <tr>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">IP</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Страна</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Город</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Сессий</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Первый визит</th>
          <th style="position:sticky;top:0;background:var(--bg2);z-index:1">Последний</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($top_ips as $ip => $info):
          $parts = explode('.', $ip);
          $masked = count($parts) === 4 ? $parts[0].'.'.$parts[1].'.xxx.xxx' : $ip;
          $geo = $info['geo'] ?? [];
          $first = $info['first_seen'] ? date('d.m.Y', $info['first_seen']) : '—';
          $last  = $info['last_seen']  ? date('d.m H:i', $info['last_seen'])  : '—';
        ?>
        <tr>
          <td style="font-family:monospace;color:var(--blue)"><?= htmlspecialchars($masked) ?></td>
          <td><?= htmlspecialchars($geo['country'] ?? '—') ?></td>
          <td style="color:var(--muted)"><?= htmlspecialchars($geo['city'] ?? '—') ?></td>
          <td class="c-amber"><?= number_format($info['sessions'] ?? 0, 0, '.', ' ') ?></td>
          <td style="color:var(--muted);font-size:11px"><?= $first ?></td>
          <td style="color:var(--muted);font-size:11px"><?= $last ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.getElementById('ip-search').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#ips-tbl tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
