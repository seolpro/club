<?php
declare(strict_types=1);

require_once __DIR__ . '/bizppurio_sms_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(['ok' => false, 'error' => 'POST only'], 405);
}

$raw = file_get_contents('php://input');
$j = json_decode($raw ?: '', true);
if (!is_array($j)) {
  // form-urlencoded도 허용하고 싶으면 아래처럼 fallback
  $j = $_POST ?: [];
}

$to      = (string)($j['to'] ?? '');
$message = (string)($j['message'] ?? '');
$subject = isset($j['subject']) ? (string)$j['subject'] : null;
$refkey  = isset($j['refkey'])  ? (string)$j['refkey']  : null;

$r = bizppurio_send_sms($to, $message, $subject, $refkey);

if (!$r['ok']) {
  json_response($r, 400);
}
json_response($r, 200);
