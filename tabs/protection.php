<?php
/**
 * tabs/protection.php — Вкладка Защита
 * @version 1.0.0
 * @updated 2026-04-10
 *
 * CHANGELOG:
 * v1.0.0 - Начальная версия
 */
require_once __DIR__ . '/../config.php';

$fw = @json_decode(file_get_contents(FILE_FW), true) ?? [];

function fmt_bytes($b) {
    if (!$b) return '0 B';
    if ($b >= 1e9) return round($b/1e9,2).' GB';
    if ($b >= 1e6) return round($b/1e6,2).' MB';
    if ($b >= 1e3) return round($b/1e3,1).' KB';
    return $b.' B';
}

$tsp_pkts   = $fw['tspublock']['pkts']  ?? 0;
$tsp_bytes  = $fw['tspublock']['bytes'] ?? 0;
$tsp_ips    = $fw['tspublock']['top_ips'] ?? [];
$tsp_hours  = $fw['tspublock']['by_hour'] ?? [];
$gov_pkts   = $fw['govblock']['pkts']   ?? 0;
$gov_bytes  = $fw['govblock']['bytes']  ?? 0;
$gov_ips    = $fw['govblock']['top_ips'] ?? [];
$gov_hours  = $fw['govblock']['by_hour'] ?? [];
$total_pkts = $fw['total_pkts']  ?? 0;
$total_bytes = $fw['total_bytes'] ?? 0;
$updated_at = $fw['updated_at']  ?? '—';

// Провайдеры из топ IP (заглушка — нужен whois)
$tsp_top5 = array_slice($tsp_ips, 0, 10);
$gov_top5 = array_slice($gov_ips, 0, 10);

// Спарклайн по часам
function hours_spark($hours, $color) {
    $max = max(array_values($hours) ?: [1]);
    $out = '<div class="spark" style="position:relative;overflow:visible">';
    for ($i = 0; $i < 24; $i++) {
        $v = $hours[(string)$i] ?? 0;
        $h = ($max > 0 && $v > 0) ? max(15, round(($v/$max)*100)) : 2;
        $label = $v > 0 ? "<span style='position:absolute;bottom:100%;left:50%;transform:translateX(-50%);font-size:8px;color:var(--muted);white-space:nowrap'>{$v}</span>" : "";
        $out .= "<div class='spark-bar spark-{$color}' style='height:{$h}%;position:relative'>{$label}</div>";
    }
    $out .= '</div>';
    return $out;
}
?>

<!-- Итоговые метрики -->
<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px">
  <div class="metric">
    <div class="metric-lbl">RST задропано всего</div>
    <div class="metric-val c-red"><?= number_format($total_pkts, 0, '.', ' ') ?></div>
    <div class="metric-sub"><?= fmt_bytes($total_bytes) ?> трафика</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">TSPUBLOCK</div>
    <div class="metric-val c-blue"><?= number_format($tsp_pkts, 0, '.', ' ') ?></div>
    <div class="metric-sub"><?= fmt_bytes($tsp_bytes) ?> · CyberOK 145 IP</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">GOVIPS</div>
    <div class="metric-val c-green"><?= number_format($gov_pkts, 0, '.', ' ') ?></div>
    <div class="metric-sub"><?= fmt_bytes($gov_bytes) ?> · 1145 подсетей</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Обновлено</div>
    <div class="metric-val" style="font-size:12px;padding-top:6px"><?= htmlspecialchars($updated_at) ?></div>
    <div class="metric-sub">каждые 10 минут</div>
  </div>
</div>

