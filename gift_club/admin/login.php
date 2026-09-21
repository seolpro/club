<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

// 설치 여부 확인
if (!is_installed()) {
    redirect('../install.php');
}

// 이미 로그인되어 있으면 동아리별 회원 제출현황으로 이동
if (!empty($_SESSION['gift_admin_id'])) {
    redirect('member_status.php');
}

$error = '';

// 로그인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $id = trim((string)($_POST['login_id'] ?? ''));
    $pw = (string)($_POST['password'] ?? '');

    // 활성화된 관리자 계정 조회
    $st = db()->prepare("
        SELECT *
        FROM gift_admins
        WHERE login_id = ?
          AND is_active = 1
        LIMIT 1
    ");

    $st->execute([$id]);
    $admin = $st->fetch();

    // 비밀번호 확인
    if ($admin && password_verify($pw, (string)$admin['password_hash'])) {

        // 세션 ID 재발급
        session_regenerate_id(true);

        $_SESSION['gift_admin_id']   = $admin['id'];
        $_SESSION['gift_admin_name'] = $admin['display_name'];

        // 최종 로그인 시간 기록
        db()->prepare("
            UPDATE gift_admins
            SET last_login_at = NOW()
            WHERE id = ?
        ")->execute([$admin['id']]);

        // ★ 관리자 로그인 후 초기화면
        redirect('member_status.php');
    }

    $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
}

?>
<!doctype html>
<html lang="ko">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>관리자 로그인</title>

    <link
        rel="stylesheet"
        href="../assets/style.css"
    >
</head>

<body>

<main class="wrap narrow">

    <section class="card">

        <div class="brand">

            <span class="logo">🔐</span>

            <div>
                <h1>관리자 로그인</h1>
                <p>동아리 및 제출내역 관리</p>
            </div>

        </div>

        <?php if ($error): ?>

            <div class="alert danger">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form method="post">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <label>
                아이디

                <input
                    type="text"
                    name="login_id"
                    autocomplete="username"
                    required
                    autofocus
                >
            </label>

            <label>
                비밀번호

                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </label>

            <button
                class="btn full"
                type="submit"
            >
                로그인
            </button>

        </form>

        <a
            class="back-link"
            href="../index.php"
        >
            ← 회원 입력화면으로
        </a>

    </section>

</main>

</body>
</html>