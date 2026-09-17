<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
admin_required();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('잘못된 요청입니다.');

    $sourceKey = (string)($_POST['source_key'] ?? '');
    $memberId = (int)($_POST['member_id'] ?? 0);
    $isTarget = (int)($_POST['is_target'] ?? 0);

    if (!in_array($sourceKey, ['cul','mt'], true) || $memberId < 1) {
        throw new RuntimeException('회원정보가 올바르지 않습니다.');
    }

    $isTarget = $isTarget === 1 ? 1 : 0;
    $pdo = db();

    $st = $pdo->prepare("
        INSERT INTO gift_account_sms_targets (source_key, member_id, is_target, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE is_target=VALUES(is_target), updated_at=NOW()
    ");
    $st->execute([$sourceKey, $memberId, $isTarget]);

    echo json_encode(['ok'=>true,'is_target'=>$isTarget], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
