<?php
// ✅ CORS 허용
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

// ✅ Preflight 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// ✅ 보안 토큰 (프론트엔드와 동일)
$ADMIN_TOKEN = "ajou2130==";

// ✅ 요청 JSON 파싱
$input = file_get_contents("php://input");
$data  = json_decode($input, true);

if (!$data) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"잘못된 JSON 본문입니다."]);
  exit;
}
if (!isset($data["token"]) || $data["token"] !== $ADMIN_TOKEN) {
  http_response_code(401);
  echo json_encode(["ok"=>false, "error"=>"Unauthorized"]);
  exit;
}

// ✅ 실제 GAS WebApp URL
$target = "https://script.google.com/macros/s/AKfycbxMZ1qfhnVak2WMqcKFg8wm430bd9oP0KQ6bGwNgWACmPlKWySDKgguRQlN5L5On_Wx/exec";

// ✅ cURL로 GAS에 전달
$ch = curl_init($target);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // ✅ redirect 자동 따라가기

// ⚠️ SSL 인증서 문제시(구형 환경) 아래 두 줄을 임시로 해제해보세요. (권장X)
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$response = curl_exec($ch);

if ($response === false) {
  $err = curl_error($ch);
  curl_close($ch);
  http_response_code(502);
  echo json_encode(["ok"=>false, "error"=>"Upstream fetch failed: ".$err]);
  exit;
}

$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ✅ GAS가 텍스트를 주더라도 JSON으로 감싸 반환 (프런트 일관성)
if ($httpcode >= 200 && $httpcode < 300) {
  // 응답이 JSON인지 판단
  $asJson = json_decode($response, true);
  if (json_last_error() === JSON_ERROR_NONE && is_array($asJson)) {
    http_response_code($httpcode);
    echo $response;
  } else {
    http_response_code(200);
    echo json_encode(["ok"=>true, "message"=>$response]);
  }
} else {
  http_response_code($httpcode);
  echo json_encode(["ok"=>false, "error"=>"GAS error ($httpcode): ".$response]);
}
