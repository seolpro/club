<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

$ADMIN_TOKEN = 'set-your-strong-token-here';
if (!isset($_GET['token']) || $_GET['token'] !== $ADMIN_TOKEN) {
  http_response_code(401);
  echo "Unauthorized"; exit;
}

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$ps = 20;
$off = ($page - 1) * $ps;

$where = '1=1';
$params = [];
$types  = '';

if ($q !== '') {
  $where .= " AND (name LIKE CONCAT('%',?,'%') OR tel LIKE CONCAT('%',?,'%') OR addr1 LIKE CONCAT('%',?,'%') OR addr2 LIKE CONCAT('%',?,'%'))";
  $params = [$q,$q,$q,$q];
  $types  = 'ssss';
}

// 총 건수
$totalSql = "SELECT COUNT(*) FROM visit_list WHERE {$where}";
$stmt = db()->prepare($totalSql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

// 목록
$listSql = "SELECT id, created_at, name, tel, zipcode, addr1, addr2, memo, agree, ticket_path, ticket_name, ticket_mime 
            FROM visit_list
            WHERE {$where}
            ORDER BY id DESC
            LIMIT ? OFFSET ?";
$stmt = db()->prepare($listSql);
if ($types) {
  $types2 = $types . 'ii';
  $params2 = array_merge($params, [$ps, $off]);
  $stmt->bind_param($types2, ...$params2);
} else {
  $stmt->bind_param('ii', $ps, $off);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pages = max(1, ceil($total / $ps));
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>관리자 | 방문자 접수 리스트</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    body{ background:#f6f8fb; }
    .container{ max-width:1100px; }
    .thumb{ width:64px; height:64px; object-fit:cover; border-radius:8px; }
    .truncate{ max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">제주 단체여행객 신청 목록</h1>
    <div>
      <a class="btn btn-outline-secondary btn-sm" href="export_csv.php?token=<?=$ADMIN_TOKEN?>&q=<?=urlencode($q)?>">CSV 내보내기</a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="token" value="<?=$ADMIN_TOKEN?>">
    <div class="col-12 col-md-8">
      <input class="form-control" type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="이름/연락처/주소 검색">
    </div>
    <div class="col-12 col-md-4 d-grid d-md-flex gap-2">
      <button class="btn btn-primary" type="submit">검색</button>
      <a class="btn btn-outline-secondary" href="index.php?token=<?=$ADMIN_TOKEN?>">전체보기</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>접수일시</th>
          <th>성명</th>
          <th>연락처</th>
          <th>주소</th>
          <th>탑승권</th>
          <th>비고</th>
          <th>동의</th>
          <th>다운</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?=$r['id']?></td>
          <td><?=$r['created_at']?></td>
          <td><?=$r['name']?></td>
          <td><?=$r['tel']?></td>
          <td class="truncate">[<?=$r['zipcode']?>] <?=$r['addr1']?> <?=$r['addr2']?></td>
          <td>
            <?php if ($r['ticket_path']): ?>
              <img src="download.php?token=<?=$ADMIN_TOKEN?>&id=<?=$r['id']?>&thumb=1" class="thumb" alt="ticket">
            <?php else: ?>
              <span class="text-muted small">없음</span>
            <?php endif; ?>
          </td>
          <td class="truncate"><?=nl2br(htmlspecialchars($r['memo']))?></td>
          <td><?=$r['agree']?></td>
          <td>
            <a class="btn btn-sm btn-outline-primary" 
               href="download.php?token=<?=$ADMIN_TOKEN?>&id=<?=$r['id']?>">다운로드</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <nav>
    <ul class="pagination">
      <?php for ($i=1; $i<=$pages; $i++): ?>
        <li class="page-item <?=$i===$page?'active':''?>">
          <a class="page-link" href="?token=<?=$ADMIN_TOKEN?>&q=<?=urlencode($q)?>&page=<?=$i?>"><?=$i?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>
</body>
</html>
