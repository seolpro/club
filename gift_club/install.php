<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $adminId = trim((string)($_POST['admin_id'] ?? 'admin'));
    $adminPw = (string)($_POST['admin_pw'] ?? '');
    $adminName = trim((string)($_POST['admin_name'] ?? '관리자'));

    if ($adminId === '' || strlen($adminId) < 3) {
        $error = '관리자 아이디는 3자 이상 입력해 주세요.';
    } elseif (strlen($adminPw) < 6) {
        $error = '관리자 비밀번호는 6자 이상 입력해 주세요.';
    } else {
        try {
            $pdo = db();

            /*
             * 중요:
             * MySQL의 CREATE TABLE 같은 DDL은 implicit commit을 발생시키므로
             * 설치 작업 전체를 beginTransaction()/commit()으로 감싸지 않습니다.
             */

            $pdo->exec("CREATE TABLE IF NOT EXISTS gift_clubs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_gift_club_name (name),
                KEY idx_gift_clubs_active_sort (is_active, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS gift_submissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                club_id INT UNSIGNED NOT NULL,
                member_name VARCHAR(80) NOT NULL,
                phone VARCHAR(30) NOT NULL,
                phone_norm VARCHAR(20) NOT NULL,
                bank_name VARCHAR(80) NOT NULL,
                account_no VARCHAR(100) NOT NULL,
                comment VARCHAR(500) NULL,
                submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_gift_submissions_club
                    FOREIGN KEY (club_id) REFERENCES gift_clubs(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT,
                UNIQUE KEY uk_gift_submission_member (member_name, phone_norm),
                KEY idx_gift_submission_club (club_id),
                KEY idx_gift_submission_date (submitted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS gift_admins (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                login_id VARCHAR(80) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(80) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_gift_admin_login (login_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS gift_settings (
                setting_key VARCHAR(80) PRIMARY KEY,
                setting_value TEXT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // 관리자 계정 생성 또는 재설정
            $stmt = $pdo->prepare("
                INSERT INTO gift_admins
                    (login_id, password_hash, display_name, is_active)
                VALUES
                    (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    password_hash = VALUES(password_hash),
                    display_name = VALUES(display_name),
                    is_active = 1
            ");
            $stmt->execute([
                $adminId,
                password_hash($adminPw, PASSWORD_DEFAULT),
                $adminName
            ]);

            // 기본 설정은 최초 설치 때만 입력
            $defaults = [
                ['title', '동아리 추석선물 입금계좌 등록'],
                ['notice', '추석선물 입금을 위한 본인 명의의 계좌정보를 정확히 입력해 주세요. 재제출 시 가장 최근 제출내역으로 자동 변경됩니다.'],
                ['closed', '0'],
            ];

            $stmt = $pdo->prepare("
                INSERT INTO gift_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = setting_value
            ");

            foreach ($defaults as $row) {
                $stmt->execute($row);
            }

            $message = '설치가 완료되었습니다. 보안을 위해 install.php는 삭제하거나 파일명을 변경해 주세요.';

        } catch (Throwable $e) {
            $error = '설치 중 오류: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>설치 - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="wrap narrow">
    <section class="card">
        <div class="brand">
            <span class="logo">🎁</span>
            <div>
                <h1>gift_club 설치</h1>
                <p>추석선물 입금계좌 수집 시스템</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert success"><?= e($message) ?></div>
            <div class="actions">
                <a class="btn" href="index.php">회원 입력화면</a>
                <a class="btn secondary" href="admin/login.php">관리자 로그인</a>
            </div>
        <?php else: ?>

            <?php if ($error): ?>
                <div class="alert danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="alert info">
                생성 테이블:
                <b>gift_clubs</b>,
                <b>gift_submissions</b>,
                <b>gift_admins</b>,
                <b>gift_settings</b>
            </div>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label>
                    관리자 아이디
                    <input name="admin_id" value="admin" required minlength="3">
                </label>

                <label>
                    관리자 이름
                    <input name="admin_name" value="관리자" required>
                </label>

                <label>
                    관리자 비밀번호
                    <input
                        type="password"
                        name="admin_pw"
                        required
                        minlength="6"
                        autocomplete="new-password"
                        placeholder="6자 이상"
                    >
                </label>

                <button class="btn full" type="submit">설치 시작</button>
            </form>

        <?php endif; ?>
    </section>
</main>
</body>
</html>
