<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'msg'=>'POST만 허용됩니다.'], 405);
$raw = file_get_contents('php://input');
$in = json_decode($raw ?: '', true);
if (!is_array($in)) json_response(['ok'=>false,'msg'=>'JSON 데이터가 올바르지 않습니다.'], 400);

$leaderName = trim((string)($in['leaderName'] ?? ''));
$leaderContact = preg_replace('/\D+/', '', (string)($in['leaderContact'] ?? ''));
$comment = trim((string)($in['comment'] ?? ''));
$passengers = $in['passengers'] ?? [];
$consent = !empty($in['consent']);

if ($leaderName === '' || $leaderContact === '') json_response(['ok'=>false,'msg'=>'대표자 정보를 입력해 주세요.'], 422);
if (!preg_match('/^\d{10,11}$/', $leaderContact)) json_response(['ok'=>false,'msg'=>'연락처 형식이 올바르지 않습니다.'], 422);
if (!$consent) json_response(['ok'=>false,'msg'=>'개인정보 동의가 필요합니다.'], 422);
if (!is_array($passengers) || count($passengers) < 1) json_response(['ok'=>false,'msg'=>'승선자 1명 이상 필요합니다.'], 422);

$clean = [];
foreach ($passengers as $p) {
    $name = trim((string)($p['name'] ?? ''));
    $birth = preg_replace('/\D+/', '', (string)($p['birth'] ?? ''));
    $gender = (string)($p['gender'] ?? '');
    if ($name === '' || !preg_match('/^\d{6}$/', $birth) || !in_array($gender, ['남','여'], true)) {
        json_response(['ok'=>false,'msg'=>'승선자 정보가 올바르지 않습니다.'], 422);
    }
    $clean[] = ['name'=>$name, 'birth'=>$birth, 'gender'=>$gender];
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('INSERT INTO '.tb('applications').' (leader_name, leader_contact, passenger_count, comment, consent_yn, ip_addr, user_agent) VALUES (?, ?, ?, ?, 1, ?, ?)');
    $stmt->execute([
        $leaderName,
        $leaderContact,
        count($clean),
        $comment,
        $_SERVER['REMOTE_ADDR'] ?? '',
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
    $appId = (int)$pdo->lastInsertId();

    $pstmt = $pdo->prepare('INSERT INTO '.tb('passengers').' (application_id, passenger_name, birth6, gender, sort_order) VALUES (?, ?, ?, ?, ?)');
    $i = 1;
    foreach ($clean as $p) {
        $pstmt->execute([$appId, $p['name'], $p['birth'], $p['gender'], $i++]);
    }

    $pdo->commit();
    json_response(['ok'=>true, 'id'=>$appId]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    json_response(['ok'=>false,'msg'=>'서버 저장 중 오류가 발생했습니다.'], 500);
}
