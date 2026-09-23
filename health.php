<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if(!installed()){http_response_code(503);echo json_encode(['ok'=>false,'installed'=>false]);exit;}
try{db()->query('SELECT 1');echo json_encode(['ok'=>true,'installed'=>true,'time'=>date(DATE_ATOM)]);}catch(Throwable){http_response_code(503);echo json_encode(['ok'=>false,'installed'=>true,'database'=>false]);}
