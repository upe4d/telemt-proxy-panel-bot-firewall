<?php
/**
 * tabs/radar.php — Вкладка Радар DC Telegram
 * @version 1.1.0
 * @updated 2026-04-11
 *
 * CHANGELOG:
 * v1.1.0 - Данные через PHP прокси, крупнее шрифты, QR попап
 * v1.0.0 - Начальная версия
 */
$proxies = [
  'se' => [
    'flag' => '🇸🇪', 'name' => 'Швеция', 'domain' => 'tg.gptru.pro',
    'ee' => 'tg://proxy?server=tg.gptru.pro&port=443&secret=ee6c0fc115f28307e3510041fffcaef3bc67707472752e70726f',
    'dd' => 'tg://proxy?server=tg.gptru.pro&port=443&secret=dd6c0fc115f28307e3510041fffcaef3bc',
  ],
  'pl' => [
    'flag' => '🇵🇱', 'name' => 'Польша', 'domain' => 'pl.gptru.pro',
    'ee' => 'tg://proxy?server=pl.gptru.pro&port=443&secret=ee20b1dfaf8512ed32a1048a55094b000e766b2e636f6d',
    'dd' => 'tg://proxy?server=pl.gptru.pro&port=443&secret=dd20b1dfaf8512ed32a1048a55094b000e',
  ],
];
$dcs = [
  1 => ['loc' => '🇺🇸 США, Майами',  'ip' => '149.154.175.50'],
  2 => ['loc' => '🇳🇱 Нидерланды',   'ip' => '149.154.167.51'],
  3 => ['loc' => '🇺🇸 США, Майами',  'ip' => '149.154.175.100'],
  4 => ['loc' => '🇳🇱 Нидерланды',   'ip' => '149.154.167.91'],
  5 => ['loc' => '🇸🇬 Сингапур',     'ip' => '149.154.171.5'],
];
?>

<!-- QR попап -->
<div id="qr-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--bg2);border:1px solid var(--border2);border-radius:12px;padding:24px;text-align:center;min-width:220px">
    <div style="font-size:12px;color:var(--muted);margin-bottom:12px" id="qr-modal-label">QR код</div>
    <img id="qr-modal-img" src="" style="width:180px;height:180px;border-radius:8px;display:block;margin:0 auto 12px">
    <div style="font-size:10px;color:var(--muted);margin-bottom:14px">Наведите камеру телефона<br>для подключения к прокси</div>
    <button onclick="closeQR()" class="btn" style="width:100%">Закрыть</button>
  </div>
</div>

<!-- Заголовок -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
  <div>
    <div style="font-size:16px;font-weight:700;color:var(--text)">Статус Telegram DC</div>
    <div style="font-size:11px;color:var(--muted);margin-top:3px">
      Источник: <a href="https://radar.telemt.top" target="_blank" style="color:var(--blue)">radar.telemt.top</a>
      · <span id="radar-upd">загрузка...</span>
    </div>
  </div>
  <button class="btn btn-blue" onclick="loadRadar()" id="btn-refresh">↻ Обновить</button>
</div>

<!-- Сводка регионов -->
<div id="radar-summary" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:14px">
  <?php for($i=0;$i<3;$i++): ?>
  <div class="card" style="text-align:center;min-height:80px"><div style="color:var(--muted);font-size:12px;margin-top:20px">Загрузка...</div></div>
  <?php endfor; ?>
</div>

<!-- Матрица DC -->
<div class="card" style="margin-bottom:14px">
  <div class="card-hdr">
    Матрица доступности — TCP 443 IPv4
    <span style="font-size:11px;color:var(--muted)">
      <span style="color:var(--green)">●</span> доступен &nbsp;
      <span style="color:var(--red)">●</span> заблокирован &nbsp;
      <span style="color:var(--muted)">●</span> нет данных
    </span>
  </div>
  <div id="radar-matrix"><div style="color:var(--muted);font-size:12px;padding:12px 0">Загрузка...</div></div>
  <div style="font-size:10px;color:var(--muted);margin-top:10px;padding-top:10px;border-top:1px solid var(--border);line-height:1.7">
    <strong style="color:var(--text)">Важно:</strong> ICMP (ping) проходит даже из России — серверы Telegram живые. Но ТСПУ блокирует TCP 443 — протокол по которому работает Telegram. Поэтому сервер "виден" но Telegram без прокси не работает.
  </div>
</div>

