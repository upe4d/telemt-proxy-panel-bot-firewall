<?php
/**
 * ping_proxy.php — измеряем TCP время до DC Telegram с нашего сервера
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$dcs = [
  1 => '149.154.175.50',
  2 => '149.154.167.51',
  3 => '149.154.175.100',
  4 => '149.154.167.91',
  5 => '149.154.171.5',
];

$results = [];
foreach ($dcs as $num => $ip) {
  $t = microtime(true);
  $conn = @fsockopen($ip, 443, $errno, $errstr, 2);
  $ms = round((microtime(true) - $t) * 1000);
  if ($conn) {
    fclose($conn);
    $results[$num] = ['ok' => true, 'ms' => $ms];
  } else {
    $results[$num] = ['ok' => false, 'ms' => null];
  }
}
echo json_encode(['ok' => true, 'results' => $results, 'from' => 'pl.gptru.pro']);
