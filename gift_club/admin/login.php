<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
if (!is_installed()) redirect('../install.php');
if (!empty($_SESSION['gift_admin_id'])) redirect('dashboard.php');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $id=trim((string)($_POST['login_id']??'')); $pw=(string)($_POST['password']??'');
    $st=db()->prepare("SELECT * FROM gift_admins WHERE login_id=? AND is_active=1"); $st->execute([$id]); $admin=$st->fetch();
    if ($admin && password_verify($pw,$admin['password_hash'])) {
        session_regenerate_id(true); $_SESSION['gift_admin_id']=$admin['id']; $_SESSION['gift_admin_name']=$admin['display_name'];
        db()->prepare("UPDATE gift_admins SET last_login_at=NOW() WHERE id=?")->execute([$admin['id']]); redirect('dashboard.php');
    }
    $error='아이디 또는 비밀번호가 올바르지 않습니다.';
}
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>관리자 로그인</title><link rel="stylesheet" href="../assets/style.css"></head><body><main class="wrap narrow"><section class="card"><div class="brand"><span class="logo">🔐</span><div><h1>관리자 로그인</h1><p>동아리 및 제출내역 관리</p></div></div><?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>아이디<input name="login_id" required autofocus></label><label>비밀번호<input type="password" name="password" required></label><button class="btn full" type="submit">로그인</button></form><a class="back-link" href="../index.php">← 회원 입력화면으로</a></section></main></body></html>
