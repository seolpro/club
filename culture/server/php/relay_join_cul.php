<?php
// CORS 허용
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// ✅ 뿌리오 인증 토큰 발급 함수
function getPpurioToken() {
    $account = "ajou9770";
    $authKey = "7bfe9eefc98c868431e0c3ca58c534ea37bbea9174a311c90be09636502ee296";
    $authString = base64_encode("$account:$authKey");

    $ch = curl_init("https://message.ppurio.com/v1/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic $authString",
            "Content-Type: application/json; charset=utf-8"
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['token'] ?? null;
}

// ✅ 메시지 타입 판단 함수
function getMessageType($msg) {
    $len = strlen(mb_convert_encoding($msg, 'EUC-KR', 'UTF-8'));
    return $len <= 90 ? 'SMS' : 'LMS';
}

// ✅ 입력값 파싱
$data = json_decode(file_get_contents("php://input"), true);
$sender = $data['sender'] ?? '신청자';
$userPhone = preg_replace("/[^0-9]/", "", $data['userPhone'] ?? '');
$userMessage = trim($data['userMessage'] ?? '');
$adminMessage = trim($data['adminMessage'] ?? '');

// ✅ 관리자 연락처 리스트
$adminPhones = [
    "01071186639"// 운영진1
   //  "01023781287", // 운영진2
  //  "01094031761"  // 운영진3
];

// ✅ 필수 값 체크
if (!$userPhone || !$userMessage || !$adminMessage) {
    echo json_encode(["code" => "4000", "description" => "필수 값 누락"]);
    exit;
}

// ✅ 토큰 발급
$token = getPpurioToken();
if (!$token) {
    echo json_encode(["code" => "3001", "description" => "토큰 발급 실패"]);
    exit;
}

// ✅ 공통 헤더
$headers = [
    "Authorization: Bearer $token",
    "Content-Type: application/json; charset=utf-8"
];

// ✅ 1. 신청자에게 메시지 전송
$payloadUser = [
    "account" => "ajou9770",
    "messageType" => getMessageType($userMessage),
    "content" => $userMessage,
    "from" => "01071186639",
    "duplicateFlag" => "Y",
    "targetCount" => 1,
    "refKey" => "user_" . time(),
    "targets" => [
        [
            "to" => $userPhone,
            "name" => $sender,
            "changeWord" => ["var1" => $sender]
        ]
    ]
];

$chUser = curl_init("https://message.ppurio.com/v1/message");
curl_setopt_array($chUser, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payloadUser),
    CURLOPT_HTTPHEADER => $headers
]);
$responseUser = curl_exec($chUser);
curl_close($chUser);

// ✅ 2. 관리자들에게 메시지 전송
$targetsAdmin = [];
foreach ($adminPhones as $adminPhone) {
    $targetsAdmin[] = [
        "to" => preg_replace("/[^0-9]/", "", $adminPhone),
        "name" => "관리자",
        "changeWord" => ["var1" => $sender]
    ];
}

$payloadAdmin = [
    "account" => "ajou9770",
    "messageType" => getMessageType($adminMessage),
    "content" => $adminMessage,
    "from" => "01071186639",
    "duplicateFlag" => "Y",
    "targetCount" => count($targetsAdmin),
    "refKey" => "admin_" . time(),
    "targets" => $targetsAdmin
];

$chAdmin = curl_init("https://message.ppurio.com/v1/message");
curl_setopt_array($chAdmin, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payloadAdmin),
    CURLOPT_HTTPHEADER => $headers
]);
$responseAdmin = curl_exec($chAdmin);
curl_close($chAdmin);

// ✅ 응답 결과 반환 (선택적으로 통합할 수 있음)
header('Content-Type: application/json');
echo json_encode([
    "userResponse" => json_decode($responseUser, true),
    "adminResponse" => json_decode($responseAdmin, true)
]);
