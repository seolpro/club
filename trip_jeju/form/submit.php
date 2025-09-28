<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

// 공통 리다이렉트 헬퍼
function redirectWith(array $params) {
  $qs = http_build_query($params);
  header('Location: index.html' . ($qs ? ('?' . $qs) : ''));
  exit;
}

// 실패 시: 에러 메시지를 쿼리로 전달해 index.html에서 토스트 출력
function fail($msg) {
  redirectWith(['err' => $msg]);
}

// 입력값 받기
$name    = trim($_POST['name']    ?? '');
$tel     = trim($_POST['tel']     ?? '');
$zipcode = trim($_POST['zipcode'] ?? '');
$addr1   = trim($_POST['addr1']   ?? '');
$addr2   = trim($_POST['addr2']   ?? '');
$memo    = trim($_POST['memo']    ?? '');
$agree   = (isset($_POST['agree']) && $_POST['agree'] === 'Y') ? 'Y' : 'N';

// 검증 (이름 필수, 010-1234-5678 형식)
if ($name === '' || !preg_match('/^010-\d{4}-\d{4}$/', $tel)) {
  fail('필수 항목 누락 또는 연락처 형식 오류(예: 010-1234-5678)');
}

try {
  // DB 저장 (파일 컬럼 제외)
  $sql = "INSERT INTO visit_list
          (name, tel, zipcode, addr1, addr2, memo, agree)
          VALUES (?,?,?,?,?,?,?)";
  $stmt = db()->prepare($sql);
  $stmt->bind_param('sssssss', $name, $tel, $zipcode, $addr1, $addr2, $memo, $agree);
  $stmt->execute();

  // 접수번호
  $rid = db()->insert_id ?? null;

  // 성공 리다이렉트 (ok=1, rid, name)
  redirectWith(['ok' => 1, 'rid' => $rid, 'name' => $name]);

} catch (Throwable $e) {
  // 예외 발생 시 에러 메시지 전달
  fail('서버 오류로 제출에 실패했습니다. 잠시 후 다시 시도해 주세요.');
}
