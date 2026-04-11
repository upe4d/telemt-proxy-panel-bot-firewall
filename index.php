<?php
/**
 * index.php — Главный файл дашборда Telemt MTProxy Stats
 * @version 2.0.0
 * @updated 2026-04-10
 *
 * CHANGELOG:
 * v2.0.0 - Полная переработка: вкладочная навигация, тёмно-синяя гамма, отдельные файлы вкладок
 * v1.0.0 - Базовая версия
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/modules/whitelist.php';

// Читаем текущие данные для шапки
$current = @json_decode(file_get_contents(FILE_CURRENT), true) ?? [];
$online  = $current['online'] ?? false;
$version = $current['version'] ?? '—';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Telemt Stats — <?= SITE_DOMAIN ?></title>
<link rel="stylesheet" href="assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<!-- ШАПКА -->
<div class="topbar">
  <div class="topbar-logo">
    <div class="topbar-dot"></div>
    Telemt MTProxy Stats
  </div>
  <div class="topbar-meta">
    <span><?= SITE_DOMAIN ?></span>
    <span>v<?= htmlspecialchars($version) ?></span>
    <span><?= date('Y-m-d H:i') ?> UTC</span>
    <button class="btn-refresh" id="btn-refresh" title="Обновить данные">↻</button>
    <?php if ($online): ?>
      <span class="badge-live">● онлайн</span>
    <?php else: ?>
      <span class="badge-live" style="background:#1c0000;color:#ef4444;border-color:#7f1d1d;">● оффлайн</span>
    <?php endif; ?>
    <a href="https://github.com/upe4d/telemt-proxy-panel-bot-firewall" target="_blank" title="GitHub">
      <i class="bi bi-github" style="font-size:14px;color:var(--muted)"></i>
    </a>
    <a href="https://t.me/<?= ltrim(SITE_CHANNEL, '@') ?>" target="_blank" title="Telegram канал">
      <i class="bi bi-telegram" style="font-size:14px;color:var(--muted)"></i>
    </a>
  </div>
</div>

<!-- НАВИГАЦИЯ ВКЛАДОК -->
<div class="tabs-nav">
  <button class="tab-btn active" data-tab="overview">
    <span class="tab-icon"><i class="bi bi-speedometer2"></i></span> Обзор
  </button>
  <button class="tab-btn" data-tab="map">
    <span class="tab-icon"><i class="bi bi-map"></i></span> Карта
  </button>
  <button class="tab-btn" data-tab="protection">
    <span class="tab-icon"><i class="bi bi-shield-check"></i></span> Защита
  </button>
  <button class="tab-btn" data-tab="countries">
    <span class="tab-icon"><i class="bi bi-globe"></i></span> Страны
  </button>
  <button class="tab-btn" data-tab="users">
    <span class="tab-icon"><i class="bi bi-people"></i></span> Пользователи
  </button>
  <button class="tab-btn" data-tab="history">
    <span class="tab-icon"><i class="bi bi-graph-up"></i></span> История
  </button>
  <button class="tab-btn" data-tab="whitelist">
    <span class="tab-icon"><i class="bi bi-funnel"></i></span> Фильтрация
  </button>
  <button class="tab-btn" data-tab="radar">
    <span class="tab-icon"><i class="bi bi-reception-4"></i></span> Радар DC
  </button>
</div>

<!-- КОНТЕНТ ВКЛАДОК -->
<div class="tab-content">
  <div id="tab-content">
    <div class="loading">Загрузка</div>
  </div>
</div>

<!-- ТОСТ -->
<div id="toast"></div>

<!-- ФУТЕР -->
<footer>
  <?= SITE_DOMAIN ?> · <?= SITE_CHANNEL ?> · v<?= htmlspecialchars($version) ?> · Данные обновляются каждые 10 минут
</footer>

<!-- СКРИПТЫ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
