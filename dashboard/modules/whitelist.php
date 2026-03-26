<?php
/**
 * Модуль управления белым списком
 * @version 1.1.0
 * @updated 2026-03-24
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

define('WL_PASSWORD',    'YOUR_WHITELIST_PASSWORD');
define('WL_SESSION_KEY', 'wl_auth');
define('WL_SESSION_TTL', 3600);

session_start();

function wl_is_auth() {
    if (!isset($_SESSION[WL_SESSION_KEY])) return false;
    if (time() - $_SESSION[WL_SESSION_KEY] > WL_SESSION_TTL) {
        unset($_SESSION[WL_SESSION_KEY]);
        return false;
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'wl_login') {
        if (($_POST['password'] ?? '') === WL_PASSWORD) {
            $_SESSION[WL_SESSION_KEY] = time();
        } else {
            $_SESSION['wl_error'] = 'Неверный пароль';
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?wl=1#wl-b-anchor');
        exit;
    }
    if ($action === 'wl_logout') {
        unset($_SESSION[WL_SESSION_KEY]);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#wl-b-anchor');
        exit;
    }
    if (!wl_is_auth()) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?wl=1#wl-b-anchor');
        exit;
    }
    $wl = load_json(FILE_WHITELIST, [
        'enabled'           => false,
        'allowed_countries' => ['RU', 'CN'],
        'allowed_ips'       => [],
        'notes'             => ['RU' => 'Россия', 'CN' => 'Китай']
    ]);
    if ($action === 'toggle') {
        $wl['enabled'] = !($wl['enabled'] ?? false);
    } elseif ($action === 'add_country') {
        $code = strtoupper(trim($_POST['country'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        if ($code && strlen($code) === 2 && !in_array($code, $wl['allowed_countries'])) {
            $wl['allowed_countries'][] = $code;
            if (!$name) { $list = get_countries_list(); $name = $list[$code] ?? $code; }
            $wl['notes'][$code] = $name;
        }
    } elseif ($action === 'remove_country') {
        $code = strtoupper(trim($_POST['country'] ?? ''));
        $wl['allowed_countries'] = array_values(array_filter($wl['allowed_countries'], fn($c) => $c !== $code));
        unset($wl['notes'][$code]);
    } elseif ($action === 'add_ip') {
        $ip   = trim($_POST['ip'] ?? '');
        $note = trim($_POST['note'] ?? '');
        if ($ip && !isset($wl['allowed_ips'][$ip])) {
            $wl['allowed_ips'][$ip] = $note ?: $ip;
        }
    } elseif ($action === 'remove_ip') {
        unset($wl['allowed_ips'][trim($_POST['ip'] ?? '')]);
    }
    save_json(FILE_WHITELIST, $wl);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?wl=1#wl-b-anchor');
    exit;
}

// === Загрузка данных ===
$wl = load_json(FILE_WHITELIST, [
    'enabled'           => false,
    'allowed_countries' => ['RU', 'CN'],
    'allowed_ips'       => [],
    'notes'             => ['RU' => 'Россия', 'CN' => 'Китай']
]);

$wl_enabled   = $wl['enabled'] ?? false;
$wl_countries = $wl['allowed_countries'] ?? ['RU', 'CN'];
$wl_ips       = $wl['allowed_ips'] ?? [];
$wl_notes     = $wl['notes'] ?? [];
$wl_authed    = wl_is_auth();
$wl_open      = isset($_GET['wl']);
$wl_error     = $_SESSION['wl_error'] ?? null;
unset($_SESSION['wl_error']);

// Загружаем current.json для статистики
$current_data   = load_json(FILE_CURRENT, []);
$allowed_active = 0;
$blocked_active = 0;
$outside_ips    = [];

foreach ($current_data['active_ips_list'] ?? [] as $ip_data) {
    $country = $ip_data['country'] ?? '';
    $ip      = $ip_data['ip'] ?? '';
    if (in_array($country, $wl_countries) || isset($wl_ips[$ip])) {
        $allowed_active++;
    } else {
        $blocked_active++;
        $outside_ips[] = $ip_data;
    }
}
