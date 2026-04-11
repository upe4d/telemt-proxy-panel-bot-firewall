<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$data = @file_get_contents('https://radar.telemt.top/api/v1/endpoints/statuses');
if ($data === false) {
    http_response_code(500);
    echo json_encode(['error' => 'failed']);
} else {
    echo $data;
}
