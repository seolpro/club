<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';
$result = $_SESSION['gift_submit_result'] ?? null;
unset($_SESSION['gift_submit_result']);
if (!$result) redirect('index.php');
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>제출 완료</title><link rel="stylesheet" href="assets/style.css"></head><body><main class="wrap narrow"><section class="card complete"><div class="success-mark">✓</div><h1>제출이 완료되었습니다</h1><p><?= $result['replaced'] ? '기존 제출내역을 삭제하고 <b>최종 제출내용으로 변경</b>했습니다.' : '입력하신 계좌정보가 정상적으로 등록되었습니다.' ?></p><div class="summary"><div><span>동아리</span><b><?=e($result['club'])?></b></div><div><span>성명</span><b><?=e($result['name'])?></b></div><div><span>은행</span><b><?=e($result['bank'])?></b></div><div><span>계좌번호</span><b><?=e($result['account'])?></b></div></div><a class="btn full" href="index.php">확인</a><p class="small-note">정보를 수정해야 하는 경우 같은 성명과 연락처로 다시 제출하면 최종 제출내용으로 교체됩니다.</p></section></main></body></html>
