<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$message = '';
$error = '';

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);

    return (int)$stmt->fetchColumn() > 0;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $pdo = db();

        $tables = ['cul_members', 'mt_members'];
        $added = [];
        $already = [];

        foreach ($tables as $table) {
            if (!tableExists($pdo, $table)) {
                throw new RuntimeException("{$table} 테이블을 찾을 수 없습니다.");
            }

            if (!columnExists($pdo, $table, 'bank_name')) {
                $pdo->exec("
                    ALTER TABLE {$table}
                    ADD COLUMN bank_name VARCHAR(80) NULL AFTER contact
                ");
                $added[] = "{$table}.bank_name";
            } else {
                $already[] = "{$table}.bank_name";
            }

            if (!columnExists($pdo, $table, 'account_no')) {
                $pdo->exec("
                    ALTER TABLE {$table}
                    ADD COLUMN account_no VARCHAR(100) NULL AFTER bank_name
                ");
                $added[] = "{$table}.account_no";
            } else {
                $already[] = "{$table}.account_no";
            }

            if (!columnExists($pdo, $table, 'account_updated_at')) {
                $pdo->exec("
                    ALTER TABLE {$table}
                    ADD COLUMN account_updated_at DATETIME NULL AFTER account_no
                ");
                $added[] = "{$table}.account_updated_at";
            } else {
                $already[] = "{$table}.account_updated_at";
            }
        }

        $message = '회원명부 연동용 컬럼 설치가 완료되었습니다.';
        if ($added) {
            $message .= ' 추가: ' . implode(', ', $added);
        }
        if ($already) {
            $message .= ' / 기존 컬럼: ' . implode(', ', $already);
        }

    } catch (Throwable $e) {
        $error = '컬럼 설치 중 오류: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>회원명부 연동 컬럼 설치</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="wrap narrow">
    <section class="card">
        <div class="brand">
            <span class="logo">🔗</span>
            <div>
                <h1>회원명부 연동 패치</h1>
                <p>cul_members / mt_members 계좌정보 컬럼 추가</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert success"><?= e($message) ?></div>

            <div class="alert info">
                <b>다음 단계</b><br>
                서버의 <code>submit.php</code>를 패치 버전으로 교체한 뒤,
                보안을 위해 이 <code>member_link_patch_install.php</code> 파일은 삭제해 주세요.
            </div>

            <div class="actions">
                <a class="btn" href="index.php">회원 입력화면</a>
            </div>
        <?php else: ?>

            <?php if ($error): ?>
                <div class="alert danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="alert info">
                다음 컬럼을 필요한 경우에만 자동 추가합니다.<br><br>
                <b>cul_members</b>:
                bank_name, account_no, account_updated_at<br>
                <b>mt_members</b>:
                bank_name, account_no, account_updated_at
            </div>

            <form method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <button class="btn full" type="submit">
                    연동 컬럼 설치
                </button>
            </form>

        <?php endif; ?>
    </section>
</main>
</body>
</html>
