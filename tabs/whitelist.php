<?php
/**
 * tabs/whitelist.php — Вкладка Фильтрация
 * @version 1.0.0
 * @updated 2026-04-10
 *
 * CHANGELOG:
 * v1.0.0 - Начальная версия
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/whitelist.php';

$wl = @json_decode(file_get_contents(FILE_WHITELIST), true) ?? [];
$enabled = $wl['enabled'] ?? false;
$allowed_countries = $wl['allowed_countries'] ?? [];
$allowed_ips = $wl['allowed_ips'] ?? [];
$notes = $wl['notes'] ?? [];

function flag_emoji($code) {
    if (!$code || $code === '??') return '🌐';
    $chars = str_split(strtoupper($code));
    $r = '';
    foreach ($chars as $c) $r .= mb_chr(ord($c) + 127397, 'UTF-8');
    return $r;
}

// Список доступных стран
$all_countries = [
    'RU'=>'Россия','UA'=>'Украина','BY'=>'Беларусь','KZ'=>'Казахстан',
    'DE'=>'Германия','NL'=>'Нидерланды','FR'=>'Франция','GB'=>'Великобритания',
    'US'=>'США','CN'=>'Китай','TR'=>'Турция','PL'=>'Польша','SE'=>'Швеция',
    'FI'=>'Финляндия','NO'=>'Норвегия','LV'=>'Латвия','LT'=>'Литва','EE'=>'Эстония',
    'MD'=>'Молдова','AM'=>'Армения','GE'=>'Грузия','AZ'=>'Азербайджан','UZ'=>'Узбекистан',
];
?>

<!-- Статус фильтрации -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:12px">
  <div class="metric">
    <div class="metric-lbl">Статус фильтрации</div>
    <div class="metric-val <?= $enabled ? 'c-green' : 'c-muted' ?>"><?= $enabled ? 'включена' : 'выключена' ?></div>
    <div class="metric-sub">GeoIP фильтр по странам</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Разрешённых стран</div>
    <div class="metric-val c-blue"><?= count($allowed_countries) ?></div>
    <div class="metric-sub">в белом списке</div>
  </div>
  <div class="metric">
    <div class="metric-lbl">Разрешённых IP</div>
    <div class="metric-val c-amber"><?= count($allowed_ips) ?></div>
    <div class="metric-sub">персональных</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">

  <!-- Управление фильтром -->
  <div class="card">
    <div class="card-hdr">Управление фильтром</div>

    <?php if (!wl_is_auth()): ?>
    <!-- Форма входа -->
    <div style="margin-bottom:14px">
      <div style="font-size:11px;color:var(--muted);margin-bottom:8px">Введите пароль для управления фильтрацией</div>
      <div style="display:flex;gap:8px">
        <input type="password" id="wl-pass" class="wl-input" placeholder="Пароль..." style="flex:1">
        <button class="btn btn-blue" onclick="wlLogin()">Войти</button>
      </div>
      <div id="wl-err" style="font-size:11px;color:var(--red);margin-top:6px;display:none">Неверный пароль</div>
    </div>
    <?php else: ?>
    <!-- Управление -->
    <div style="margin-bottom:14px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <span style="font-size:12px;color:var(--text)">Фильтрация <?= $enabled ? '<span class="badge badge-ok">включена</span>' : '<span class="badge badge-err">выключена</span>' ?></span>
        <button class="btn <?= $enabled ? 'btn-red' : 'btn-blue' ?>" onclick="wlToggle()"><?= $enabled ? 'Выключить' : 'Включить' ?></button>
      </div>

      <?php if ($enabled): ?>
      <div style="padding:10px;background:var(--bg3);border-radius:7px;font-size:11px;color:var(--amber);margin-bottom:12px">
        <i class="bi bi-exclamation-triangle"></i> Фильтр активен — подключения только из разрешённых стран и IP
      </div>
      <?php endif; ?>

      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <select id="wl-country-add" class="wl-input" style="flex:1">
          <option value="">Выберите страну...</option>
          <?php foreach ($all_countries as $code => $name): ?>
          <option value="<?= $code ?>" <?= in_array($code, $allowed_countries) ? 'disabled' : '' ?>><?= flag_emoji($code) ?> <?= $name ?> (<?= $code ?>)</option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-blue" onclick="wlAddCountry()">+ Добавить</button>
      </div>

      <div style="font-size:11px;color:var(--muted);margin-bottom:6px">Разрешённые страны:</div>
      <div id="wl-countries-list">
        <?php foreach ($allowed_countries as $code): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 8px;background:var(--bg3);border-radius:6px;margin-bottom:4px" id="wl-c-<?= $code ?>">
          <span style="font-family:sans-serif"><?= flag_emoji($code) ?></span>
          <span style="font-size:12px;flex:1;margin-left:8px"><?= htmlspecialchars($notes[$code] ?? $code) ?></span>
          <button class="btn btn-red" style="padding:2px 8px;font-size:10px" onclick="wlRemoveCountry('<?= $code ?>')">✕</button>
        </div>
        <?php endforeach; ?>
        <?php if (empty($allowed_countries)): ?>
        <div style="font-size:11px;color:var(--muted);padding:8px 0">Список пуст — все страны разрешены</div>
        <?php endif; ?>
      </div>

      <div style="margin-top:12px;border-top:1px solid var(--border);padding-top:12px">
        <div style="font-size:11px;color:var(--muted);margin-bottom:6px">Добавить конкретный IP:</div>
        <div style="display:flex;gap:8px">
          <input type="text" id="wl-ip-add" class="wl-input" placeholder="1.2.3.4" style="flex:1">
          <button class="btn btn-blue" onclick="wlAddIp()">+ IP</button>
        </div>
      </div>

      <button class="btn" style="margin-top:10px;font-size:10px;color:var(--muted)" onclick="wlLogout()">Выйти</button>
    </div>
    <?php endif; ?>
  </div>

  <!-- О фильтрации -->
  <div style="display:flex;flex-direction:column;gap:12px">
    <div class="card">
      <div class="card-hdr">Как работает фильтрация</div>
      <div style="font-size:11px;color:var(--muted);line-height:1.8">
        <p style="margin-bottom:8px">GeoIP фильтр блокирует подключения к MTProxy от IP адресов не из разрешённых стран. Реализован через iptables цепочку <code style="color:var(--blue)">TELEMT_WL</code>.</p>
        <p style="margin-bottom:8px"><strong style="color:var(--amber)">Важно:</strong> многие российские пользователи используют VPN с европейскими IP — они будут заблокированы если включить фильтр только для RU.</p>
        <p>Рекомендуется <strong style="color:var(--text)">не включать</strong> фильтр в период активных блокировок.</p>
      </div>
    </div>

    <div class="card">
      <div class="card-hdr">Разрешённые IP</div>
      <?php if (empty($allowed_ips)): ?>
      <div style="font-size:11px;color:var(--muted)">Персональных IP не добавлено</div>
      <?php else: ?>
      <div style="max-height:150px;overflow-y:auto">
        <?php foreach ($allowed_ips as $ip => $note): ?>
        <div class="list-item">
          <div class="list-main"><?= htmlspecialchars($ip) ?></div>
          <?php if (wl_is_auth()): ?>
          <button class="btn btn-red" style="padding:2px 8px;font-size:10px" onclick="wlRemoveIp('<?= htmlspecialchars($ip) ?>')">✕</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
function wlLogin() {
    const pass = document.getElementById('wl-pass').value;
    fetch('modules/whitelist.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=wl_login&password=' + encodeURIComponent(pass)
    }).then(r => r.json()).then(d => {
        if (d.ok) location.reload();
        else { document.getElementById('wl-err').style.display = 'block'; }
    });
}

function wlLogout() {
    fetch('modules/whitelist.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=wl_logout'
    }).then(() => location.reload());
}

function wlToggle() {
    fetch('modules/whitelist.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=wl_toggle'
    }).then(r => r.json()).then(d => {
        if (d.ok) location.reload();
    });
}

function wlAddCountry() {
    const code = document.getElementById('wl-country-add').value;
    if (!code) return;
    fetch('modules/whitelist.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=wl_add_country&country=' + encodeURIComponent(code)
    }).then(r => r.json()).then(d => {
        if (d.ok) location.reload();
    });
}

function wlRemoveCountry(code) {
    fetch('modules/whitelist.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=wl_remove_country&country=' + encodeURIComponent(code)
    }).then(r => r.json()).then(d => {
        if (d.ok) location.reload();
    });
}

function wlAddIp() {
    const ip = document.getElementById('wl-ip-add').value.trim();
    if (!ip) return;
    fetch('modules/whitelist.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=wl_add_ip&ip=' + encodeURIComponent(ip)
    }).then(r => r.json()).then(d => {
        if (d.ok) location.reload();
        else alert('Ошибка: ' + (d.error || 'неверный IP'));
    });
}

function wlRemoveIp(ip) {
    fetch('modules/whitelist.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=wl_remove_ip&ip=' + encodeURIComponent(ip)
    }).then(r => r.json()).then(d => {
        if (d.ok) location.reload();
    });
}
</script>
