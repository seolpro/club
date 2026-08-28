<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/*
 * 산악회 급여공제용 mt_members + cul_members 통합 조회 API
 *
 * 중요:
 * 아래 DB 연결 include 경로와 mt_members의 컬럼명 3개만
 * 현재 서버 구조에 맞게 확인/수정하면 됩니다.
 */

// ===== 1) DB 연결 =====
// 예시 A: 공통 DB 설정 + db() 함수가 이미 있는 경우
// require_once dirname(__DIR__) . '/private_config/db_common.php';
// require_once __DIR__ . '/lib.php';
// $pdo = db();

// 예시 B: 이 파일에서 직접 PDO 생성하는 경우
// $cfg = require dirname(__DIR__) . '/private_config/db_common.php';
// $pdo = new PDO(
//     "mysql:host={$cfg['host']};port=".($cfg['port'] ?? 3306).";dbname={$cfg['name']};charset=".($cfg['charset'] ?? 'utf8mb4'),
//     $cfg['user'],
//     $cfg['pass'],
//     [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
// );

// 실제 프로젝트의 공통 DB 함수가 있다면 아래 2줄 형태로 교체 권장:
// require_once __DIR__ . '/lib.php';
// $pdo = db();

$dbConfigFile = dirname(__DIR__) . '/private_config/db_common.php';
$cfg = require $dbConfigFile;

if (function_exists('db')) {
    $pdo = db();
} elseif (is_array($cfg)) {
    // db_common.php가 설정 배열을 return 하는 구조
    $pdo = new PDO(
        'mysql:host=' . $cfg['host']
        . ';port=' . ($cfg['port'] ?? 3306)
        . ';dbname=' . $cfg['name']
        . ';charset=' . ($cfg['charset'] ?? 'utf8mb4'),
        $cfg['user'],
        $cfg['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} else {
    throw new RuntimeException('DB 연결 설정을 확인해 주세요.');
}

// ===== 2) mt_members / cul_members 공통 컬럼명 =====
// 서버 테이블 실제 컬럼명이 다르면 이 3개만 수정하세요.
const MEMBER_NAME_COL    = 'name';
const MEMBER_PHONE_COL   = 'contact';
const MEMBER_EMP_NO_COL  = 'employee_id';

function out(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function phone_digits(string $v): string {
    return preg_replace('/\D+/', '', $v) ?? '';
}

try {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        out(['ok' => false, 'message' => '잘못된 요청입니다.'], 400);
    }

    if (($input['action'] ?? '') !== 'match_members') {
        out(['ok' => false, 'message' => '지원하지 않는 action입니다.'], 400);
    }

    $entries = $input['entries'] ?? [];
    if (!is_array($entries)) {
        out(['ok' => false, 'message' => 'entries 형식이 올바르지 않습니다.'], 400);
    }

    $nameCol = MEMBER_NAME_COL;
    $phoneCol = MEMBER_PHONE_COL;
    $empCol = MEMBER_EMP_NO_COL;

    // 산악회(mt_members) + 문화탐방(cul_members)을 함께 조회합니다.
    // 두 테이블의 컬럼 구조가 동일하므로 UNION ALL로 통합 조회합니다.
    $sql = "
        SELECT member_name, member_phone, employee_no, member_type
        FROM (
            SELECT
                {$nameCol} AS member_name,
                {$phoneCol} AS member_phone,
                {$empCol} AS employee_no,
                '산악회' AS member_type
            FROM mt_members
            WHERE {$nameCol} = :mt_name
              AND REPLACE(REPLACE(REPLACE({$phoneCol}, '-', ''), ' ', ''), '.', '') = :mt_phone

            UNION ALL

            SELECT
                {$nameCol} AS member_name,
                {$phoneCol} AS member_phone,
                {$empCol} AS employee_no,
                '문화탐방' AS member_type
            FROM cul_members
            WHERE {$nameCol} = :cul_name
              AND REPLACE(REPLACE(REPLACE({$phoneCol}, '-', ''), ' ', ''), '.', '') = :cul_phone
        ) x
        LIMIT 4
    ";
    $stmt = $pdo->prepare($sql);

    $rows = [];

    foreach ($entries as $entry) {
        $id = (int)($entry['id'] ?? 0);
        $name = trim((string)($entry['name'] ?? ''));
        $phone = phone_digits((string)($entry['contact'] ?? ''));

        if ($id <= 0 || $name === '' || $phone === '') {
            $rows[] = [
                'id' => $id,
                'matched' => false,
                'employeeNo' => '',
                'message' => '신청정보 부족',
            ];
            continue;
        }

        $stmt->execute([
            ':mt_name' => $name,
            ':mt_phone' => $phone,
            ':cul_name' => $name,
            ':cul_phone' => $phone,
        ]);
        $matches = $stmt->fetchAll();

        if (count($matches) === 1) {
            $rows[] = [
                'id' => $id,
                'matched' => true,
                'employeeNo' => (string)($matches[0]['employee_no'] ?? ''),
                'memberType' => (string)($matches[0]['member_type'] ?? ''),
                'message' => '일치',
            ];
        } elseif (count($matches) > 1) {
            // 동일인이 두 동아리에 모두 등록된 경우, 사번이 같다면 정상 회원으로 처리합니다.
            $employeeNos = array_values(array_unique(array_filter(array_map(
                static fn(array $m): string => (string)($m['employee_no'] ?? ''),
                $matches
            ))));

            $memberTypes = array_values(array_unique(array_filter(array_map(
                static fn(array $m): string => (string)($m['member_type'] ?? ''),
                $matches
            ))));

            if (count($employeeNos) === 1) {
                $rows[] = [
                    'id' => $id,
                    'matched' => true,
                    'employeeNo' => $employeeNos[0],
                    'memberType' => implode('+', $memberTypes),
                    'message' => '일치',
                ];
            } else {
                $rows[] = [
                    'id' => $id,
                    'matched' => false,
                    'employeeNo' => '',
                    'memberType' => implode('+', $memberTypes),
                    'message' => '회원정보 중복확인',
                ];
            }
        } else {
            $rows[] = [
                'id' => $id,
                'matched' => false,
                'employeeNo' => '',
                'memberType' => '',
                'message' => '회원정보 없음',
            ];
        }
    }

    out(['ok' => true, 'rows' => $rows]);

} catch (Throwable $e) {
    error_log('[payroll_api] ' . $e->getMessage());
    out(['ok' => false, 'message' => '서버 조회 중 오류가 발생했습니다.'], 500);
}
