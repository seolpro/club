<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (!is_installed()) redirect('install.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
verify_csrf();

$pdo = db();
$closedStmt = $pdo->prepare("SELECT setting_value FROM gift_settings WHERE setting_key='closed'");
$closedStmt->execute();
if ($closedStmt->fetchColumn() === '1') exit('현재 제출이 마감되었습니다.');

$clubId = (int)($_POST['club_id'] ?? 0);
$name = trim((string)($_POST['member_name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$phoneNorm = normalize_phone($phone);
$bank = trim((string)($_POST['bank_name'] ?? ''));
$account = trim((string)($_POST['account_no'] ?? ''));
$comment = trim((string)($_POST['comment'] ?? ''));

if ($clubId < 1 || $name === '' || $phoneNorm === '' || $bank === '' || $account === '') exit('필수 입력값을 확인해 주세요.');
if (!preg_match('/^01\d{8,9}$/', $phoneNorm)) exit('연락처 형식을 확인해 주세요.');
if (mb_strlen($name) > 80 || mb_strlen($bank) > 80 || mb_strlen($account) > 100 || mb_strlen($comment) > 500) exit('입력 가능한 글자 수를 초과했습니다.');

$clubStmt = $pdo->prepare("SELECT name FROM gift_clubs WHERE id=? AND is_active=1");
$clubStmt->execute([$clubId]);
$clubName = $clubStmt->fetchColumn();
if (!$clubName) exit('선택한 동아리가 유효하지 않습니다.');

try {
    $pdo->beginTransaction();
    // 동일 성명 + 동일 연락처를 회원 식별키로 사용: 기존 제출 삭제 후 최종본만 저장
    $del = $pdo->prepare("DELETE FROM gift_submissions WHERE member_name=? AND phone_norm=?");
    $del->execute([$name, $phoneNorm]);
    $replaced = $del->rowCount() > 0;

    $ins = $pdo->prepare("INSERT INTO gift_submissions (club_id, member_name, phone, phone_norm, bank_name, account_no, comment, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $ins->execute([$clubId, $name, $phone, $phoneNorm, $bank, $account, $comment ?: null]);
    $pdo->commit();

    $_SESSION['gift_submit_result'] = ['club'=>$clubName,'name'=>$name,'bank'=>$bank,'account'=>mask_account($account),'replaced'=>$replaced];
    redirect('complete.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    exit('제출 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.');
}
