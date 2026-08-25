<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!is_installed()) {
    redirect('install.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verify_csrf();

$pdo = db();

$closedStmt = $pdo->prepare("
    SELECT setting_value
    FROM gift_settings
    WHERE setting_key = 'closed'
");
$closedStmt->execute();

if ($closedStmt->fetchColumn() === '1') {
    exit('현재 제출이 마감되었습니다.');
}

$clubId = (int)($_POST['club_id'] ?? 0);
$name = trim((string)($_POST['member_name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$phoneNorm = normalize_phone($phone);
$bank = trim((string)($_POST['bank_name'] ?? ''));
$account = trim((string)($_POST['account_no'] ?? ''));
$comment = trim((string)($_POST['comment'] ?? ''));

if (
    $clubId < 1 ||
    $name === '' ||
    $phoneNorm === '' ||
    $bank === '' ||
    $account === ''
) {
    exit('필수 입력값을 확인해 주세요.');
}

if (!preg_match('/^01\d{8,9}$/', $phoneNorm)) {
    exit('연락처 형식을 확인해 주세요.');
}

if (
    mb_strlen($name) > 80 ||
    mb_strlen($bank) > 80 ||
    mb_strlen($account) > 100 ||
    mb_strlen($comment) > 500
) {
    exit('입력 가능한 글자 수를 초과했습니다.');
}

$clubStmt = $pdo->prepare("
    SELECT name
    FROM gift_clubs
    WHERE id = ?
      AND is_active = 1
");
$clubStmt->execute([$clubId]);

$clubName = (string)$clubStmt->fetchColumn();

if ($clubName === '') {
    exit('선택한 동아리가 유효하지 않습니다.');
}

/**
 * 기존 회원명부의 계좌정보를 갱신합니다.
 *
 * - 문화탐방이 포함된 동아리명 => cul_members
 * - 산악회가 포함된 동아리명   => mt_members
 * - "산악회+문화탐방"처럼 둘 다 포함되면 두 테이블 모두 반영
 *
 * 회원 식별:
 * 성명 + 연락처(하이픈/공백 제거 후 비교)
 *
 * 매칭되는 회원이 없어도 gift_submissions 저장은 정상 진행합니다.
 */
function syncMemberAccount(
    PDO $pdo,
    string $clubName,
    string $name,
    string $phoneNorm,
    string $bank,
    string $account
): array {
    $targets = [];

    if (mb_strpos($clubName, '문화탐방') !== false) {
        $targets['cul'] = 'cul_members';
    }

    if (mb_strpos($clubName, '산악회') !== false) {
        $targets['mt'] = 'mt_members';
    }

    $result = [
        'cul' => null,
        'mt' => null,
    ];

    foreach ($targets as $key => $table) {
        // 테이블명은 사용자 입력값이 아니라 위 화이트리스트에서만 선택됩니다.
        $sql = "
            UPDATE {$table}
               SET bank_name = ?,
                   account_no = ?,
                   account_updated_at = NOW()
             WHERE name = ?
               AND REPLACE(
                       REPLACE(
                           REPLACE(COALESCE(contact, ''), '-', ''),
                       ' ', ''),
                   '.', '') = ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $bank,
            $account,
            $name,
            $phoneNorm,
        ]);

        $result[$key] = $stmt->rowCount() > 0;
    }

    return $result;
}

try {
    $pdo->beginTransaction();

    // 동일 성명 + 동일 연락처를 회원 식별키로 사용:
    // 기존 gift 제출내역 삭제 후 최종 제출 1건만 저장
    $del = $pdo->prepare("
        DELETE FROM gift_submissions
        WHERE member_name = ?
          AND phone_norm = ?
    ");
    $del->execute([$name, $phoneNorm]);

    $replaced = $del->rowCount() > 0;

    $ins = $pdo->prepare("
        INSERT INTO gift_submissions (
            club_id,
            member_name,
            phone,
            phone_norm,
            bank_name,
            account_no,
            comment,
            submitted_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $ins->execute([
        $clubId,
        $name,
        $phone,
        $phoneNorm,
        $bank,
        $account,
        $comment !== '' ? $comment : null,
    ]);

    // 기존 문화탐방/산악회 회원명부에도 계좌정보 동시 반영
    $memberSync = syncMemberAccount(
        $pdo,
        $clubName,
        $name,
        $phoneNorm,
        $bank,
        $account
    );

    $pdo->commit();

    $_SESSION['gift_submit_result'] = [
        'club' => $clubName,
        'name' => $name,
        'bank' => $bank,
        'account' => mask_account($account),
        'replaced' => $replaced,
        'member_sync' => $memberSync,
    ];

    redirect('complete.php');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    exit('제출 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.');
}
