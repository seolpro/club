<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_admin();
$pdo=db();
$q=trim((string)($_GET['q']??'')); $from=trim((string)($_GET['from']??'')); $to=trim((string)($_GET['to']??''));
$where=[]; $params=[];
if($q!==''){ $where[]='(a.leader_name LIKE ? OR a.leader_contact LIKE ? OR p.passenger_name LIKE ?)'; $params[]="%$q%"; $params[]="%$q%"; $params[]="%$q%"; }
if($from!==''){ $where[]='a.created_at >= ?'; $params[]=$from.' 00:00:00'; }
if($to!==''){ $where[]='a.created_at <= ?'; $params[]=$to.' 23:59:59'; }
$sql='SELECT a.id,a.created_at,a.leader_name,a.leader_contact,a.passenger_count,a.comment,p.sort_order,p.passenger_name,p.birth6,p.gender FROM '.tb('applications').' a JOIN '.tb('passengers').' p ON p.application_id=a.id '.($where?'WHERE '.implode(' AND ',$where):'').' ORDER BY a.id DESC,p.sort_order ASC';
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
$filename='boarding_list_'.date('Ymd_His').'.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF";
?>
<html><head><meta charset="UTF-8"></head><body><table border="1"><thead><tr><th>접수번호</th><th>접수일시</th><th>대표자</th><th>대표자 연락처</th><th>전체 승선자수</th><th>승선자 순번</th><th>승선자 성명</th><th>생년월일</th><th>성별</th><th>코멘트</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=e($r['id'])?></td><td><?=e($r['created_at'])?></td><td><?=e($r['leader_name'])?></td><td style="mso-number-format:'\@';"><?=e($r['leader_contact'])?></td><td><?=e($r['passenger_count'])?></td><td><?=e($r['sort_order'])?></td><td><?=e($r['passenger_name'])?></td><td style="mso-number-format:'\@';"><?=e($r['birth6'])?></td><td><?=e($r['gender'])?></td><td><?=e($r['comment'])?></td></tr><?php endforeach; ?>
</tbody></table></body></html>