<!-- TSPUBLOCK + GOVIPS карточки -->
<div class="grid-2-eq" style="margin-bottom:12px">

  <!-- TSPUBLOCK -->
  <div class="card">
    <div class="card-hdr">
      <span style="display:flex;align-items:center;gap:7px">
        <span style="width:26px;height:26px;border-radius:6px;background:#0c2a4a;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-shield-fill" style="color:var(--blue);font-size:12px"></i>
        </span>
        TSPUBLOCK — CyberOK Skipa
      </span>
      <span class="badge badge-ok">активен</span>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">
      <div style="background:var(--bg3);border-radius:7px;padding:9px 11px">
        <div style="font-size:10px;color:var(--muted);margin-bottom:3px">Пакетов</div>
        <div style="font-size:18px;font-weight:700;color:var(--blue)"><?= number_format($tsp_pkts, 0, '.', ' ') ?></div>
      </div>
      <div style="background:var(--bg3);border-radius:7px;padding:9px 11px">
        <div style="font-size:10px;color:var(--muted);margin-bottom:3px">Трафик</div>
        <div style="font-size:18px;font-weight:700;color:var(--blue)"><?= fmt_bytes($tsp_bytes) ?></div>
      </div>
      <div style="background:var(--bg3);border-radius:7px;padding:9px 11px">
        <div style="font-size:10px;color:var(--muted);margin-bottom:3px">IP в списке</div>
        <div style="font-size:18px;font-weight:700;color:var(--blue)">145</div>
      </div>
    </div>

    <div style="font-size:10px;color:var(--muted);margin-bottom:5px">Активность по часам (24ч)</div>
    <?= hours_spark($tsp_hours, 'blue') ?>

    <?php if (!empty($tsp_top5)): ?>
    <div style="margin-top:12px">
      <div class="card-hdr" style="margin-bottom:8px">Топ источников RST</div>
      <?php foreach ($tsp_top5 as [$ip, $cnt]): ?>
      <div class="list-item">
        <div>
          <div class="list-main"><?= htmlspecialchars($ip) ?></div>
        </div>
        <div class="list-val c-red"><?= number_format($cnt, 0, '.', ' ') ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="margin-top:12px;font-size:11px;color:var(--muted);padding:8px 0">
      Данные накапливаются — логирование включено <?= date('d.m.Y') ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- GOVIPS -->
  <div class="card">
    <div class="card-hdr">
      <span style="display:flex;align-items:center;gap:7px">
        <span style="width:26px;height:26px;border-radius:6px;background:#052e16;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-shield-fill" style="color:var(--green);font-size:12px"></i>
        </span>
        GOVIPS — Госорганы РФ
      </span>
      <span class="badge badge-ok">активен</span>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">
      <div style="background:var(--bg3);border-radius:7px;padding:9px 11px">
        <div style="font-size:10px;color:var(--muted);margin-bottom:3px">Пакетов</div>
        <div style="font-size:18px;font-weight:700;color:var(--green)"><?= number_format($gov_pkts, 0, '.', ' ') ?></div>
      </div>
      <div style="background:var(--bg3);border-radius:7px;padding:9px 11px">
        <div style="font-size:10px;color:var(--muted);margin-bottom:3px">Трафик</div>
        <div style="font-size:18px;font-weight:700;color:var(--green)"><?= fmt_bytes($gov_bytes) ?></div>
      </div>
      <div style="background:var(--bg3);border-radius:7px;padding:9px 11px">
        <div style="font-size:10px;color:var(--muted);margin-bottom:3px">Подсетей</div>
        <div style="font-size:18px;font-weight:700;color:var(--green)">1 145</div>
      </div>
    </div>

    <div style="font-size:10px;color:var(--muted);margin-bottom:5px">Активность по часам (24ч)</div>
    <?= hours_spark($gov_hours, 'green') ?>

    <?php if (!empty($gov_top5)): ?>
    <div style="margin-top:12px">
      <div class="card-hdr" style="margin-bottom:8px">Топ источников RST</div>
      <?php foreach ($gov_top5 as [$ip, $cnt]): ?>
      <div class="list-item">
        <div>
          <div class="list-main"><?= htmlspecialchars($ip) ?></div>
        </div>
        <div class="list-val c-red"><?= number_format($cnt, 0, '.', ' ') ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="margin-top:12px;font-size:11px;color:var(--muted);padding:8px 0">
      Данные накапливаются — логирование включено <?= date('d.m.Y') ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- Информация о методе -->
<div class="card">
  <div class="card-hdr">Как работает защита</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:12px;color:var(--muted);line-height:1.7">
    <div>
      <div style="color:var(--blue);font-weight:700;margin-bottom:5px">TSPUBLOCK — CyberOK Skipa</div>
      Задокументированный список IP сканеров ТСПУ от независимых исследователей. Включает сканеры CyberOK, ГРЧЦ, НКЦКИ. Обновляется автоматически раз в сутки с GitHub. Блокирует RST пакеты от 145 известных адресов DPI-оборудования.
    </div>
    <div>
      <div style="color:var(--green);font-weight:700;margin-bottom:5px">GOVIPS — Подсети госорганов РФ</div>
      Подсети Роскомнадзора, ФСБ, МВД, ГРЧЦ и других госорганов РФ. Источник: <a href="https://github.com/C24Be/AS_Network_List" target="_blank">C24Be/AS_Network_List</a>. 1145 подсетей, обновляется ежедневно. Блокирует RST и прямые подключения от госсетей.
    </div>
  </div>
  <div style="margin-top:12px;padding:10px;background:var(--bg3);border-radius:7px;font-size:11px;color:var(--muted)">
    <strong style="color:var(--green)">CyberOK Skipa</strong> — задокументированные IP сканеров ТСПУ (ГРЧЦ, НКЦКИ). <strong style="color:var(--blue)">GOVIPS</strong> — подсети госорганов РФ: Роскомнадзор, ФСБ, МВД. Все адреса проверены независимыми исследователями. RST от этих IP дропается — соединения пользователей удерживаются.
    Источники: <a href="https://github.com/tread-lightly/CyberOK_Skipa_ips" target="_blank">CyberOK_Skipa_ips</a> · <a href="https://github.com/C24Be/AS_Network_List" target="_blank">C24Be/AS_Network_List</a>
  </div>
</div>
