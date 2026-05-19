<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_admin();
$pdo = db();
$q = trim((string)($_GET['q'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$where=[]; $params=[];
if($q!==''){ $where[]='(a.leader_name LIKE ? OR a.leader_contact LIKE ? OR EXISTS (SELECT 1 FROM '.tb('passengers').' p WHERE p.application_id=a.id AND p.passenger_name LIKE ?))'; $params[]="%$q%"; $params[]="%$q%"; $params[]="%$q%"; }
if($from!==''){ $where[]='a.created_at >= ?'; $params[]=$from.' 00:00:00'; }
if($to!==''){ $where[]='a.created_at <= ?'; $params[]=$to.' 23:59:59'; }
$sql='SELECT a.* FROM '.tb('applications').' a '.($where?'WHERE '.implode(' AND ',$where):'').' ORDER BY a.id DESC LIMIT 500';
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
$totalPassengers = array_sum(array_map(fn($r)=>(int)$r['passenger_count'],$rows));
$query = http_build_query(['q'=>$q,'from'=>$from,'to'=>$to]);
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>승선자 관리자</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f8fafc}.top{background:linear-gradient(135deg,#1d4ed8,#06b6d4);color:#fff;border-radius:0 0 30px 30px}.cardx{border:0;border-radius:22px;box-shadow:0 10px 30px rgba(15,23,42,.08)}.table thead th{background:#f1f5f9;white-space:nowrap}.pill{border-radius:999px}.nowrap{white-space:nowrap}</style></head><body>
<div class="top py-4 mb-4"><div class="container d-flex justify-content-between align-items-center"><div><h2 class="fw-bold mb-1">🚢 승선자 접수 관리자</h2><div class="opacity-75">최근 500건 기준 · 조회 <?=count($rows)?>건 · 승선자 <?=$totalPassengers?>명</div></div><a href="logout.php" class="btn btn-light pill">로그아웃</a></div></div>
<div class="container pb-5">
  <div class="card cardx mb-3"><div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-md-4"><label class="form-label">검색</label><input class="form-control" name="q" value="<?=e($q)?>" placeholder="대표자/연락처/승선자명"></div>
      <div class="col-md-3"><label class="form-label">시작일</label><input type="date" class="form-control" name="from" value="<?=e($from)?>"></div>
      <div class="col-md-3"><label class="form-label">종료일</label><input type="date" class="form-control" name="to" value="<?=e($to)?>"></div>
      <div class="col-md-2 d-grid"><button class="btn btn-primary">조회</button></div>
    </form>
    <div class="d-flex gap-2 mt-3"><a class="btn btn-success" href="export.php?<?=e($query)?>">📥 엑셀 다운로드</a><a class="btn btn-outline-secondary" href="index.php">초기화</a></div>
  </div></div>
  <div class="card cardx"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th><th>접수일시</th><th>대표자</th><th>연락처</th><th>승선자수</th><th>코멘트</th><th>관리</th></tr></thead><tbody>
  <?php foreach($rows as $r): ?><tr><td class="fw-bold"><?=e($r['id'])?></td><td class="nowrap"><?=e($r['created_at'])?></td><td><?=e($r['leader_name'])?></td><td><?=e($r['leader_contact'])?></td><td><span class="badge text-bg-primary"><?=e($r['passenger_count'])?>명</span></td><td><?=e(mb_strimwidth((string)$r['comment'],0,45,'...','UTF-8'))?></td><td class="nowrap"><a class="btn btn-sm btn-outline-primary" href="detail.php?id=<?=e($r['id'])?>">상세</a> <a class="btn btn-sm btn-outline-danger" onclick="return confirm('삭제하시겠습니까?')" href="delete.php?id=<?=e($r['id'])?>">삭제</a></td></tr><?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="7" class="text-center text-secondary py-5">조회 결과가 없습니다.</td></tr><?php endif; ?>
  </tbody></table></div></div>
</div></body></html>
