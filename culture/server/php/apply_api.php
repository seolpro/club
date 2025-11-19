<?php
// /www/mt/apply_api.php
require_once __DIR__ . '/config.php';

// ✅ config.php 에 json_response 가 없을 때 대비
if (!function_exists('json_response')) {
    function json_response($data, int $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 필요시 CORS 열기 (같은 도메인이면 굳이 안 써도 됨)
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Headers: Content-Type');
// header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// JSON 또는 일반 POST 모두 처리
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

/**
 * ✅ GAS Web App(메일 릴레이)로 알림 전송
 */
function notify_via_gas(array $payload): void {
    if (!defined('GAS_MAIL_URL') || !GAS_MAIL_URL) {
        return; // 설정이 없으면 그냥 무시
    }

    $ch = curl_init(GAS_MAIL_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = get_pdo();

    /* ------------------------------------------------------------------
     * 1) 신청 목록 조회 (사용자 + 관리자 공용)
     *    GET /apply_api.php
     * -----------------------------------------------------------------*/
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT
              id,
              created_at,
              name,
              contact,
              participants,
              non_members,
              member_names,
              course,
              comment,
              is_waiting
            FROM " . TABLE_MT . "
            WHERE deleted_at IS NULL
            ORDER BY created_at ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $entryList = array_map(function ($row) {
            return [
                'id'           => (int)$row['id'],
                'time'         => $row['created_at'],
                'name'         => $row['name'],
                'contact'      => $row['contact'],
                'participants' => (int)$row['participants'],
                'nonMembers'   => (int)$row['non_members'],
                'memberNames'  => $row['member_names'] ?? '',
                'course'       => $row['course'] ?? '',
                'comment'      => $row['comment'] ?? '',
                'isWaiting'    => (bool)$row['is_waiting'],
            ];
        }, $rows);

        json_response(['ok' => true, 'list' => $entryList]);
    }

    /* ------------------------------------------------------------------
     * 2) 이하 POST 메소드 처리
     * -----------------------------------------------------------------*/
    if ($method !== 'POST') {
        json_response(['ok' => false, 'msg' => '지원하지 않는 메소드입니다.'], 405);
    }

    // ✅ 기본값은 'register', 특별히 'delete'만 삭제로 처리
    $action = $data['action'] ?? 'register';

    /* ------------------------------------------------------------------
     * 2-2) 신청 삭제 (사용자 취소 + 관리자 삭제)
     *     action 이 'delete' 일 때만 삭제
     * -----------------------------------------------------------------*/
    if ($action === 'delete') {
        $id     = isset($data['id']) ? (int)$data['id'] : 0;
        $reason = trim($data['reason'] ?? '');

        if ($id <= 0) {
            json_response(['ok' => false, 'msg' => '삭제할 ID가 없습니다.'], 400);
        }

        // 삭제 대상 찾기
        $stmt = $pdo->prepare("
            SELECT id, name, contact, participants, created_at
            FROM " . TABLE_MT . "
            WHERE id = :id AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            json_response(['ok' => false, 'msg' => '이미 삭제되었거나 존재하지 않는 신청입니다.'], 404);
        }

        // 논리 삭제 처리
        $stmt = $pdo->prepare("
            UPDATE " . TABLE_MT . "
            SET deleted_at = NOW(), delete_reason = :reason
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':reason' => $reason ?: null,
            ':id'     => $row['id'],
        ]);

        // 메일 알림 (옵션)
        notify_via_gas([
            'type'        => 'delete',
            'id'          => (int)$row['id'],
            'name'        => $row['name'],
            'contact'     => $row['contact'],
            'participants'=> (int)$row['participants'],
            'createdAt'   => $row['created_at'],
            'reason'      => $reason,
        ]);

        json_response(['ok' => true, 'msg' => '삭제가 완료되었습니다.']);
    }

    /* ------------------------------------------------------------------
     * 2-1) 신청 등록 (나머지 모든 POST는 여기서 처리)
     * -----------------------------------------------------------------*/
    // ✅ 여기까지 왔다는 것은 action이 'delete'가 아니라는 뜻 → 전부 등록으로 간주
    $name         = trim($data['name']        ?? '');
    $contact      = trim($data['contact']     ?? '');
    $participants = (int)($data['participants'] ?? 0);
    $nonMembers   = (int)($data['nonMembers']   ?? 0);
    $memberNames  = trim($data['memberNames'] ?? '');
    $course       = trim($data['course']      ?? '');
    $comment      = trim($data['comment']     ?? '');

    // 필수값 검증
    if ($name === '' || $contact === '' || $participants <= 0 || $course === '') {
        json_response(['ok' => false, 'msg' => '필수 항목이 누락되었습니다.'], 400);
    }

    // 연락처 형식 검사 (숫자 11자리)
    if (!preg_match('/^\d{11}$/', $contact)) {
        json_response(['ok' => false, 'msg' => '연락처는 숫자 11자리여야 합니다.'], 400);
    }

    // 현재까지 총 신청 인원 (삭제되지 않은 건만 집계)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(participants), 0) AS total
        FROM " . TABLE_MT . "
        WHERE deleted_at IS NULL
    ");
    $stmt->execute();
    $currentTotal = (int)$stmt->fetchColumn();

    $isWaiting = ($currentTotal + $participants > MAX_CAPACITY) ? 1 : 0;

    // created_at 값(이메일용) – DB에도 같이 기록
    $createdAt = date('Y-m-d H:i:s');

    // INSERT
    $stmt = $pdo->prepare("
        INSERT INTO " . TABLE_MT . "
        (created_at, name, contact, participants, non_members, member_names, course, comment, is_waiting)
        VALUES (:created_at, :name, :contact, :participants, :non_members, :member_names, :course, :comment, :is_waiting)
    ");
    $stmt->execute([
        ':created_at'  => $createdAt,
        ':name'        => $name,
        ':contact'     => $contact,
        ':participants'=> $participants,
        ':non_members' => $nonMembers,
        ':member_names'=> $memberNames ?: null,
        ':course'      => $course ?: null,
        ':comment'     => $comment ?: null,
        ':is_waiting'  => $isWaiting,
    ]);

    $insertId   = (int)$pdo->lastInsertId();
    $statusText = $isWaiting ? '대기접수' : '정상접수';

    // 메일 알림 (옵션)
    notify_via_gas([
        'type'        => 'register',
        'id'          => $insertId,
        'name'        => $name,
        'contact'     => $contact,
        'participants'=> $participants,
        'nonMembers'  => $nonMembers,
        'memberNames' => $memberNames,
        'course'      => $course,
        'comment'     => $comment,
        'status'      => $statusText,
        'createdAt'   => $createdAt,
    ]);

    json_response([
        'ok'        => true,
        'id'        => $insertId,
        'isWaiting' => (bool)$isWaiting,
        'msg'       => '신청이 완료되었습니다.',
    ]);

} catch (Throwable $e) {   // ✅ 모든 에러를 JSON으로
    json_response([
        'ok'  => false,
        'msg' => '서버 오류: ' . $e->getMessage()
    ], 500);
}