<!-- Диагностика -->
<div class="card" style="margin-bottom:14px">
  <div class="card-hdr">
    Диагностика с вашего устройства
    <button class="btn btn-blue" onclick="runDiag()" id="btn-diag" style="color:#fff">▶ Запустить тест</button>
  </div>
  <div style="font-size:11px;color:var(--muted);margin-bottom:12px;line-height:1.7">
    Измеряем время TCP соединения с каждым DC Telegram из вашего браузера.
    <span style="color:var(--green)">До 100мс</span> — отлично,
    <span style="color:var(--amber)">100–300мс</span> — нормально,
    <span style="color:var(--red)">более 300мс</span> — высокая задержка.
    Если DC заблокирован — увидите "заблокирован".
  </div>
  <?php foreach ($dcs as $num => $dc): ?>
  <div style="background:var(--bg3);border-radius:8px;padding:11px 14px;margin-bottom:8px">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="font-size:13px;font-weight:700;width:40px;flex-shrink:0;color:var(--blue)">DC<?= $num ?></div>
      <div style="font-size:11px;color:var(--muted);width:120px;flex-shrink:0;font-family:sans-serif"><?= $dc['loc'] ?></div>
      <div style="flex:1;height:7px;background:var(--bg4);border-radius:4px;overflow:hidden">
        <div id="bar<?= $num ?>" style="height:100%;border-radius:4px;width:0%;background:var(--blue);transition:width .4s"></div>
      </div>
      <div id="ping<?= $num ?>" style="font-size:12px;font-weight:700;width:60px;text-align:right;flex-shrink:0;color:var(--muted)">— мс</div>
      <div id="st<?= $num ?>" style="width:90px;text-align:right;flex-shrink:0">
        <span class="badge" style="background:var(--bg4);color:var(--muted)">ожидание</span>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Прокси -->
<div class="card" style="margin-bottom:14px">
  <div class="card-hdr">Подключиться к нашим прокси</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
    <?php foreach ($proxies as $key => $p): ?>
    <div style="background:var(--bg3);border-radius:10px;padding:14px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
        <span style="font-family:sans-serif;font-size:18px"><?= $p['flag'] ?></span>
        <span style="font-size:13px;font-weight:700"><?= $p['name'] ?> · <?= $p['domain'] ?></span>
        <span class="badge badge-ok">онлайн</span>
      </div>

      <!-- EE -->
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <span style="font-size:12px;font-weight:700;color:var(--blue)">EE — рекомендуется</span>
          <span style="font-size:10px;color:var(--muted)">FakeTLS + домен</span>
        </div>
        <div id="<?= $key ?>-ee" style="font-size:9px;color:#94a3b8;word-break:break-all;padding:7px 9px;background:var(--bg2);border-radius:6px;line-height:1.6;margin-bottom:7px;font-family:monospace"><?= $p['ee'] ?></div>
        <div style="display:flex;gap:7px;align-items:center">
          <button class="btn" onclick="doCopy('<?= $key ?>-ee',this)" style="font-size:11px;padding:5px 12px">Копировать</button>
          <button class="btn btn-blue" onclick="location.href='<?= $p['ee'] ?>'" style="font-size:11px;padding:5px 12px">Открыть в TG</button>
          <button class="btn" onclick="showQR('<?= $p['flag'].' '.$p['name'] ?> EE','<?= $p['ee'] ?>')" style="font-size:11px;padding:5px 12px">QR</button>
        </div>
      </div>

      <div style="border-top:1px solid var(--border);padding-top:12px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <span style="font-size:12px;font-weight:700;color:var(--muted)">DD — резервный</span>
          <span style="font-size:10px;color:var(--muted)">FakeTLS</span>
        </div>
        <div id="<?= $key ?>-dd" style="font-size:9px;color:#94a3b8;word-break:break-all;padding:7px 9px;background:var(--bg2);border-radius:6px;line-height:1.6;margin-bottom:7px;font-family:monospace"><?= $p['dd'] ?></div>
        <div style="display:flex;gap:7px;align-items:center">
          <button class="btn" onclick="doCopy('<?= $key ?>-dd',this)" style="font-size:11px;padding:5px 12px">Копировать</button>
          <button class="btn btn-blue" onclick="location.href='<?= $p['dd'] ?>'" style="font-size:11px;padding:5px 12px">Открыть в TG</button>
          <button class="btn" onclick="showQR('<?= $p['flag'].' '.$p['name'] ?> DD','<?= $p['dd'] ?>')" style="font-size:11px;padding:5px 12px">QR</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="font-size:10px;color:var(--muted);margin-top:12px;padding-top:12px;border-top:1px solid var(--border);line-height:1.7">
    <strong style="color:var(--text)">EE (FakeTLS + домен)</strong> — лучше маскируется под HTTPS трафик, сложнее заблокировать. Рекомендуем.
    <strong style="color:var(--muted)">DD (FakeTLS)</strong> — проще, работает везде. Если один не работает — попробуйте другой.
  </div>
