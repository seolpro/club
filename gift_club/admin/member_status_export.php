<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
admin_required();

$pdo = db();

$source = (string)($_GET['source'] ?? 'all');
$status = (string)($_GET['status'] ?? 'all');
$q = trim((string)($_GET['q'] ?? ''));

if (!in_array($source, ['all', 'cul', 'mt'], true)) {
    $source = 'all';
}
if (!in_array($status, ['all', 'submitted', 'missing', 'check'], true)) {
    $status = 'all';
}

function csvCollateExpr(string $expr): string
{
    return "CONVERT({$expr} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
}

function csvPhoneSql(string $column): string
{
    $expr = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '-', ''), ' ', ''), '.', ''), '+82', '0')";
    return csvCollateExpr($expr);
}

function csvLoadRosterStatus(PDO $pdo, string $table, string $sourceKey, string $sourceLabel, string $clubKeyword): array
{
    $memberPhone = csvPhoneSql('m.contact');
    $submitPhone = csvPhoneSql('s.phone');
    $memberName = csvCollateExpr('m.name');
    $submitName = csvCollateExpr('s.member_name');
    $clubName = csvCollateExpr('c.name');
    $like = '%' . $clubKeyword . '%';

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
                JOIN gift_clubs c ON c.id=s.club_id
                WHERE {$clubName} LIKE CONVERT(:like1 USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  AND {$submitName}={$memberName}
                  AND {$submitPhone}={$memberPhone}
                ORDER BY s.submitted_at DESC,s.id DESC
                LIMIT 1
            ) exact_submission_id,

            (
                SELECT s.submitted_at
                FROM gift_submissions s
                JOIN gift_clubs c ON c.id=s.club_id
                WHERE {$clubName} LIKE CONVERT(:like2 USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  AND {$submitName}={$memberName}
                  AND {$submitPhone}={$memberPhone}
                ORDER BY s.submitted_at DESC,s.id DESC
                LIMIT 1
            ) submitted_at,

            (
                SELECT s.bank_name
                FROM gift_submissions s
                JOIN gift_clubs c ON c.id=s.club_id
                WHERE {$clubName} LIKE CONVERT(:like3 USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  AND {$submitName}={$memberName}
                  AND {$submitPhone}={$memberPhone}
                ORDER BY s.submitted_at DESC,s.id DESC
                LIMIT 1
            ) bank_name,

            (
                SELECT s.account_no
                FROM gift_submissions s
                JOIN gift_clubs c ON c.id=s.club_id
                WHERE {$clubName} LIKE CONVERT(:like4 USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  AND {$submitName}={$memberName}
                  AND {$submitPhone}={$memberPhone}
                ORDER BY s.submitted_at DESC,s.id DESC
                LIMIT 1
            ) account_no,

            (
                SELECT COUNT(*)
                FROM gift_submissions s
                JOIN gift_clubs c ON c.id=s.club_id
                WHERE {$clubName} LIKE CONVERT(:like5 USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  AND (
                        {$submitName}={$memberName}
                        OR {$submitPhone}={$memberPhone}
                  )
            ) possible_count

        FROM {$table} m
        ORDER BY m.name,m.id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':source_key'=>$sourceKey,
        ':source_label'=>$sourceLabel,
        ':like1'=>$like,
        ':like2'=>$like,
        ':like3'=>$like,
        ':like4'=>$like,
        ':like5'=>$like,
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

$rows = [];

if ($source === 'all' || $source === 'cul') {
    $rows = array_merge($rows, csvLoadRosterStatus($pdo, 'cul_members', 'cul', '문화탐방', '문화탐방'));
}
if ($source === 'all' || $source === 'mt') {
    $rows = array_merge($rows, csvLoadRosterStatus($pdo, 'mt_members', 'mt', '산악회', '산악회'));
}

$rows = array_values(array_filter(
    $rows,
    static function (array $row) use ($status, $q): bool {
        if ($status !== 'all' && $row['submit_status'] !== $status) {
            return false;
        }

        if ($q === '') {
            return true;
        }

        $text = mb_strtolower(
            (string)$row['member_name'] . ' ' .
            (string)$row['contact'] . ' ' .
            (string)$row['source_label']
        );

        return mb_strpos($text, mb_strtolower($q)) !== false;
    }
));

$statusNames = [
    'submitted' => '제출완료',
    'missing' => '미제출',
    'check' => '확인필요',
];

$filename = 'gift_member_status_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

fputcsv($out, [
    '동아리',
    '성명',
    '회원명부 연락처',
    '제출상태',
    '은행명',
    '계좌번호',
    '최종 제출일시',
]);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['source_label'],
        $row['member_name'],
        $row['contact'],
        $statusNames[$row['submit_status']] ?? $row['submit_status'],
        $row['submit_status'] === 'submitted' ? $row['bank_name'] : '',
        $row['submit_status'] === 'submitted' ? $row['account_no'] : '',
        $row['submit_status'] === 'submitted' ? $row['submitted_at'] : '',
    ]);
}

fclose($out);
exit;
