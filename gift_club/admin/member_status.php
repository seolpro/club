<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
admin_required();

$pdo = db();

$source = (string)($_GET['source'] ?? 'all');
$status = (string)($_GET['status'] ?? 'all');
$q = trim((string)($_GET['q'] ?? ''));

$allowedSources = ['all', 'cul', 'mt'];
$allowedStatuses = ['all', 'submitted', 'missing', 'check'];

if (!in_array($source, $allowedSources, true)) {
    $source = 'all';
}
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}

/**
 * 서로 다른 테이블의 collation 차이를 안전하게 해소하기 위해
 * 비교 시점에 utf8mb4_unicode_ci 로 통일합니다.
 */
function collateExpr(string $expr): string
{
    return "CONVERT({$expr} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
}

function phoneSql(string $column): string
{
    $expr = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '-', ''), ' ', ''), '.', ''), '+82', '0')";
    return collateExpr($expr);
}

function loadRosterStatus(PDO $pdo, string $table, string $sourceKey, string $sourceLabel, string $clubKeyword): array
{
    $memberPhone = phoneSql('m.contact');
    $submitPhone = phoneSql('s.phone');

    $memberName = collateExpr('m.name');
    $submitName = collateExpr('s.member_name');
    $clubName = collateExpr('c.name');

    $sql = "
        SELECT
            m.id AS member_id,
            m.name AS member_name,
            m.contact AS contact,
            :source_key AS source_key,
            :source_label AS source_label,

            (
                SELECT s.id
                FROM gift_submissions s
                JOIN gift_clubs c ON c.id = s.club_id
                WHERE {$clubName} LIKE CONVERT(:club_like_exact USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  AND {$submitName} = {$memberName}
                  AND {$submitPhone} = {$memberPhone}
                ORDER BY s.submitted_at DESC, s.id DESC
                LIMIT 1
            ) AS exact_submission_id,

            (
                SELECT s.submitted_at
                FROM gift_submissions s
                JOIN gift_clubs c ON c.id = s.club_id
                WHERE {$clubName} LIKE CONVERT(:club_like_date USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  AND {$submitName} = {$memberName}
                  AND {$submitPhone} = {$memberPhone}
                ORDER BY s.submitted_at DESC, s.id DESC
                LIMIT 1
            ) AS submitted_at,

            (
                SELECT s.bank_name
                FROM gift_submissions s
                JOIN gift_clubs c ON c.id = s.club_id
                WHERE {$clubName} LIKE CONVERT(:club_like_bank USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  AND {$submitName} = {$memberName}
                  AND {$submitPhone} = {$memberPhone}
                ORDER BY s.submitted_at DESC, s.id DESC
                LIMIT 1
            ) AS bank_name,

            (
                SELECT s.account_no
                FROM gift_submissions s
                JOIN gift_clubs c ON c.id = s.club_id
                WHERE {$clubName} LIKE CONVERT(:club_like_account USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  AND {$submitName} = {$memberName}
                  AND {$submitPhone} = {$memberPhone}
                ORDER BY s.submitted_at DESC, s.id DESC
                LIMIT 1
            ) AS account_no,

            (
                SELECT COUNT(*)
                FROM gift_submissions s
                JOIN gift_clubs c ON c.id = s.club_id
                WHERE {$clubName} LIKE CONVERT(:club_like_possible USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  AND (
                        {$submitName} = {$memberName}
                        OR {$submitPhone} = {$memberPhone}
                  )
            ) AS possible_count

        FROM {$table} m
        ORDER BY m.name, m.id
    ";

    $stmt = $pdo->prepare($sql);
    $like = '%' . $clubKeyword . '%';
    $stmt->execute([
        ':source_key' => $sourceKey,
        ':source_label' => $sourceLabel,
        ':club_like_exact' => $like,
        ':club_like_date' => $like,
        ':club_like_bank' => $like,
        ':club_like_account' => $like,
        ':club_like_possible' => $like,
    ]);

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        if (!empty($row['exact_submission_id'])) {
            $row['submit_status'] = 'submitted';
        } elseif ((int)$row['possible_count'] > 0) {
            $row['submit_status'] = 'check';
        } else {
            $row['submit_status'] = 'missing';
        }
    }
    unset($row);

    return $rows;
}

$allRows = [];

try {
    if ($source === 'all' || $source === 'cul') {
        $allRows = array_merge(
            $allRows,
            loadRosterStatus($pdo, 'cul_members', 'cul', '문화탐방', '문화탐방')
        );
    }

    if ($source === 'all' || $source === 'mt') {
        $allRows = array_merge(
            $allRows,
            loadRosterStatus($pdo, 'mt_members', 'mt', '산악회', '산악회')
        );
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('회원 제출현황을 불러오는 중 오류가 발생했습니다: ' . e($e->getMessage()));
}

$stats = [
    'total' => count($allRows),
    'submitted' => 0,
    'missing' => 0,
    'check' => 0,
];

foreach ($allRows as $row) {
    if (isset($stats[$row['submit_status']])) {
        $stats[$row['submit_status']]++;
    }
}

$stats['rate'] = $stats['total'] > 0
    ? round(($stats['submitted'] / $stats['total']) * 100, 1)
    : 0.0;

$rows = array_values(array_filter(
    $allRows,
    static function (array $row) use ($status, $q): bool {
        if ($status !== 'all' && $row['submit_status'] !== $status) {
            return false;
        }

        if ($q === '') {
            return true;
        }

        $haystack = mb_strtolower(
            (string)$row['member_name'] . ' ' .
            (string)$row['contact'] . ' ' .
            (string)$row['source_label']
        );

        return mb_strpos($haystack, mb_strtolower($q)) !== false;
    }
));

$queryString = http_build_query([
    'source' => $source,
    'status' => $status,
    'q' => $q,
]);

function statusLabel(string $status): string
{
    return match ($status) {
        'submitted' => '제출완료',
        'check' => '확인필요',
        default => '미제출',
    };
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>회원 제출현황</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .status-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:13px;margin-bottom:20px}
        .status-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px 20px;box-shadow:var(--shadow)}
        .status-card span{display:block;color:var(--muted);font-size:.82rem;font-weight:800;margin-bottom:4px}
        .status-card strong{font-size:1.75rem;letter-spacing:-.03em}
        .status-card small{color:var(--muted)}
        .status-card.good strong{color:#15845a}.status-card.wait strong{color:#b9770e}
        .status-card.warn strong{color:#c14b54}.status-card.rate strong{color:var(--primary)}
        .member-status{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:999px;font-size:.78rem;font-weight:900;white-space:nowrap}
        .member-status.submitted{background:#e8f7ef;color:#16734b}
        .member-status.missing{background:#fff5df;color:#936313}
        .member-status.check{background:#fff0f0;color:#a63e48}
        .source-tag{display:inline-block;background:#eef3ff;color:#3558ba;padding:4px 8px;border-radius:8px;font-size:.78rem;font-weight:800;white-space:nowrap}
        .member-phone{white-space:nowrap}.mini-note{color:var(--muted);font-size:.78rem}
        .help-box{padding:13px 15px;border-radius:13px;background:#f7f9fc;border:1px solid var(--line);color:var(--muted);font-size:.85rem;margin-bottom:16px}
        .help-box b{color:var(--text)}
        .filter-links{display:flex;gap:7px;flex-wrap:wrap}
        .filter-links a{display:inline-flex;text-decoration:none;padding:8px 11px;border-radius:10px;background:#edf0f5;color:#48546a;font-size:.83rem;font-weight:800}
        .filter-links a.active{background:var(--primary);color:#fff}
        @media(max-width:900px){.status-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:600px){.status-grid{grid-template-columns:1fr 1fr}.status-card{padding:15px}.status-card strong{font-size:1.45rem}}
    </style>
</head>
<body class="admin-body">

<?php include __DIR__ . '/_nav.php'; ?>

<main class="admin-wrap wide">
    <div class="page-head">
        <div>
            <span class="eyebrow">회원명부 기준</span>
            <h1>회원 제출현황</h1>
            <p>문화탐방·산악회 회원명부와 최종 제출내역을 비교하여 미제출자를 확인합니다.</p>
        </div>
        <a class="btn" href="member_status_export.php?<?= e($queryString) ?>">CSV 내려받기</a>
    </div>

    <section class="status-grid">
        <div class="status-card"><span>👥 전체 회원</span><strong><?= number_format($stats['total']) ?></strong><small>명</small></div>
        <div class="status-card good"><span>✅ 제출완료</span><strong><?= number_format($stats['submitted']) ?></strong><small>명</small></div>
        <div class="status-card wait"><span>⏳ 미제출</span><strong><?= number_format($stats['missing']) ?></strong><small>명</small></div>
        <div class="status-card warn"><span>⚠️ 확인필요</span><strong><?= number_format($stats['check']) ?></strong><small>명</small></div>
        <div class="status-card rate"><span>📊 제출률</span><strong><?= e(number_format($stats['rate'], 1)) ?>%</strong><small>정확히 매칭된 기준</small></div>
    </section>

    <section class="card">
        <div class="help-box">
            <b>판정기준:</b> 성명 + 연락처가 모두 일치하면 <b>제출완료</b>,
            이름 또는 연락처 중 하나만 일치하면 <b>확인필요</b>,
            관련 제출이 없으면 <b>미제출</b>입니다.
            산악회+문화탐방 제출은 두 회원명부 모두 제출로 인정됩니다.
        </div>

        <form method="get" class="filter-bar">
            <select name="source">
                <option value="all" <?= $source === 'all' ? 'selected' : '' ?>>전체 동아리</option>
                <option value="cul" <?= $source === 'cul' ? 'selected' : '' ?>>문화탐방</option>
                <option value="mt" <?= $source === 'mt' ? 'selected' : '' ?>>산악회</option>
            </select>

            <select name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>전체 상태</option>
                <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>제출완료</option>
                <option value="missing" <?= $status === 'missing' ? 'selected' : '' ?>>미제출</option>
                <option value="check" <?= $status === 'check' ? 'selected' : '' ?>>확인필요</option>
            </select>

            <input name="q" value="<?= e($q) ?>" placeholder="성명·연락처 검색">
            <button class="btn small" type="submit">검색</button>
            <a class="btn small secondary" href="member_status.php">초기화</a>
            <span class="count">표시 <b><?= number_format(count($rows)) ?></b>명</span>
        </form>

        <div class="filter-links" style="margin:14px 0 16px">
            <a class="<?= $status === 'all' ? 'active' : '' ?>" href="?<?= e(http_build_query(['source'=>$source,'status'=>'all','q'=>$q])) ?>">전체</a>
            <a class="<?= $status === 'submitted' ? 'active' : '' ?>" href="?<?= e(http_build_query(['source'=>$source,'status'=>'submitted','q'=>$q])) ?>">✅ 제출완료</a>
            <a class="<?= $status === 'missing' ? 'active' : '' ?>" href="?<?= e(http_build_query(['source'=>$source,'status'=>'missing','q'=>$q])) ?>">⏳ 미제출만 보기</a>
            <a class="<?= $status === 'check' ? 'active' : '' ?>" href="?<?= e(http_build_query(['source'=>$source,'status'=>'check','q'=>$q])) ?>">⚠️ 확인필요</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>동아리</th><th>성명</th><th>회원명부 연락처</th><th>제출상태</th>
                    <th>은행</th><th>계좌번호</th><th>최종 제출일시</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><span class="source-tag"><?= e($row['source_label']) ?></span></td>
                        <td><b><?= e($row['member_name']) ?></b></td>
                        <td class="member-phone"><?= e((string)$row['contact']) ?></td>
                        <td>
                            <span class="member-status <?= e($row['submit_status']) ?>">
                                <?= $row['submit_status'] === 'submitted' ? '✅' : ($row['submit_status'] === 'check' ? '⚠️' : '⏳') ?>
                                <?= e(statusLabel($row['submit_status'])) ?>
                            </span>
                        </td>
                        <td><?= $row['submit_status'] === 'submitted' ? e((string)$row['bank_name']) : '<span class="mini-note">-</span>' ?></td>
                        <td class="account"><?= $row['submit_status'] === 'submitted' ? e(mask_account((string)$row['account_no'])) : '<span class="mini-note">-</span>' ?></td>
                        <td><?= $row['submit_status'] === 'submitted' ? e((string)$row['submitted_at']) : '<span class="mini-note">-</span>' ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="empty">조건에 해당하는 회원이 없습니다.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
