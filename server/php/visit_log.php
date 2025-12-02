<?php
// /mt/visit_log.php
require_once __DIR__ . '/config.php';

// CORS 열기(필요시)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'POST only'], JSON_UNESCAPED_UNICODE);
    exit;
}

// JSON 파싱
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = [];
}

$page = trim($data['page'] ?? ''); // 예: 'admin_joinmt.html'

// 서버 정보
$ip        = $_SERVER['REMOTE_ADDR']        ?? '';
$uri       = $_SERVER['REQUEST_URI']        ?? '';
$referer   = $_SERVER['HTTP_REFERER']       ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT']    ?? '';

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare("
        INSERT INTO mt_visit_log (visited_at, ip, uri, referer, user_agent)
        VALUES (NOW(), :ip, :uri, :referer, :ua)
    ");

    // uri에는 실제 페이지 이름을 구분해서 넣어두면 나중에 필터링 편해요
    $uriToSave = $page !== '' ? $page : $uri;

    $stmt->execute([
        ':ip'      => $ip,
        ':uri'     => $uriToSave,
        ':referer' => $referer,
        ':ua'      => $userAgent,
    ]);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'DB error'], JSON_UNESCAPED_UNICODE);
}
