<?php
/**
 * Вспомогательные функции форматирования
 */

function load_json($file, $default = []) {
    if (!file_exists($file)) return $default;
    $data = json_decode(file_get_contents($file), true);
    return $data ?: $default;
}

function save_json($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function fmt_bytes($bytes) {
    $bytes = (int)$bytes;
    if ($bytes > 1073741824) return round($bytes/1073741824, 1) . ' GB';
    if ($bytes > 1048576)    return round($bytes/1048576, 1) . ' MB';
    if ($bytes > 1024)       return round($bytes/1024, 1) . ' KB';
    return $bytes . ' B';
}

function fmt_uptime($secs) {
    $secs = (int)$secs;
    $d = floor($secs/86400);
    $h = floor(($secs%86400)/3600);
    $m = floor(($secs%3600)/60);
    if ($d > 0) return "{$d}д {$h}ч {$m}м";
    if ($h > 0) return "{$h}ч {$m}м";
    return "{$m}м";
}

function country_flag($code) {
    $code = strtoupper(trim($code));
    if (strlen($code) != 2) return '🌐';
    $flag = '';
    foreach (str_split($code) as $c) {
        $flag .= mb_chr(ord($c) - ord('A') + 0x1F1E6);
    }
    return $flag;
}

function sort_by(&$arr, $key) {
    uasort($arr, function($a, $b) use ($key) {
        return ($b[$key] ?? 0) <=> ($a[$key] ?? 0);
    });
}

// Список всех стран мира для выпадающего списка
function get_countries_list() {
    return [
        'AF'=>'Афганистан','AL'=>'Албания','DZ'=>'Алжир','AM'=>'Армения',
        'AU'=>'Австралия','AT'=>'Австрия','AZ'=>'Азербайджан','BH'=>'Бахрейн',
        'BY'=>'Беларусь','BE'=>'Бельгия','BR'=>'Бразилия','BG'=>'Болгария',
        'CA'=>'Канада','CN'=>'Китай','HR'=>'Хорватия','CY'=>'Кипр',
        'CZ'=>'Чехия','DK'=>'Дания','EG'=>'Египет','EE'=>'Эстония',
        'FI'=>'Финляндия','FR'=>'Франция','GE'=>'Грузия','DE'=>'Германия',
        'GR'=>'Греция','HU'=>'Венгрия','IN'=>'Индия','ID'=>'Индонезия',
        'IR'=>'Иран','IQ'=>'Ирак','IE'=>'Ирландия','IL'=>'Израиль',
        'IT'=>'Италия','JP'=>'Япония','JO'=>'Иордания','KZ'=>'Казахстан',
        'KW'=>'Кувейт','KG'=>'Киргизия','LV'=>'Латвия','LB'=>'Ливан',
        'LT'=>'Литва','LU'=>'Люксембург','MK'=>'Македония','MY'=>'Малайзия',
        'MT'=>'Мальта','MX'=>'Мексика','MD'=>'Молдова','MN'=>'Монголия',
        'ME'=>'Черногория','MA'=>'Марокко','NL'=>'Нидерланды','NZ'=>'Новая Зеландия',
        'NO'=>'Норвегия','OM'=>'Оман','PK'=>'Пакистан','PL'=>'Польша',
        'PT'=>'Португалия','QA'=>'Катар','RO'=>'Румыния','RU'=>'Россия',
        'SA'=>'Саудовская Аравия','RS'=>'Сербия','SG'=>'Сингапур','SK'=>'Словакия',
        'SI'=>'Словения','ZA'=>'ЮАР','KR'=>'Южная Корея','ES'=>'Испания',
        'SE'=>'Швеция','CH'=>'Швейцария','SY'=>'Сирия','TW'=>'Тайвань',
        'TJ'=>'Таджикистан','TH'=>'Таиланд','TN'=>'Тунис','TR'=>'Турция',
        'TM'=>'Туркменистан','UA'=>'Украина','AE'=>'ОАЭ','GB'=>'Великобритания',
        'US'=>'США','UZ'=>'Узбекистан','VN'=>'Вьетнам','YE'=>'Йемен',
    ];
}
