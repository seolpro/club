<?php
// =====================================
// 🔧 Ppurio 기본 설정 (여기만 수정하면 됨)
// =====================================
define('PPURIO_ACCOUNT',   'aj9770');        // 예: 'ajou9770'
define('PPURIO_AUTH_KEY',  '08868d27d42a13b10954f7c9705063152e03d948b824bf336ff611be225957b9');        // Ppurio에서 받은 authKey
define('PPURIO_FROM',      '01071186639');          // Ppurio에 등록된 발신번호

// API 엔드포인트
define('PPURIO_TOKEN_URL',   'https://message.ppurio.com/v1/token');
define('PPURIO_MESSAGE_URL', 'https://message.ppurio.com/v1/message');

// =====================================
// 🌐 CORS 허용
// =====================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight 요청은 여기서 종료
    http_response_code(204);
    exit;
}

// =====================================
// 🔐 뿌리오 인증 토큰 발급 함수
// =====================================
function getPpurioToken() {
    $account   = PPURIO_ACCOUNT;
    $authKey   = PPURIO_AUTH_KEY;
    $authBasic = base64_encode("$account:$authKey");

    $ch = curl_init(PPURIO_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Basic $authBasic",
            "Content-Type: application/json; charset=utf-8"
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return null;
    }

    $result = json_decode($response, true);
    return $result['token'] ?? null;
}

// =====================================
// ✉️ 메시지 타입 판단 함수 (SMS/LMS)
// =====================================
function getMessageType($msg) {
    // 한글 길이 계산을 위해 EUC-KR로 변환 후 바이트 길이 측정
    $len = strlen(mb_convert_encoding($msg, 'EUC-KR', 'UTF-8'));
    return $len <= 90 ? 'SMS' : 'LMS';
}

// =====================================
// 📥 입력값 파싱
// =====================================
$raw  = file_get_contents("php://input");
$data = json_decode($raw, true);

$sender       = $data['sender'] ?? '신청자';
$userPhone    = preg_replace("/[^0-9]/", "", $data['userPhone'] ?? '');
$userMessage  = trim($data['userMessage']  ?? '');
$adminMessage = trim($data['adminMessage'] ?? '');

// ✅ 관리자 연락처 리스트
$adminPhones = [
    "01071186639", // 운영진1
    // "01023781287", // 운영진2
    // "01094031761"  // 운영진3
];

// =====================================
// 🛡 필수 값 체크
// =====================================
if (!$userPhone || !$userMessage || !$adminMessage) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "code"        => "4000",
        "description" => "필수 값 누락 (userPhone, userMessage, adminMessage 확인)"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================
// 🔑 토큰 발급
// =====================================
$token = getPpurioToken();
if (!$token) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "code"        => "3001",
        "description" => "토큰 발급 실패"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 공통 헤더
$headers = [
    "Authorization: Bearer $token",
    "Content-Type: application/json; charset=utf-8"
];

// =====================================
// 1️⃣ 신청자에게 메시지 전송
// =====================================
$payloadUser = [
    "account"      => PPURIO_ACCOUNT,                      // ✅ 상수 사용
    "messageType"  => getMessageType($userMessage),
    "content"      => $userMessage,
    "from"         => PPURIO_FROM,                         // ✅ 상수 사용
    "duplicateFlag"=> "Y",
    "targetCount"  => 1,
    "refKey"       => "user_" . time(),
    "targets"      => [
        [
            "to"         => $userPhone,
            "name"       => $sender,
            "changeWord" => ["var1" => $sender]     // 템플릿 변수 쓰는 경우
        ]
    ]
];

$chUser = curl_init(PPURIO_MESSAGE_URL);
curl_setopt_array($chUser, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payloadUser, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => $headers
]);
$responseUser = curl_exec($chUser);
curl_close($chUser);

// =====================================
// 2️⃣ 관리자들에게 메시지 전송
// =====================================
$targetsAdmin = [];
foreach ($adminPhones as $adminPhone) {
    $targetsAdmin[] = [
        "to"         => preg_replace("/[^0-9]/", "", $adminPhone),
        "name"       => "관리자",
        "changeWord" => ["var1" => $sender]
    ];
}

$payloadAdmin = [
    "account"      => PPURIO_ACCOUNT,                      // ✅ 상수 사용
    "messageType"  => getMessageType($adminMessage),
    "content"      => $adminMessage,
    "from"         => PPURIO_FROM,                         // ✅ 상수 사용
    "duplicateFlag"=> "Y",
    "targetCount"  => count($targetsAdmin),
    "refKey"       => "admin_" . time(),
    "targets"      => $targetsAdmin
];

$chAdmin = curl_init(PPURIO_MESSAGE_URL);
curl_setopt_array($chAdmin, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payloadAdmin, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => $headers
]);
$responseAdmin = curl_exec($chAdmin);
curl_close($chAdmin);

// =====================================
// 📤 최종 응답 반환
// =====================================
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    "userResponse"   => json_decode($responseUser,  true),
    "adminResponse"  => json_decode($responseAdmin, true)
], JSON_UNESCAPED_UNICODE);
