<?php
/**
 * Конфигурация дашборда Telemt Stats
 */

// Путь к данным
define('DATA_DIR', __DIR__ . '/data');

// Файлы данных
define('FILE_CURRENT',         DATA_DIR . '/current.json');
define('FILE_HISTORY',         DATA_DIR . '/history.json');
define('FILE_HOURS',           DATA_DIR . '/hours.json');
define('FILE_COUNTRIES',       DATA_DIR . '/countries.json');
define('FILE_CITIES',          DATA_DIR . '/cities.json');
define('FILE_IPS',             DATA_DIR . '/ips.json');
define('FILE_USERS',           DATA_DIR . '/users.json');
define('FILE_NVR',             DATA_DIR . '/new_vs_repeat.json');
define('FILE_NEW_BY_DAY',      DATA_DIR . '/new_by_day.json');
define('FILE_COUNTRY_TRAFFIC', DATA_DIR . '/country_traffic.json');
define('FILE_WHITELIST',       DATA_DIR . '/whitelist.json');
define('FILE_FW',             DATA_DIR . '/fw_stats.json');

// Настройки сайта
define('SITE_DOMAIN',  'pl.gptru.pro');
define('SITE_CHANNEL', '@u_pre');
define('PANEL_URL',    'http://95.85.255.210:8443');
