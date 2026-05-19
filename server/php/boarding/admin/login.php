<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = (string)($_POST['password'] ?? '');
    if (hash_equals(ADMIN_PASSWORD, $pw)) {
        $_SESSION['boarding_admin'] = true;
        header('Location: index.php'); exit;
    }
    $error = '비밀번호가 올바르지 않습니다.';
}
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>관리자 로그인</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{min-height:100vh;background:linear-gradient(135deg,#dbeafe,#f8fafc);display:flex;align-items:center}.login{max-width:420px;margin:auto;background:#fff;border-radius:24px;box-shadow:0 20px 50px rgba(15,23,42,.15);padding:2rem}</style></head><body><div class="container"><form class="login" method="post"><h3 class="fw-bold mb-3">🔐 관리자 로그인</h3><?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?><input type="password" name="password" class="form-control form-control-lg mb-3" placeholder="관리자 비밀번호" autofocus><button class="btn btn-primary btn-lg w-100">로그인</button></form></div></body></html>
