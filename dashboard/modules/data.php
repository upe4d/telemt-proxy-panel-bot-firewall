<?php
/**
 * Загрузка и подготовка данных для дашборда
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

// Загрузка всех данных
$current        = load_json(FILE_CURRENT);
$history        = load_json(FILE_HISTORY);
$hours          = load_json(FILE_HOURS);
$countries      = load_json(FILE_COUNTRIES);
$cities         = load_json(FILE_CITIES);
$ips            = load_json(FILE_IPS);
$users          = load_json(FILE_USERS);
$nvr            = load_json(FILE_NVR, ['new'=>0,'repeat'=>0]);
$new_by_day     = load_json(FILE_NEW_BY_DAY, []);
$country_traffic = load_json(FILE_COUNTRY_TRAFFIC, []);

// Сортировка
sort_by($countries, 'sessions');
sort_by($cities, 'sessions');
sort_by($country_traffic, 'bytes');

// Данные для графиков по дням
ksort($history);
$days        = array_keys($history);
$day_labels  = array_map(fn($d) => substr($d, 5), $days);
$day_conns   = array_column(array_values($history), 'connections');
$day_traffic = array_map(fn($d) => round(($d['traffic']??0)/1048576, 1), array_values($history));
$day_ips     = array_column(array_values($history), 'peak_ips');

// График новых IP по дням
ksort($new_by_day);
$new_day_labels = array_map(fn($d) => substr($d, 5), array_keys($new_by_day));
$new_day_data   = array_values($new_by_day);

// Активность по часам
$hour_labels = array_map(fn($i) => sprintf('%02d:00', $i), range(0, 23));
$hour_data   = array_map(fn($i) => (int)($hours[(string)$i] ?? 0), range(0, 23));

// Топ списки
$top_countries = array_slice($countries, 0, 10, true);
$top_cities    = array_slice($cities, 0, 20, true);
$top_traffic   = array_slice($country_traffic, 0, 10, true);

// История IP (последние 100)
$recent_ips = [];
foreach ($ips as $ip => $data) {
    $recent_ips[] = array_merge(['ip' => $ip], $data);
}
usort($recent_ips, fn($a, $b) => ($b['last_seen']??0) <=> ($a['last_seen']??0));
$recent_ips = array_slice($recent_ips, 0, 100);

// Карта — группируем активные IP по городу
$map_cities = [];
foreach ($current['active_ips_list'] ?? [] as $ip_data) {
    if (!isset($ip_data['lat'], $ip_data['lon'])) continue;
    $key = ($ip_data['city'] ?? '') ?: ($ip_data['country_name'] ?? '');
    if (!isset($map_cities[$key])) {
        $map_cities[$key] = [
            'lat' => $ip_data['lat'], 'lon' => $ip_data['lon'],
            'city' => $ip_data['city'] ?? '',
            'country' => $ip_data['country_name'] ?? '',
            'count' => 0, 'ips' => []
        ];
    }
    $map_cities[$key]['count']++;
    $map_cities[$key]['ips'][] = $ip_data['ip'];
}

$status_color = ($current['online'] ?? false) ? '#4ade80' : '#f87171';

// Данные блокировок
$blocked      = load_json(DATA_DIR . '/blocked.json', [
    'total4' => 0, 'total6' => 0, 'ips' => [], 'last_updated' => ''
]);
$blocked_ips  = $blocked['ips'] ?? [];

// Сортируем по количеству попыток
uasort($blocked_ips, fn($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

// Статистика блокировок по странам
$blocked_by_country = [];
foreach ($blocked_ips as $ip => $data) {
    $country      = $data['geo']['country'] ?? '??';
    $country_name = $data['geo']['country_name'] ?? 'Unknown';
    if (!isset($blocked_by_country[$country])) {
        $blocked_by_country[$country] = [
            'name'     => $country_name,
            'count'    => 0,
            'attempts' => 0,
            'ips'      => []
        ];
    }
    $blocked_by_country[$country]['count']++;
    $blocked_by_country[$country]['attempts'] += ($data['count'] ?? 0);
    $blocked_by_country[$country]['ips'][]     = $ip;
}
uasort($blocked_by_country, fn($a, $b) => $b['attempts'] <=> $a['attempts']);

// Счётчики iptables
// Счётчики iptables — читаем из файла который обновляется cron каждую минуту
$fw_counters = load_json(DATA_DIR . '/fw_counters.json', [
    'blocked_ipv4' => 0,
    'blocked_ipv6' => 0,
    'accepted_ipv4' => 0,
]);
$r4 = shell_exec("sudo /usr/sbin/iptables -L TELEMT_WL -n -v 2>/dev/null");
$r6 = shell_exec("sudo /usr/sbin/ip6tables -L TELEMT_WL6 -n -v 2>/dev/null");
if ($r4) {
    preg_match_all('/(\d+)\s+\d+\s+DROP/', $r4, $m);
    if (!empty($m[1])) $fw_counters['blocked_ipv4'] = (int)array_sum($m[1]);
}
if ($r6) {
    preg_match_all('/(\d+)\s+\d+\s+DROP/', $r6, $m);
    if (!empty($m[1])) $fw_counters['blocked_ipv6'] = (int)array_sum($m[1]);
}
