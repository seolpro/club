<?php
// /mt/admin_visit_log.php
session_start();
require_once __DIR__ . '/config.php';

// ▽ 디버깅용 (문제 원인 확인 위해 임시로 켜두세요)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ 관리자 비밀번호 (필요시 config.php로 빼도 됨)
const VISIT_ADMIN_PW = '2130';

// 🔓 로그아웃
if (isset($_POST['admin_logout'])) {
    $_SESSION['mt_visit_admin'] = false;
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 🔑 로그인 시도
$login_error = '';
if (isset($_POST['admin_pw'])) {
    if ($_POST['admin_pw'] === VISIT_ADMIN_PW) {
        $_SESSION['mt_visit_admin'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = '비밀번호가 올바르지 않습니다.';
    }
}

// 🔐 로그인 안 했으면 로그인 폼만
if (empty($_SESSION['mt_visit_admin'])):
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>🧗 산악회 페이지 방문 로그 (관리자)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:420px;">
  <h4 class="mb-3 text-center">🧗 산악회 방문 로그 관리자</h4>
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post">
        <div class="mb-3">
          <label for="admin_pw" class="form-label">관리자 비밀번호</label>
          <input type="password" name="admin_pw" id="admin_pw" class="form-control" required>
        </div>
        <?php if ($login_error): ?>
          <div class="alert alert-danger py-2"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary w-100">로그인</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
<?php
exit;
endif;

// ================== 여기부터 실제 로그 조회 ==================

try {
    // ⚠️ config.php 안에 get_pdo()가 없고 getDB()만 있다면
    // $pdo = getDB(); 로 바꿔주세요.
    $pdo = get_pdo();

    // 필터값 받기
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to']   ?? '';
    $uri  = $_GET['uri']  ?? '/mt/admin_joinmt.html'; // 기본값: 해당 페이지

    $conditions = [];
    $params     = [];

    // 날짜 필터
    if ($from !== '') {
        $conditions[]    = 'visited_at >= :from';
        $params[':from'] = $from . ' 00:00:00';
    }
    if ($to !== '') {
        $conditions[]   = 'visited_at <= :to';
        $params[':to']  = $to . ' 23:59:59';
    }

    // URI 필터
    if ($uri !== '') {
        $conditions[]   = 'uri = :uri';
        $params[':uri'] = $uri;
    }

    $where = '';
    if ($conditions) {
        $where = 'WHERE ' . implode(' AND ', $conditions);
    }

    // 최근 200건만
    $sql = "
        SELECT visited_at, ip, uri, referer, user_agent
        FROM mt_visit_log
        $where
        ORDER BY visited_at DESC
        LIMIT 200
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    // DB 에러나 함수 미정의 등으로 500 나지 않게 화면에 표시
    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head>
      <meta charset="UTF-8">
      <title>방문 로그 오류</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
    <div class="container py-5">
      <div class="alert alert-danger">
        <h5 class="alert-heading">⚠ 방문 로그 조회 중 오류가 발생했습니다.</h5>
        <p class="mb-1">오류 메시지:</p>
        <pre class="mb-0" style="white-space:pre-wrap; word-break:break-all;">
<?= htmlspecialchars($e->getMessage()) ?>
        </pre>
        <hr>
        <p class="small text-muted mb-0">
          · <code>mt_visit_log</code> 테이블이 생성되어 있는지, <br>
          · <code>get_pdo()</code> (또는 <code>getDB()</code>) 함수가 config.php에 정의되어 있는지 확인해 주세요.
        </p>
      </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>🧗 산악회 페이지 방문 로그 (관리자)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8f9fa; }
    .table-sm td, .table-sm th { font-size:0.85rem; }
    .nowrap { white-space:nowrap; }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">🧗 산악회 전자장부 페이지 방문 기록</h4>
    <form method="post" class="m-0">
      <button type="submit" name="admin_logout" class="btn btn-outline-danger btn-sm">로그아웃</button>
    </form>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-12 col-md-3">
          <label class="form-label">시작일</label>
          <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">종료일</label>
          <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">URI</label>
          <input type="text" name="uri" value="<?= htmlspecialchars($uri) ?>" class="form-control form-control-sm" placeholder="/mt/admin_joinmt.html">
        </div>
        <div class="col-12 col-md-2 text-end">
          <button type="submit" class="btn btn-primary btn-sm w-100">조회</button>
        </div>
      </form>
      <p class="text-muted mt-2 mb-0 small">
        ※ 기본값은 <code>/mt/admin_joinmt.html</code> 방문 기록만 조회합니다.
      </p>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h6 class="mb-2">최근 방문 기록 (최대 200건)</h6>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead class="table-light">
          <tr>
            <th class="nowrap">접속일시</th>
            <th class="nowrap">IP</th>
            <th>URI</th>
            <th>Referer</th>
            <th>User-Agent</th>
          </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">조회된 방문 기록이 없습니다.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="nowrap"><?= htmlspecialchars($r['visited_at']) ?></td>
                <td class="nowrap"><?= htmlspecialchars($r['ip']) ?></td>
                <td><?= htmlspecialchars($r['uri']) ?></td>
                <td><?= htmlspecialchars($r['referer'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['user_agent'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