</div>

<!-- Легенда -->
<div class="card">
  <div class="card-hdr">Справка — датацентры Telegram</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
    <?php
    $legend = [
      ['dc'=>'DC1','loc'=>'🇺🇸 США, Майами',  'desc'=>'Основной для пользователей Америки. Сообщения, звонки.'],
      ['dc'=>'DC2','loc'=>'🇳🇱 Нидерланды',   'desc'=>'Основной для большинства RU/EU. Сообщения, файлы, медиа.'],
      ['dc'=>'DC3','loc'=>'🇺🇸 США, Майами',  'desc'=>'Резервный для DC1. Медиафайлы и CDN.'],
      ['dc'=>'DC4','loc'=>'🇳🇱 Нидерланды',   'desc'=>'Резервный для DC2. Медиа и файлы EU пользователей.'],
      ['dc'=>'DC5','loc'=>'🇸🇬 Сингапур',     'desc'=>'Основной для пользователей Азии и Океании.'],
      ['dc'=>'DC203','loc'=>'🌐 CDN',          'desc'=>'Специальный CDN узел для ускорения загрузки медиа.'],
    ];
    foreach ($legend as $l): ?>
    <div style="display:flex;gap:10px;padding:8px 10px;border-bottom:1px solid var(--border);align-items:flex-start">
      <div style="font-size:12px;font-weight:700;width:44px;flex-shrink:0;color:var(--blue)"><?= $l['dc'] ?></div>
      <div style="font-size:10px;color:var(--muted);width:110px;flex-shrink:0;font-family:sans-serif"><?= $l['loc'] ?></div>
      <div style="font-size:10px;color:var(--muted);line-height:1.6"><?= $l['desc'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="font-size:10px;color:var(--muted);margin-top:10px;line-height:1.7">
    Telegram автоматически назначает пользователя на DC при регистрации. Изменить нельзя. Если ваш DC заблокирован — нужен прокси в стране где DC доступен.
  </div>
</div>

<script>
// QR попап
function showQR(label, url) {
  document.getElementById('qr-modal-label').textContent = label;
  document.getElementById('qr-modal-img').src = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(url);
  document.getElementById('qr-modal').style.display = 'flex';
}
function closeQR() {
  document.getElementById('qr-modal').style.display = 'none';
}
document.getElementById('qr-modal').addEventListener('click', function(e) {
  if (e.target === this) closeQR();
});

// Копирование
function doCopy(id, btn) {
  const txt = document.getElementById(id).textContent.trim();
  navigator.clipboard.writeText(txt).then(() => {
    const orig = btn.textContent;
    btn.textContent = 'Скопировано!';
    btn.style.color = '#22c55e';
    btn.style.borderColor = '#22c55e';
    setTimeout(() => { btn.textContent = orig; btn.style.color = ''; btn.style.borderColor = ''; }, 1500);
  });
}

// Загрузка данных радара
async function loadRadar() {
  const btn = document.getElementById('btn-refresh');
  const upd = document.getElementById('radar-upd');
  if (btn) { btn.textContent = '↻ Загрузка...'; btn.disabled = true; }

  try {
    const r = await fetch('radar_proxy.php');
    const data = await r.json();

    const regions = {SE:{ok:0,total:0},DE:{ok:0,total:0},RU:{ok:0,total:0}};
    const matrix = {SE:{},DE:{},RU:{}};
    const ruSeen = {};

    data.forEach(ep => {
      const g = ep.group || '';
      const name = ep.name || '';
      const last = ep.results && ep.results.length ? ep.results[ep.results.length-1] : null;
      const ok = last ? last.success : null;

      if (!g.includes('TCP 443 IPv4') || name.includes('IPv6')) return;

      const dcM = name.match(/DC(\d+)/);
      if (!dcM) return;
      const dcNum = 'DC' + dcM[1];

      let region = null;
      if (g.includes('from SE')) region = 'SE';
      else if (g.includes('from DE')) region = 'DE';
      else if (g.includes('from RU')) region = 'RU';
      if (!region) return;

      // Дедупликация RU (ru01 и ru1)
      if (region === 'RU') {
        if (ruSeen[dcNum]) return;
        ruSeen[dcNum] = true;
      }

      matrix[region][dcNum] = ok;
      regions[region].total++;
      if (ok) regions[region].ok++;
    });

    // Сводка
    const info = {
      SE:{flag:'🇸🇪',name:'Швеция · наш прокси',note:'Пользователи через наш прокси подключаются стабильно'},
      DE:{flag:'🇩🇪',name:'Германия',note:'Германия для сравнения'},
      RU:{flag:'🇷🇺',name:'Россия · напрямую',note:'Именно поэтому нужен MTProxy прокси'},
    };
    document.getElementById('radar-summary').innerHTML = Object.entries(info).map(([code,i]) => {
      const ok = regions[code].ok || 0;
      const total = regions[code].total || 5;
      const color = ok===total?'#22c55e':ok===0?'#ef4444':'#f59e0b';
      return `<div class="card" style="text-align:center">
        <div style="font-family:sans-serif;font-size:22px;margin-bottom:4px">${i.flag}</div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:8px">${i.name}</div>
        <div style="font-size:28px;font-weight:700;color:${color};margin-bottom:4px">${ok}/${total}</div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:8px">DC доступны</div>
        <div style="font-size:10px;color:var(--muted);padding-top:8px;border-top:1px solid var(--border);line-height:1.5">${i.note}</div>
      </div>`;
    }).join('');

    // Матрица
    const dcs = ['DC1','DC2','DC3','DC4','DC5'];
    const locs = {DC1:'🇺🇸 США',DC2:'🇳🇱 NL',DC3:'🇺🇸 США',DC4:'🇳🇱 NL',DC5:'🇸🇬 SG'};
    let tbl = `<table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead><tr>
        <th style="text-align:left;padding:7px 6px;border-bottom:1px solid var(--border);font-size:11px;color:var(--muted);font-weight:400">Из страны</th>
        ${dcs.map(dc=>`<th style="text-align:center;padding:7px 6px;border-bottom:1px solid var(--border);font-size:11px;color:var(--muted);font-weight:400">${dc}<br><span style="font-size:9px;color:var(--muted)">${locs[dc]}</span></th>`).join('')}
      </tr></thead><tbody>`;

    [{code:'SE',flag:'🇸🇪',label:'Швеция'},{code:'DE',flag:'🇩🇪',label:'Германия'},{code:'RU',flag:'🇷🇺',label:'Россия'}].forEach(row => {
      tbl += `<tr><td style="padding:8px 6px;border-bottom:1px solid var(--border);font-size:12px"><span style="font-family:sans-serif">${row.flag}</span> ${row.label}</td>`;
      dcs.forEach(dc => {
        const v = matrix[row.code][dc];
        const color = v===true?'#22c55e':v===false?'#ef4444':'#64748b';
        tbl += `<td style="text-align:center;padding:8px 6px;border-bottom:1px solid var(--border)"><span style="color:${color};font-size:18px">●</span></td>`;
      });
      tbl += '</tr>';
    });
    tbl += '</tbody></table>';
    document.getElementById('radar-matrix').innerHTML = tbl;

    const now = new Date();
    if (upd) upd.textContent = 'обновлено ' + now.toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'});

  } catch(e) {
    if (upd) upd.textContent = 'ошибка загрузки';
  }
  if (btn) { btn.textContent = '↻ Обновить'; btn.disabled = false; }
}

// Диагностика через PHP прокси на нашем сервере
async function runDiag() {
  const btn = document.getElementById('btn-diag');
  if (btn) { btn.textContent = '⏳ Тестирование...'; btn.disabled = true; }

  // Показываем проверку на всех DC
  for (let i = 1; i <= 5; i++) {
    document.getElementById('ping'+i).textContent = '...';
    document.getElementById('ping'+i).style.color = '#f59e0b';
    document.getElementById('st'+i).innerHTML = '<span class="badge" style="background:var(--bg3);color:var(--amber)">проверка</span>';
  }

  try {
    const r = await fetch('ping_proxy.php');
    const data = await r.json();
    Object.entries(data.results).forEach(([num, res]) => {
      const p = document.getElementById('ping'+num);
      const b = document.getElementById('bar'+num);
      const s = document.getElementById('st'+num);
      if (res.ok && res.ms !== null) {
        const ms = res.ms;
        const color = ms<100?'#22c55e':ms<300?'#f59e0b':'#ef4444';
        p.textContent = ms+' мс'; p.style.color = color;
        b.style.width = Math.min(ms/3,100)+'%'; b.style.background = color;
        s.innerHTML = '<span class="badge badge-ok">доступен</span>';
      } else {
        p.textContent = 'блок.'; p.style.color = '#ef4444';
        b.style.width = '100%'; b.style.background = '#ef4444';
        s.innerHTML = '<span class="badge badge-err">заблокирован</span>';
      }
    });
  } catch(e) {
    for (let i = 1; i <= 5; i++) {
      document.getElementById('ping'+i).textContent = 'ошибка';
      document.getElementById('st'+i).innerHTML = '<span class="badge badge-err">ошибка</span>';
    }
  }
  if (btn) { btn.textContent = '▶ Запустить тест'; btn.disabled = false; }
}

loadRadar();
</script>
