<?php
/**
 * Telemt MTProxy — Статистика дашборд
 *
 * @version 3.2.0
 * @updated 2026-03-24
 *
 * CHANGELOG:
 * v3.2.0 - Секция блокировок с GeoIP, анализ ботов
 * v3.1.0 - Пароль для белого списка
 * v3.0.0 - Модульная структура
 * v2.0.0 - Карта Leaflet, новые/повторные IP
 * v1.0.0 - Базовая версия
 *
 * ИНСТРУКЦИИ (НЕ УДАЛЯТЬ):
 * - Модули: /var/www/stats/modules/
 * - Данные: /var/www/stats/data/
 * - Коллектор: /opt/telemt-bot/collector.py
 * - Firewall: /opt/telemt-bot/firewall.py
 * - Пароль WL: в modules/whitelist.php → WL_PASSWORD
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/modules/helpers.php';
require_once __DIR__ . '/modules/whitelist.php';
require_once __DIR__ . '/modules/data.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Telemt Stats — <?= SITE_DOMAIN ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
:root {
    --bg:#0f1117; --bg2:#1a1d2e; --bg3:#242840;
    --border:#2a2d45; --text:#e2e8f0; --muted:#94a3b8;
    --green:#4ade80; --blue:#60a5fa; --yellow:#fbbf24;
    --purple:#a78bfa; --red:#f87171; --orange:#fb923c;
}
*{box-sizing:border-box;}
body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;margin:0;font-size:14px;}
.hdr{background:var(--bg2);border-bottom:1px solid var(--border);padding:12px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:200;gap:12px;}
.hdr h1{font-size:1em;margin:0;color:var(--green);font-weight:700;white-space:nowrap;}
.hdr .meta{font-size:0.75em;color:var(--muted);display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.sdot{width:8px;height:8px;border-radius:50%;background:<?= $status_color ?>;display:inline-block;margin-right:6px;box-shadow:0 0 6px <?= $status_color ?>;}
.sec{padding:18px 24px 0;}
.sec-title{font-size:0.72em;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:12px;display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none;}
.sec-title:hover{color:var(--text);}
.sec-title::after{content:'';flex:1;height:1px;background:var(--border);}
.metrics{display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:8px;}
.metric{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px 16px;transition:border-color .15s;}
.metric:hover{border-color:var(--blue);}
.metric .ico{font-size:1.1em;margin-bottom:5px;}
.metric .val{font-size:1.55em;font-weight:700;line-height:1.2;}
.metric .lbl{font-size:0.72em;color:var(--muted);margin-top:3px;}
.prog{background:var(--bg3);border-radius:3px;height:3px;margin-top:7px;}
.prog-f{height:100%;border-radius:3px;}
.cg{color:var(--green)!important;} .cb{color:var(--blue)!important;} .cy{color:var(--yellow)!important;}
.cp{color:var(--purple)!important;} .cr{color:var(--red)!important;} .co{color:var(--orange)!important;}
.cm{color:var(--muted)!important;}
.grid2{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:10px;}
.grid3{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:8px;}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;}
.card-title{font-size:0.72em;font-weight:700;color:var(--muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;}
.chart-wrap{position:relative;height:175px;}
.country-card{background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:12px 14px;display:flex;align-items:center;gap:10px;}
.cflag{font-size:1.7em;line-height:1;}
.cinfo{flex:1;min-width:0;}
.cinfo .name{font-size:0.83em;font-weight:600;color:var(--text);}
.cinfo .stats{font-size:0.72em;color:var(--muted);}
.tbl{width:100%;border-collapse:collapse;font-size:0.83em;color:var(--text);}
.tbl th{color:var(--muted);font-weight:600;font-size:0.72em;text-transform:uppercase;padding:7px 10px;border-bottom:1px solid var(--border);text-align:left;white-space:nowrap;background:var(--bg2);}
.tbl td{padding:7px 10px;border-bottom:1px solid rgba(42,45,69,.5);color:var(--text);vertical-align:middle;}
.tbl tr:hover td{background:rgba(255,255,255,.025);}
.tbl-wrap{max-height:380px;overflow-y:auto;}
.badge-c{background:rgba(96,165,250,.15);color:var(--blue)!important;border-radius:4px;padding:2px 7px;font-size:0.8em;white-space:nowrap;}
.badge-red{background:rgba(248,113,113,.15);color:var(--red)!important;border-radius:4px;padding:2px 7px;font-size:0.8em;white-space:nowrap;}
.badge-yellow{background:rgba(251,191,36,.15);color:var(--yellow)!important;border-radius:4px;padding:2px 7px;font-size:0.8em;white-space:nowrap;}
.badge-green{background:rgba(74,222,128,.15);color:var(--green)!important;border-radius:4px;padding:2px 7px;font-size:0.8em;white-space:nowrap;}
.ip-tag{font-family:monospace;background:var(--bg3);padding:2px 7px;border-radius:4px;font-size:0.82em;color:var(--text);}
.odot{width:6px;height:6px;border-radius:50%;background:var(--green);display:inline-block;box-shadow:0 0 4px var(--green);margin-right:5px;flex-shrink:0;}
.rdot{width:6px;height:6px;border-radius:50%;background:var(--red);display:inline-block;box-shadow:0 0 4px var(--red);margin-right:5px;flex-shrink:0;}
#worldmap{height:420px;width:100%;border-radius:10px;}
.leaflet-container{background:#0a0c14!important;}
.leaflet-popup-content-wrapper{background:var(--bg2);color:var(--text);border:1px solid var(--border);}
.leaflet-popup-tip{background:var(--bg2);}
.nvr-box{text-align:center;padding:20px;background:var(--bg3);border-radius:8px;}
.nvr-val{font-size:2.3em;font-weight:700;}
.nvr-lbl{color:var(--muted);font-size:0.82em;margin-top:4px;}
.nvr-sub{color:var(--muted);font-size:0.72em;margin-top:2px;}
.wl-badge{display:inline-flex;align-items:center;gap:6px;background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:4px 10px;font-size:0.82em;color:var(--text);}
.wl-badge.active{border-color:var(--green);}
.rm-btn{background:none;border:none;cursor:pointer;color:var(--red);padding:0;font-size:0.85em;line-height:1;}
.rm-btn:hover{color:#ff9999;}
.toggle-btn{background:none;border:2px solid var(--border);border-radius:8px;padding:8px 18px;cursor:pointer;font-size:0.85em;font-weight:600;transition:all .2s;color:var(--text);width:100%;}
.toggle-btn.on{border-color:var(--green);color:var(--green);}
.toggle-btn.off{border-color:var(--muted);color:var(--muted);}
.wl-input{background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:7px 12px;color:var(--text);font-size:0.85em;outline:none;width:100%;}
.wl-input:focus{border-color:var(--blue);}
.wl-select{background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:7px 12px;color:var(--text);font-size:0.85em;outline:none;width:100%;}
.wl-select:focus{border-color:var(--blue);}
.wl-btn{background:var(--blue);border:none;border-radius:6px;padding:7px 14px;color:#0f1117;font-size:0.85em;font-weight:600;cursor:pointer;white-space:nowrap;}
.wl-btn:hover{opacity:.85;}
.wl-btn-green{background:var(--green);border:none;border-radius:6px;padding:4px 10px;color:#0f1117;font-size:0.78em;font-weight:600;cursor:pointer;}
.wl-btn-green:hover{opacity:.85;}
.score-bar{height:4px;border-radius:2px;background:var(--bg3);margin-top:3px;}
.score-fill{height:100%;border-radius:2px;}
.footer{text-align:center;padding:28px;color:var(--muted);font-size:0.72em;}
::-webkit-scrollbar{width:5px;}
::-webkit-scrollbar-track{background:var(--bg);}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px;}
@media(max-width:640px){
    .sec{padding:12px 12px 0;}
    .hdr{padding:10px 12px;}
    .metrics{grid-template-columns:repeat(2,1fr);}
    .grid2,.grid3{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- Хедер -->
<div class="hdr">
  <h1><span class="sdot"></span>Telemt MTProxy Stats</h1>
  <div class="meta">
    <span><?= SITE_DOMAIN ?> &nbsp;v<?= htmlspecialchars($current['version'] ?? '') ?></span>
    <span class="d-none d-md-inline cm"><?= htmlspecialchars($current['datetime'] ?? '') ?></span>
    <a href="?" style="color:var(--muted);text-decoration:none" title="Обновить"><i class="bi bi-arrow-clockwise"></i></a>
    <?php if ($wl_enabled): ?>
    <span class="cg" style="font-size:0.85em"><i class="bi bi-shield-check"></i> Фильтр ВКЛ</span>
    <?php else: ?>
    <span class="cm" style="font-size:0.85em"><i class="bi bi-shield"></i> Фильтр ВЫКЛ</span>
    <?php endif; ?>
  </div>
</div>

<!-- Метрики (открыта) -->
<div class="sec mt-3">
  <div class="sec-title" onclick="tog('met',this)"><i class="bi bi-speedometer2"></i>Текущее состояние<i class="bi bi-chevron-up"></i></div>
  <div id="met-b">
    <div class="metrics">
      <div class="metric"><div class="ico">🔗</div><div class="val cg"><?= number_format($current['total_connections']??0) ?></div><div class="lbl">Подключений</div></div>
      <div class="metric"><div class="ico">🌍</div><div class="val cb"><?= $current['active_ips']??0 ?></div><div class="lbl">Активных IP</div></div>
      <div class="metric"><div class="ico">📦</div><div class="val cy"><?= fmt_bytes($current['total_traffic']??0) ?></div><div class="lbl">Трафик всего</div></div>
      <div class="metric"><div class="ico">⏱️</div><div class="val cp"><?= fmt_uptime($current['uptime_seconds']??0) ?></div><div class="lbl">Uptime</div></div>
      <div class="metric">
        <div class="ico">💻</div><div class="val <?= ($current['cpu']??0)>80?'cr':'cg' ?>"><?= $current['cpu']??0 ?>%</div><div class="lbl">CPU</div>
        <div class="prog"><div class="prog-f" style="width:<?= min($current['cpu']??0,100) ?>%;background:<?= ($current['cpu']??0)>80?'var(--red)':'var(--green)' ?>"></div></div>
      </div>
      <div class="metric">
        <div class="ico">🧠</div><div class="val <?= ($current['ram']??0)>85?'cr':'cb' ?>"><?= $current['ram']??0 ?>%</div><div class="lbl">RAM</div>
        <div class="prog"><div class="prog-f" style="width:<?= min($current['ram']??0,100) ?>%;background:var(--blue)"></div></div>
      </div>
      <div class="metric"><div class="ico">💾</div><div class="val co"><?= htmlspecialchars($current['disk']['used']??'N/A') ?></div><div class="lbl">Диск (<?= htmlspecialchars($current['disk']['pct']??'') ?>)</div></div>
      <div class="metric"><div class="ico">📋</div><div class="val co" style="font-size:1.1em"><?= htmlspecialchars($current['logs_size']??'N/A') ?></div><div class="lbl">Логи</div></div>
      <div class="metric"><div class="ico">🌐</div><div class="val cp"><?= count($countries) ?></div><div class="lbl">Стран</div></div>
      <div class="metric"><div class="ico">🏙️</div><div class="val cb"><?= count($cities) ?></div><div class="lbl">Городов</div></div>
      <div class="metric"><div class="ico">👤</div><div class="val cg"><?= count($ips) ?></div><div class="lbl">Всего IP</div></div>
      <div class="metric"><div class="ico">🚫</div><div class="val cr"><?= number_format($fw_counters['blocked_ipv4'] + $fw_counters['blocked_ipv6']) ?></div><div class="lbl">Заблокировано</div></div>
    </div>
  </div>
</div>

<!-- Карта (открыта) -->
<div class="sec mt-4">
  <div class="sec-title" onclick="tog('map',this)"><i class="bi bi-map"></i>Карта активных пользователей<i class="bi bi-chevron-up"></i></div>
  <div id="map-b">
    <div class="card" style="padding:0;overflow:hidden;">
      <div id="worldmap"></div>
    </div>
    <div style="font-size:0.72em;color:var(--muted);padding:6px 4px">
      🟢 Активные IP сейчас &nbsp;·&nbsp; Нажми на точку для деталей &nbsp;·&nbsp; Точек: <?= count($map_cities) ?>
    </div>
  </div>
</div>

<!-- Страны (открыта) -->
<div class="sec mt-4">
  <div class="sec-title" onclick="tog('countries',this)"><i class="bi bi-globe"></i>Страны<i class="bi bi-chevron-up"></i></div>
  <div id="countries-b">
    <div class="grid3">
      <?php
      $max_s = max(array_column(array_values($countries),'sessions') ?: [1]);
      foreach ($top_countries as $code => $c):
        $pct = round(($c['sessions']??0)/$max_s*100);
      ?>
      <div class="country-card">
        <div class="cflag"><?= country_flag($code) ?></div>
        <div class="cinfo">
          <div class="name"><?= htmlspecialchars($c['name']??$code) ?></div>
          <div class="stats"><?= count($c['ips']??[]) ?> IP · <?= $c['sessions']??0 ?> сессий</div>
          <div class="prog" style="margin-top:5px"><div class="prog-f" style="width:<?= $pct ?>%;background:var(--blue)"></div></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Блокировки (свёрнута) -->
<div class="sec mt-4">
  <div class="sec-title" onclick="tog('blocked',this)">
    <i class="bi bi-shield-x"></i>Заблокированные подключения
    <span class="cr" style="font-size:0.85em;font-weight:400">
      IPv4: <?= number_format($fw_counters['blocked_ipv4']) ?> &nbsp;·&nbsp;
      IPv6: <?= number_format($fw_counters['blocked_ipv6']) ?> &nbsp;·&nbsp;
      Уникальных IP: <?= count($blocked_ips) ?>
    </span>
    <i class="bi bi-chevron-down"></i>
  </div>
  <div id="blocked-b" style="display:none">
    <div class="grid2" style="margin-bottom:10px">

      <!-- Статистика блокировок -->
      <div class="card">
        <div class="card-title">Общая статистика</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
          <div class="nvr-box">
            <div class="nvr-val cr"><?= number_format($fw_counters['blocked_ipv4']) ?></div>
            <div class="nvr-lbl">🚫 Заблокировано IPv4</div>
          </div>
          <div class="nvr-box">
            <div class="nvr-val cr"><?= number_format($fw_counters['blocked_ipv6']) ?></div>
            <div class="nvr-lbl">🚫 Заблокировано IPv6</div>
          </div>
          <div class="nvr-box">
            <div class="nvr-val cy"><?= count($blocked_ips) ?></div>
            <div class="nvr-lbl">👤 Уникальных IP</div>
          </div>
          <div class="nvr-box">
            <div class="nvr-val co"><?= count($blocked_by_country) ?></div>
            <div class="nvr-lbl">🌍 Стран</div>
          </div>
        </div>
        <?php if ($blocked['last_updated']??''): ?>
        <div style="font-size:0.72em;color:var(--muted)">
          Последнее обновление: <?= htmlspecialchars($blocked['last_updated']) ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Топ заблокированных стран -->
      <div class="card">
        <div class="card-title">Топ стран по блокировкам</div>
        <div class="chart-wrap"><canvas id="blockedCountriesChart"></canvas></div>
      </div>

    </div>

    <!-- Таблица заблокированных IP -->
    <?php if ($wl_authed): ?>
    <div class="card">
      <div class="card-title">
        Заблокированные IP — анализ &nbsp;
        <span style="font-weight:400;color:var(--muted)">
          🤖 = бот &nbsp; ⚠️ = подозрительно &nbsp; 👤 = возможно пользователь
        </span>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>IP</th>
              <th>Страна</th>
              <th>Город</th>
              <th>Попыток</th>
              <th>Вердикт</th>
              <th>Причины</th>
              <th>Последний раз</th>
              <th>Действие</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach (array_slice($blocked_ips, 0, 200, true) as $ip => $data):
            $geo      = $data['geo'] ?? [];
            $analysis = $data['analysis'] ?? [];
            $score    = $analysis['score'] ?? 0;
            $verdict  = $analysis['verdict'] ?? '?';
            $rec      = $analysis['recommendation'] ?? '';
            $reasons  = $analysis['reasons'] ?? [];
            $count    = $data['count'] ?? 0;

            // Цвет вердикта
            if ($score >= 4)      { $badge_class = 'badge-red'; }
            elseif ($score >= 2)  { $badge_class = 'badge-yellow'; }
            else                  { $badge_class = 'badge-green'; }

            // Цвет прогресс-бара
            $score_color = $score >= 4 ? 'var(--red)' : ($score >= 2 ? 'var(--yellow)' : 'var(--green)');
            $score_pct   = min(100, $score * 16);
          ?>
          <tr>
            <td>
              <span class="rdot"></span>
              <span class="ip-tag"><?= htmlspecialchars($ip) ?></span>
            </td>
            <td>
              <?= country_flag($geo['country'] ?? '??') ?>
              <?= htmlspecialchars($geo['country_name'] ?? 'Unknown') ?>
            </td>
            <td class="cm"><?= htmlspecialchars($geo['city'] ?? '') ?></td>
            <td>
              <strong class="<?= $count >= 10 ? 'cr' : ($count >= 3 ? 'cy' : '') ?>"><?= $count ?></strong>
            </td>
            <td>
              <span class="<?= $badge_class ?>"><?= htmlspecialchars($verdict) ?></span>
              <div class="score-bar" style="width:60px">
                <div class="score-fill" style="width:<?= $score_pct ?>%;background:<?= $score_color ?>"></div>
              </div>
            </td>
            <td class="cm" style="font-size:0.78em;max-width:180px">
              <?= htmlspecialchars(implode(', ', $reasons)) ?>
            </td>
            <td class="cm" style="font-size:0.8em;white-space:nowrap">
              <?= htmlspecialchars($data['last'] ?? '') ?>
            </td>
            <td>
              <?php if ($score < 4): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="add_ip">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                <input type="hidden" name="note" value="<?= htmlspecialchars(($geo['country_name']??'').' '.($geo['city']??'')) ?>">
                <button type="submit" class="wl-btn-green" title="Добавить в белый список">
                  <i class="bi bi-plus"></i> В список
                </button>
              </form>
              <?php else: ?>
              <span class="cm" style="font-size:0.78em">Бот</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (count($blocked_ips) > 200): ?>
      <div style="font-size:0.72em;color:var(--muted);margin-top:8px">
        Показано 200 из <?= count($blocked_ips) ?> IP (отсортировано по количеству попыток)
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card">
      <div style="color:var(--muted);font-size:0.85em">
        <i class="bi bi-lock"></i> Войди в белый список чтобы видеть детали и управлять блокировками
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Трафик по странам (свёрнута) -->
<div class="sec mt-4">
  <div class="sec-title" onclick="tog('ctraf',this)"><i class="bi bi-bar-chart-line"></i>Трафик по странам (косвенный)<i class="bi bi-chevron-down"></i></div>
  <div id="ctraf-b" style="display:none">
    <div class="grid2">
      <div class="card">
        <div class="card-title">Распределение трафика</div>
        <div class="chart-wrap"><canvas id="ctrafChart"></canvas></div>
      </div>
      <div class="card">
        <div class="card-title">Детали по странам</div>
        <div class="tbl-wrap">
          <table class="tbl">
            <thead><tr><th>Страна</th><th>Трафик</th><th>Сессий</th></tr></thead>
            <tbody>
            <?php foreach ($top_traffic as $code => $ct): ?>
            <tr>
              <td><?= country_flag($code) ?> <?= htmlspecialchars($ct['name']??$code) ?></td>
              <td class="cy"><?= fmt_bytes($ct['bytes']??0) ?></td>
              <td class="cm"><?= $ct['sessions']??0 ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="font-size:0.72em;color:var(--muted);margin-top:10px">
          ⚠️ Косвенный подсчёт — пропорционально количеству сессий
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Новые vs Повторные (свёрнута) -->
<div class="sec mt-4">
  <div class="sec-title" onclick="tog('nvr',this)"><i class="bi bi-person-check"></i>Новые vs Повторные IP<i class="bi bi-chevron-down"></i></div>
  <div id="nvr-b" style="display:none">
    <div class="grid2">
      <div class="card">
        <div class="card-title">Сегодня</div>
        <div class="chart-wrap"><canvas id="nvrChart"></canvas></div>
      </div>
      <div class="card">
        <div class="card-title">Новые IP по дням</div>
        <div class="chart-wrap"><canvas id="newByDayChart"></canvas></div>
      </div>
      <div class="card">
        <div class="card-title">Статистика сегодня</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="nvr-box"><div class="nvr-val cg"><?= (int)($nvr['new']??0) ?></div><div class="nvr-lbl">🆕 Новых</div><div class="nvr-sub">первый раз сегодня</div></div>
          <div class="nvr-box"><div class="nvr-val cb"><?= (int)($nvr['repeat']??0) ?></div><div class="nvr-lbl">🔄 Повторных</div><div class="nvr-sub">уже были раньше</div></div>
        </div>
        <?php
        $tnvr    = ($nvr['new']??0) + ($nvr['repeat']??0);
        $new_pct = $tnvr > 0 ? round(($nvr['new']??0)/$tnvr*100) : 0;
        ?>
        <div style="margin-top:14px;font-size:0.78em;color:var(--muted)">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px">
            <span>Новые <?= $new_pct ?>%</span><span>Повторные <?= 100-$new_pct ?>%</span>
          </div>
          <div class="prog" style="height:6px"><div class="prog-f" style="width:<?= $new_pct ?>%;background:var(--green)"></div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Графики (свёрнута) -->
<div class="sec mt-4">
  <div class="sec-title" onclick="tog('charts',this)"><i class="bi bi-bar-chart"></i>Графики по дням<i class="bi bi-chevron-down"></i></div>
  <div id="charts-b" style="display:none">
    <div class="grid2">
      <div class="card"><div class="card-title">📈 Подключения по дням</div><div class="chart-wrap"><canvas id="connChart"></canvas></div></div>
      <div class="card"><div class="card-title">📦 Трафик по дням (MB)</div><div class="chart-wrap"><canvas id="trafficChart"></canvas></div></div>
      <div class="card"><div class="card-title">👥 Пик активных IP по дням</div><div class="chart-wrap"><canvas id="ipsChart"></canvas></div></div>
      <div class="card"><div class="card-title">⏰ Активность по часам (UTC)</div><div class="chart-wrap"><canvas id="hoursChart"></canvas></div></div>
    </div>
  </div>
</div>

<!-- Пользователи (свёрнута) -->
<?php if (!empty($users)): ?>
<div class="sec mt-4">
  <div class="sec-title" onclick="tog('users',this)"><i class="bi bi-people"></i>Пользователи<i class="bi bi-chevron-down"></i></div>
  <div id="users-b" style="display:none">
    <div class="grid2">
      <?php foreach ($users as $u): ?>
      <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span style="font-weight:700;color:var(--green);font-size:1.05em"><?= htmlspecialchars($u['username']??'') ?></span>
          <span class="badge-c"><?= $u['connections']??0 ?> conn</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:0.82em">
          <div><div class="cm">Активных IP</div><strong class="cb" style="font-size:1.4em"><?= $u['active_ips']??0 ?></strong></div>
          <div><div class="cm">Недавних IP</div><strong class="cy" style="font-size:1.4em"><?= $u['recent_ips']??0 ?></strong></div>
          <div><div class="cm">Трафик</div><strong class="cg"><?= fmt_bytes($u['traffic']??0) ?></strong></div>
          <div><div class="cm">Подключений</div><strong class="cb"><?= $u['connections']??0 ?></strong></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Топ городов (свёрнута) -->
<div class="sec mt-4">
  <div class="sec-title" onclick="tog('cities',this)"><i class="bi bi-building"></i>Топ городов<i class="bi bi-chevron-down"></i></div>
  <div id="cities-b" style="display:none">
    <div class="card">
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>#</th><th>Город</th><th>Страна</th><th>IP</th><th>Сессий</th></tr></thead>
          <tbody>
          <?php $i=1; foreach ($top_cities as $c): ?>
          <tr>
            <td class="cm"><?= $i++ ?></td>
            <td><?= htmlspecialchars($c['city']??'') ?></td>
            <td><span class="badge-c"><?= country_flag($c['country']??'') ?> <?= htmlspecialchars($c['country']??'') ?></span></td>
            <td class="cb"><?= count($c['ips']??[]) ?></td>
            <td class="cg"><?= $c['sessions']??0 ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Активные IP (свёрнута) -->
<div class="sec mt-4">
  <div class="sec-title" onclick="tog('aips',this)"><i class="bi bi-wifi"></i>Активные IP сейчас<i class="bi bi-chevron-down"></i></div>
  <div id="aips-b" style="display:none">
    <div class="card">
      <table class="tbl">
        <thead><tr><th>IP</th><th>Страна</th><th>Город</th></tr></thead>
        <tbody>
        <?php foreach ($current['active_ips_list']??[] as $ip_data): ?>
        <tr>
          <td><span class="odot"></span><span class="ip-tag"><?= htmlspecialchars($ip_data['ip']??'') ?></span></td>
          <td><?= country_flag($ip_data['country']??'??') ?> <?= htmlspecialchars($ip_data['country_name']??'Unknown') ?></td>
          <td class="cm"><?= htmlspecialchars($ip_data['city']??'') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- История IP (свёрнута) -->
<div class="sec mt-4">
  <div class="sec-title" onclick="tog('iph',this)"><i class="bi bi-clock-history"></i>История IP<i class="bi bi-chevron-down"></i></div>
  <div id="iph-b" style="display:none">
    <div class="card">
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>IP</th><th>Страна</th><th>Город</th><th>Сессий</th><th>Первый</th><th>Последний</th></tr></thead>
          <tbody>
          <?php foreach ($recent_ips as $d): ?>
          <tr>
            <td><span class="ip-tag"><?= htmlspecialchars($d['ip']??'') ?></span></td>
            <td><?= country_flag($d['geo']['country']??'??') ?> <?= htmlspecialchars($d['geo']['country_name']??'Unknown') ?></td>
            <td class="cm"><?= htmlspecialchars($d['geo']['city']??'') ?></td>
            <td class="cy"><?= $d['sessions']??0 ?></td>
            <td class="cm" style="font-size:0.8em"><?= date('d.m H:i', $d['first_seen']??0) ?></td>
            <td class="cm" style="font-size:0.8em"><?= date('d.m H:i', $d['last_seen']??0) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Белый список -->
<div class="sec mt-4 pb-4" id="wl-b-anchor">
  <div class="sec-title" onclick="tog('wl',this)">
    <i class="bi bi-shield-check"></i>Белый список (фильтрация)
    <?php if ($wl_authed): ?>
    <span class="cg" style="font-size:0.85em;font-weight:400"><i class="bi bi-unlock"></i> Управление открыто</span>
    <?php else: ?>
    <span class="cm" style="font-size:0.85em;font-weight:400"><i class="bi bi-lock"></i> Требуется пароль</span>
    <?php endif; ?>
    <i class="bi bi-chevron-<?= $wl_open?'up':'down' ?>"></i>
  </div>
  <div id="wl-b" style="display:<?= $wl_open?'block':'none' ?>">

    <?php if (!$wl_authed): ?>
    <div style="max-width:420px">
      <div class="card">
        <div class="card-title"><i class="bi bi-lock"></i> Вход в управление</div>
        <p style="font-size:0.82em;color:var(--muted);margin-bottom:16px">
          Для управления белым списком введи пароль.<br>
          Статистика видна всем без пароля.
        </p>
        <?php if ($wl_error): ?>
        <div style="color:var(--red);font-size:0.82em;margin-bottom:12px;padding:8px;background:rgba(248,113,113,.1);border-radius:6px">
          <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($wl_error) ?>
        </div>
        <?php endif; ?>
        <form method="post" style="display:flex;flex-direction:column;gap:10px">
          <input type="hidden" name="action" value="wl_login">
          <input type="password" name="password" placeholder="Пароль" class="wl-input" autofocus>
          <button type="submit" class="wl-btn" style="width:100%">
            <i class="bi bi-box-arrow-in-right"></i> Войти
          </button>
        </form>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
          <div style="font-size:0.72em;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">Статистика (только просмотр)</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">
            <div class="nvr-box"><div class="nvr-val cg"><?= $allowed_active ?></div><div class="nvr-lbl">✅ Разрешённых IP</div></div>
            <div class="nvr-box"><div class="nvr-val cr"><?= $blocked_active ?></div><div class="nvr-lbl">🚫 Вне списка</div></div>
          </div>
          <div style="font-size:0.78em;color:var(--muted)">
            Разрешённые страны:
            <?php foreach ($wl_countries as $code): ?>
            <?= country_flag($code) ?> <?= htmlspecialchars($wl_notes[$code] ?? $code) ?> &nbsp;
            <?php endforeach; ?>
          </div>
          <div style="margin-top:6px;font-size:0.78em;color:var(--muted)">
            Фильтр: <?= $wl_enabled ? '<span class="cg">ВКЛЮЧЁН</span>' : '<span class="cm">ВЫКЛЮЧЕН</span>' ?>
          </div>
        </div>
      </div>
    </div>

    <?php else: ?>
    <div class="grid2">
      <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="card-title" style="margin:0">Статус фильтра</div>
          <form method="post">
            <input type="hidden" name="action" value="wl_logout">
            <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:0.78em">
              <i class="bi bi-box-arrow-right"></i> Выйти
            </button>
          </form>
        </div>
        <form method="post" style="margin-bottom:16px">
          <input type="hidden" name="action" value="toggle">
          <button type="submit" class="toggle-btn <?= $wl_enabled?'on':'off' ?>">
            <?= $wl_enabled ? '<i class="bi bi-shield-check"></i> Фильтр ВКЛЮЧЁН' : '<i class="bi bi-shield"></i> Фильтр ВЫКЛЮЧЕН' ?>
            <span style="font-size:0.85em;opacity:.7"> — нажми чтобы <?= $wl_enabled?'выключить':'включить' ?></span>
          </button>
        </form>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px">
          <div class="nvr-box"><div class="nvr-val cg"><?= $allowed_active ?></div><div class="nvr-lbl">✅ Разрешённых</div><div class="nvr-sub">активных IP</div></div>
          <div class="nvr-box"><div class="nvr-val cr"><?= $blocked_active ?></div><div class="nvr-lbl">🚫 Вне списка</div><div class="nvr-sub"><?= $wl_enabled?'заблокированы':'фильтр выкл' ?></div></div>
        </div>
        <div style="font-size:0.75em;color:var(--muted);background:var(--bg3);padding:10px;border-radius:6px;line-height:1.6">
          ⚠️ <strong class="cy">Важно:</strong> firewall активен через iptables.<br>
          Изменения применяются автоматически в течение 5 секунд.
        </div>
      </div>

      <div class="card">
        <div class="card-title">🌍 Разрешённые страны</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px">
          <?php foreach ($wl_countries as $code): ?>
          <div class="wl-badge <?= $wl_enabled?'active':'' ?>">
            <?= country_flag($code) ?> <?= htmlspecialchars($wl_notes[$code] ?? $code) ?>
            <form method="post" style="display:inline;margin:0">
              <input type="hidden" name="action" value="remove_country">
              <input type="hidden" name="country" value="<?= htmlspecialchars($code) ?>">
              <button type="submit" class="rm-btn" title="Удалить">✕</button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <form method="post" style="display:flex;flex-direction:column;gap:8px">
          <input type="hidden" name="action" value="add_country">
          <select name="country" class="wl-select" onchange="updateCountryName(this)">
            <option value="">— Выбери страну —</option>
            <?php foreach (get_countries_list() as $code => $name): ?>
            <?php if (!in_array($code, $wl_countries)): ?>
            <option value="<?= $code ?>" data-name="<?= htmlspecialchars($name) ?>">
              <?= country_flag($code) ?> <?= htmlspecialchars($name) ?> (<?= $code ?>)
            </option>
            <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="name" id="country_name_input">
          <button type="submit" class="wl-btn"><i class="bi bi-plus"></i> Добавить страну</button>
        </form>
      </div>

      <div class="card">
        <div class="card-title">🔑 Разрешённые IP (VPN, свои)</div>
        <?php if (!empty($wl_ips)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px">
          <?php foreach ($wl_ips as $ip => $note): ?>
          <div class="wl-badge <?= $wl_enabled?'active':'' ?>">
            <span class="ip-tag"><?= htmlspecialchars($ip) ?></span>
            <?php if ($note && $note !== $ip): ?>
            <span class="cm" style="font-size:0.85em"><?= htmlspecialchars($note) ?></span>
            <?php endif; ?>
            <form method="post" style="display:inline;margin:0">
              <input type="hidden" name="action" value="remove_ip">
              <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
              <button type="submit" class="rm-btn" title="Удалить">✕</button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="cm" style="font-size:0.82em;margin-bottom:14px">Нет добавленных IP</div>
        <?php endif; ?>
        <form method="post" style="display:flex;flex-direction:column;gap:8px">
          <input type="hidden" name="action" value="add_ip">
          <input type="text" name="ip" placeholder="IP адрес (1.2.3.4 или IPv6)" class="wl-input">
          <input type="text" name="note" placeholder="Заметка (VPN Швеция, дом...)" class="wl-input">
          <button type="submit" class="wl-btn"><i class="bi bi-plus"></i> Добавить IP</button>
        </form>
      </div>

      <div class="card">
        <div class="card-title">🚫 IP вне белого списка (<?= count($outside_ips) ?>)</div>
        <?php if (empty($outside_ips)): ?>
        <div class="cg" style="font-size:0.85em">✅ Все активные IP из разрешённых стран</div>
        <?php else: ?>
        <div class="tbl-wrap" style="max-height:280px">
          <table class="tbl">
            <thead><tr><th>IP</th><th>Страна</th><th>Город</th></tr></thead>
            <tbody>
            <?php foreach ($outside_ips as $ip_data): ?>
            <tr>
              <td><span class="ip-tag"><?= htmlspecialchars($ip_data['ip']??'') ?></span></td>
              <td><?= country_flag($ip_data['country']??'??') ?> <?= htmlspecialchars($ip_data['country_name']??'Unknown') ?></td>
              <td class="cm"><?= htmlspecialchars($ip_data['city']??'') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="footer">
  <?= SITE_DOMAIN ?> &nbsp;·&nbsp; <?= SITE_CHANNEL ?> &nbsp;·&nbsp; v<?= htmlspecialchars($current['version']??'') ?> &nbsp;·&nbsp; Данные обновляются каждые 5 минут
</div>

<script>
Chart.defaults.color = '#94a3b8';
Chart.defaults.borderColor = '#2a2d45';
Chart.defaults.font.size = 11;

function mkBar(id, labels, data, color) {
  var ctx = document.getElementById(id);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: { labels: labels, datasets: [{ data: data, backgroundColor: color+'88', borderColor: color, borderWidth: 1, borderRadius: 4 }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: '#1a1d2e' }, ticks: { color: '#94a3b8', maxRotation: 45 } },
        y: { grid: { color: '#1a1d2e' }, ticks: { color: '#94a3b8' }, beginAtZero: true }
      }
    }
  });
}

mkBar('connChart',     <?= json_encode($day_labels) ?>,     <?= json_encode($day_conns) ?>,    '#4ade80');
mkBar('trafficChart',  <?= json_encode($day_labels) ?>,     <?= json_encode($day_traffic) ?>,  '#60a5fa');
mkBar('ipsChart',      <?= json_encode($day_labels) ?>,     <?= json_encode($day_ips) ?>,      '#fbbf24');
mkBar('hoursChart',    <?= json_encode($hour_labels) ?>,    <?= json_encode($hour_data) ?>,    '#a78bfa');
mkBar('newByDayChart', <?= json_encode($new_day_labels) ?>, <?= json_encode($new_day_data) ?>, '#4ade80');

// Трафик по странам
(function(){
  var ctx = document.getElementById('ctrafChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_map(fn($ct) => $ct['name']??'Unknown', array_values($top_traffic))) ?>,
      datasets: [{ data: <?= json_encode(array_map(fn($ct) => round(($ct['bytes']??0)/1048576,1), array_values($top_traffic))) ?>, backgroundColor: '#fb923c88', borderColor: '#fb923c', borderWidth: 1, borderRadius: 4 }]
    },
    options: {
      indexAxis: 'y', responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: '#1a1d2e' }, ticks: { color: '#94a3b8' }, title: { display: true, text: 'MB', color: '#64748b' } },
        y: { grid: { color: '#1a1d2e' }, ticks: { color: '#94a3b8' } }
      }
    }
  });
})();

// Заблокированные страны
(function(){
  var ctx = document.getElementById('blockedCountriesChart');
  if (!ctx) return;
  var data = <?= json_encode(array_values($blocked_by_country)) ?>;
  if (!data.length) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.map(function(d){ return d.name; }),
      datasets: [{ data: data.map(function(d){ return d.attempts; }), backgroundColor: '#f8717188', borderColor: '#f87171', borderWidth: 1, borderRadius: 4 }]
    },
    options: {
      indexAxis: 'y', responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: '#1a1d2e' }, ticks: { color: '#94a3b8' }, title: { display: true, text: 'Попыток', color: '#64748b' } },
        y: { grid: { color: '#1a1d2e' }, ticks: { color: '#94a3b8' } }
      }
    }
  });
})();

// NVR doughnut
(function(){
  var n = <?= (int)($nvr['new']??0) ?>, r = <?= (int)($nvr['repeat']??0) ?>;
  if (n + r === 0) return;
  var ctx = document.getElementById('nvrChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: { labels: ['Новые','Повторные'], datasets: [{ data: [n,r], backgroundColor: ['#4ade8099','#60a5fa99'], borderColor: ['#4ade80','#60a5fa'], borderWidth: 2 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, position: 'bottom', labels: { color: '#94a3b8', padding: 14 } } } }
  });
})();

// Leaflet карта
(function(){
  var map = L.map('worldmap', { center: [55,40], zoom: 4, scrollWheelZoom: false });
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { attribution: '© CartoDB', maxZoom: 12 }).addTo(map);
  var points = <?= json_encode(array_values($map_cities)) ?>;
  if (!points.length) return;
  var maxC = Math.max.apply(null, points.map(function(p){ return p.count; }));
  points.forEach(function(p) {
    var r = Math.max(7, Math.min(24, 7+(p.count/maxC)*17));
    var c = L.circleMarker([p.lat,p.lon], { radius:r, fillColor:'#4ade80', color:'#4ade80', weight:1, opacity:0.9, fillOpacity:0.3 }).addTo(map);
    var ips = p.ips.slice(0,5).join('<br>')+(p.ips.length>5?'<br><em>+'+(p.ips.length-5)+' ещё</em>':'');
    c.bindPopup('<b style="color:#4ade80">'+(p.city||p.country)+'</b><br><span style="color:#94a3b8">'+p.country+'</span><br><b style="color:#e2e8f0">'+p.count+'</b> <span style="color:#94a3b8">IP</span><br><small style="color:#64748b">'+ips+'</small>');
  });
})();

function tog(id, el) {
  var b = document.getElementById(id+'-b');
  if (!b) return;
  var ic = el ? el.querySelector('i:last-child') : null;
  var h = b.style.display === 'none';
  b.style.display = h ? '' : 'none';
  if (ic) ic.className = h ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
}

function updateCountryName(sel) {
  var opt = sel.options[sel.selectedIndex];
  document.getElementById('country_name_input').value = opt.dataset.name || '';
}

setTimeout(function(){ location.reload(); }, 300000);
</script>
</body>
</html>
