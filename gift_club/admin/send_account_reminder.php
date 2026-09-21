<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
admin_required();
require_once __DIR__ . '/gift_account_sms.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: member_status.php');
    exit;
}

$returnQuery = trim((string)($_POST['return_query'] ?? ''));
$returnUrl = 'member_status.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');

$members = $_POST['members'] ?? [];
if (!is_array($members) || !$members) {
    $_SESSION['gift_sms_flash'] = ['ok'=>false,'message'=>'문자를 발송할 회원을 선택해 주세요.'];
    header('Location: '.$returnUrl);
    exit;
}

$members = array_values(array_unique(array_filter(array_map('strval', $members))));
$success = 0;
$failed = 0;
$skipped = 0;
$errors = [];

function gift_member_row(PDO $pdo, string $sourceKey, int $memberId): ?array
{
    $map = [
        'cul' => ['table'=>'cul_members', 'label'=>'문화탐방', 'keyword'=>'문화탐방'],
        'mt'  => ['table'=>'mt_members',  'label'=>'산악회',   'keyword'=>'산악회'],
    ];
    if (!isset($map[$sourceKey])) return null;

    $cfg = $map[$sourceKey];
    $st = $pdo->prepare("SELECT id, name, contact FROM {$cfg['table']} WHERE id=? LIMIT 1");
    $st->execute([$memberId]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) return null;

    $phoneExpr = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(s.phone,''),'-',''),' ',''),'.',''),'+82','0')";
    $memberPhone = preg_replace('/\D+/', '', (string)$m['contact']) ?? '';

    $chk = $pdo->prepare("
        SELECT s.id
        FROM gift_submissions s
        JOIN gift_clubs c ON c.id=s.club_id
        WHERE CONVERT(c.name USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
          AND CONVERT(s.member_name USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
          AND {$phoneExpr} = ?
        ORDER BY s.submitted_at DESC, s.id DESC
        LIMIT 1
    ");
    $chk->execute(['%'.$cfg['keyword'].'%', (string)$m['name'], $memberPhone]);

    return [
        'id'=>(int)$m['id'], 'name'=>(string)$m['name'], 'contact'=>(string)$m['contact'],
        'source_key'=>$sourceKey, 'source_label'=>$cfg['label'],
        'already_submitted'=>(bool)$chk->fetchColumn(),
    ];
}

// 일괄발송 전체에서 토큰은 1회만 발급하여 재사용합니다.
try {
    $batchToken = gift_sms_token();
} catch (Throwable $e) {
    $_SESSION['gift_sms_flash'] = ['ok'=>false, 'message'=>'뿌리오 토큰 발급 실패: '.$e->getMessage()];
    header('Location: '.$returnUrl);
    exit;
}

$processedSend = 0;

foreach ($members as $key) {
    if (!preg_match('/^(cul|mt):(\d+)$/', $key, $m)) {
        $skipped++;
        continue;
    }

    $sourceKey = $m[1];
    $memberId = (int)$m[2];

    try {
        $member = gift_member_row($pdo, $sourceKey, $memberId);
        if (!$member) {
            $skipped++;
            continue;
        }

        // 저장된 '대상 제외' 회원은 서버에서도 차단
        $st = $pdo->prepare("SELECT is_target FROM gift_account_sms_targets WHERE source_key=? AND member_id=? LIMIT 1");
        $st->execute([$sourceKey, $memberId]);
        $savedTarget = $st->fetchColumn();
        if ($savedTarget !== false && (int)$savedTarget !== 1) {
            $skipped++;
            continue;
        }

        // 발송 직전 재검사: 이미 제출된 회원에게는 보내지 않음
        if ($member['already_submitted']) {
            $skipped++;
            continue;
        }

        // 첫 발송 이후 각 회원 사이에 0.35초 간격을 두어 순간 요청 집중을 완화합니다.
        if ($processedSend > 0) {
            usleep(350000);
        }

        $result = gift_send_account_lms(
            $batchToken,
            $sourceKey,
            $memberId,
            $member['source_label'],
            $member['name'],
            $member['contact']
        );
        $processedSend++;

        $log = $pdo->prepare("
            INSERT INTO gift_account_sms_logs
                (source_key, member_id, member_name, phone, ref_key, request_key, result_code, result_message, content, success, sent_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
        ");
        $log->execute([
            $sourceKey, $memberId, $member['name'], $member['contact'],
            $result['ref_key'], $result['request_key'], $result['code'],
            $result['description'], $result['content'], $result['ok'] ? 1 : 0
        ]);

        if ($result['ok']) {
            $success++;
            $up = $pdo->prepare("
                INSERT INTO gift_account_sms_targets
                    (source_key, member_id, is_target, last_sent_at, send_count, last_result, updated_at)
                VALUES (?, ?, 1, NOW(), 1, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    last_sent_at=NOW(),
                    send_count=send_count+1,
                    last_result=VALUES(last_result),
                    updated_at=NOW()
            ");
            $up->execute([$sourceKey, $memberId, $result['description'] ?: $result['code'] ?: 'SUCCESS']);
        } else {
            $failed++;
            $errors[] = $member['name'].'('.($result['description'] ?: $result['code'] ?: '발송실패').')';
        }
    } catch (Throwable $e) {
        $failed++;
        $errors[] = $key.'('.$e->getMessage().')';
    }
}

$msg = "문자발송 결과: 성공 {$success}명";
if ($failed) $msg .= ", 실패 {$failed}명";
if ($skipped) $msg .= ", 제외/이미제출 {$skipped}명";
if ($errors) $msg .= " / " . implode(', ', array_slice($errors, 0, 5));

$_SESSION['gift_sms_flash'] = ['ok'=>$failed === 0, 'message'=>$msg];
header('Location: '.$returnUrl);
exit;
