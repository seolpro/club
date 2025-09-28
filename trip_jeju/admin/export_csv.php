<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

$ADMIN_TOKEN = 'myStrongSecretToken123'; // index.php와 동일하게

if (!isset($_GET['token']) || $_GET['token'] !== $ADMIN_TOKEN) {
  http_response_code(401);
  echo "Unauthorized";
  exit;
}

$q = trim($_GET['q'] ?? '');
$where = '1=1';
$params = [];
$types  = '';

if ($q !== '') {
  $where .= " AND (name LIKE CONCAT('%',?,'%') OR tel LIKE CONCAT('%',?,'%') 
             OR addr1 LIKE CONCAT('%',?,'%') OR addr2 LIKE CONCAT('%',?,'%'))";
  $params = [$q,$q,$q,$q];
  $types  = 'ssss';
}

$sql = "SELECT id, created_at, name, tel, zipcode, addr1, addr2, memo, agree
        FROM visit_list
        WHERE {$where}
        ORDER BY id DESC";

$stmt = db()->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="visit_list.csv"');

$out = fopen('php://output', 'w');
// UTF-8 BOM → Excel 한글 깨짐 방지
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// 헤더행
fputcsv($out, ['ID','접수일시','성명','연락처','우편번호','주소','상세주소','비고','동의']);

while ($row = $res->fetch_assoc()) {
  fputcsv($out, [
    $row['id'],
    $row['created_at'],
    $row['name'],
    $row['tel'],
    $row['zipcode'],
    $row['addr1'],
    $row['addr2'],
    $row['memo'],
    $row['agree']
  ]);
}

fclose($out);
exit;
