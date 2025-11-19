<?php
// /www/cul/club_api.php

// JSON 깨지지 않도록 화면 출력은 끄고, 에러는 로그에만 남기기
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

// config.php 가 잘 못 불렸을 경우 대비용 (디버그 메시지)
if (!function_exists('get_pdo')) {
    function get_pdo() {
        throw new Exception('get_pdo() 함수가 없습니다. config.php 위치/이름을 확인하세요.');
    }
}

// 필요하면 CORS
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Headers: Content-Type');
// header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function jres($arr, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

// JSON 또는 일반 POST 모두 처리
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action'])
    ? $_GET['action']
    : (isset($data['action']) ? $data['action'] : '');

try {
    $pdo = get_pdo();

    /* ============================================================
     *  GET 메소드 : 목록 조회
     * ==========================================================*/
    if ($method === 'GET') {

        /* ---- 1) 회원 목록 조회 -------------------------------- */
        if ($action === 'list_members') {
            $status = isset($_GET['status']) ? $_GET['status'] : '';

            $sql = "
                SELECT
                  id,
                  employee_id,
                  name,
                  department,
                  contact,
                  motivation,
                  comment,
                  status,
                  joined_date,
                  withdrawn_date
                FROM " . TABLE_CUL_MEMBERS . "
                WHERE 1
            ";
            $params = array();

            if ($status === 'active' || $status === 'withdrawn') {
                $sql .= " AND status = :status";
                $params[':status'] = $status;
            }

            $sql .= " ORDER BY id ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            jres(array('ok' => true, 'members' => $rows));
        }

        /* ---- 2) 전자장부 목록 조회 ---------------------------- */
        if ($action === 'list_ledger') {
            $sql = "
                SELECT
                  id,
                  DATE_FORMAT(trade_date, '%Y-%m-%d') AS trade_date,
                  deposit,
                  withdrawal,
                  description,
                  balance,
                  proof1_url,
                  proof2_url,
                  note
                  FROM " . TABLE_CUL_LEDGER . "
                  ORDER BY trade_date DESC, id DESC

            ";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll();

            jres(array('ok' => true, 'ledger' => $rows));
        }

        // 알 수 없는 action
        jres(array('ok' => false, 'msg' => '알 수 없는 GET action입니다.'), 400);
    }

    /* ============================================================
     *  POST 메소드 : 상태 변경 / 장부 입력 / 삭제
     * ==========================================================*/
    if ($method !== 'POST') {
        jres(array('ok' => false, 'msg' => '지원하지 않는 메소드입니다.'), 405);
    }

    // ✅ 0) action 값 읽기
$action = $data['action'] ?? '';

// ✅ 1) 문화탐방 동아리 "가입" 처리
if ($action === 'join') {

    $employeeId = trim($data['employeeId'] ?? '');
    $name       = trim($data['name']       ?? '');
    $job        = trim($data['job']        ?? '');
    $contact    = trim($data['contact']    ?? '');
    $motivation = trim($data['motivation'] ?? '');
    $comment    = trim($data['comment']    ?? '');

    // 필수값 체크
    if ($employeeId === '' || $name === '' || $job === '' || $contact === '') {
        jres(['ok' => false, 'msg' => '사번, 이름, 부서, 연락처는 필수입니다.'], 400);
    }

    // 연락처 숫자만 추출 후 길이 체크 (10~11자리 정도)
    $contactDigits = preg_replace('/\D+/', '', $contact);
    if (strlen($contactDigits) < 9 || strlen($contactDigits) > 11) {
        jres(['ok' => false, 'msg' => '연락처는 숫자 9~11자리로 입력해 주세요.'], 400);
    }

    // ✅ 중복 가입 체크 (사번 기준)
    $stmt = $pdo->prepare("
        SELECT id, name
        FROM " . TABLE_CUL_MEMBERS . "
        WHERE employee_id = :emp
        LIMIT 1
    ");
    $stmt->execute([':emp' => $employeeId]);
    $exists = $stmt->fetch();

    if ($exists) {
        jres([
            'ok'  => false,
            'msg' => '이미 가입된 회원입니다. (사번 ' . $employeeId . ')'
        ], 400);
    }

    // ✅ 새 회원 INSERT
    $stmt = $pdo->prepare("
        INSERT INTO " . TABLE_CUL_MEMBERS . "
          (created_at, updated_at,
           employee_id, name, department, contact,
           motivation, comment,
           status, joined_date)
        VALUES
          (NOW(), NOW(),
           :employee_id, :name, :department, :contact,
           :motivation, :comment,
           'active', CURDATE())
    ");
    $stmt->execute([
        ':employee_id' => $employeeId,
        ':name'        => $name,
        ':department'  => $job,
        ':contact'     => $contact,       // 원문 그대로 저장 (하이픈 포함 허용)
        ':motivation'  => $motivation ?: null,
        ':comment'     => $comment ?: null,
    ]);

    jres([
        'ok'  => true,
        'msg' => '가입 신청이 저장되었습니다.'
    ]);
}

    /* ---- 3) 회원 상태 변경 (탈퇴 / 복귀) ---------------------- */
    if ($action === 'update_member_status') {
        $id     = isset($data['id']) ? (int)$data['id'] : 0;
        $status = isset($data['status']) ? $data['status'] : 'active';
        $reason = isset($data['reason']) ? trim($data['reason']) : '';

        if ($id <= 0) {
            jres(array('ok' => false, 'msg' => '회원 ID가 없습니다.'), 400);
        }

        if ($status !== 'withdrawn') {
            // 활동 상태로 복귀
            $stmt = $pdo->prepare("
                UPDATE " . TABLE_CUL_MEMBERS . "
                SET status = 'active',
                    withdrawn_date = NULL,
                    withdrawn_reason = NULL,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(array(':id' => $id));

            jres(array('ok' => true, 'msg' => '활동 상태로 변경되었습니다.'));
        }

        // 탈퇴 처리
        $stmt = $pdo->prepare("
            UPDATE " . TABLE_CUL_MEMBERS . "
            SET status = 'withdrawn',
                withdrawn_date = CURDATE(),
                withdrawn_reason = :reason,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(array(
            ':id'     => $id,
            ':reason' => $reason ?: null,
        ));

        jres(array('ok' => true, 'msg' => '탈퇴 처리되었습니다.'));
    }

    /* ---- 4) 전자장부 단건 입력 ------------------------------- */
    if ($action === 'insert_ledger') {
        $trade_date  = isset($_POST['trade_date'])  ? $_POST['trade_date']  : '';
        $deposit     = isset($_POST['deposit'])     ? (int)$_POST['deposit'] : 0;
        $withdrawal  = isset($_POST['withdrawal'])  ? (int)$_POST['withdrawal'] : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $note        = isset($_POST['note'])        ? trim($_POST['note']) : '';

        if ($trade_date === '' || ($deposit === 0 && $withdrawal === 0)) {
            jres(array('ok' => false, 'msg' => '거래일과 입금/출금 중 하나는 반드시 있어야 합니다.'), 400);
        }

        // 마지막 잔액 가져오기 (가장 최근 행 기준)
        $stmt = $pdo->query("
            SELECT balance
            FROM " . TABLE_CUL_LEDGER . "
            ORDER BY trade_date DESC, id DESC
            LIMIT 1
        ");
        $last = $stmt->fetch();
        $prevBalance = $last ? (int)$last['balance'] : 0;
        $balance = $prevBalance + $deposit - $withdrawal;

        // 파일 업로드(선택사항)
        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $baseUrl = '/cul/uploads';

        $saveFile = function ($field) use ($uploadDir, $baseUrl) {
            if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                return null;
            }
            $name = basename($_FILES[$field]['name']);
            $ext  = pathinfo($name, PATHINFO_EXTENSION);
            $newName = date('Ymd_His') . '_' . uniqid() . ($ext ? '.'.$ext : '');
            $target = $uploadDir . '/' . $newName;
            if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
                return null;
            }
            return $baseUrl . '/' . $newName;
        };

        $proof1 = $saveFile('proof1_file');
        $proof2 = $saveFile('proof2_file');

        $stmt = $pdo->prepare("
            INSERT INTO " . TABLE_CUL_LEDGER . "
              (trade_date, created_at, deposit, withdrawal, description, balance, proof1_url, proof2_url, note)
            VALUES
              (:trade_date, NOW(), :deposit, :withdrawal, :description, :balance, :proof1_url, :proof2_url, :note)
        ");
        $stmt->execute(array(
            ':trade_date'  => $trade_date,
            ':deposit'     => $deposit,
            ':withdrawal'  => $withdrawal,
            ':description' => $description,
            ':balance'     => $balance,
            ':proof1_url'  => $proof1,
            ':proof2_url'  => $proof2,
            ':note'        => $note ?: null,
        ));

        jres(array('ok' => true, 'msg' => '전자장부가 저장되었습니다.'));
    }

    /* ---- 5) 전자장부 삭제 ------------------------------------ */
    if ($action === 'delete_ledger') {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) {
            jres(array('ok' => false, 'msg' => '삭제할 ID가 없습니다.'), 400);
        }

        $stmt = $pdo->prepare("
            DELETE FROM " . TABLE_CUL_LEDGER . "
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(array(':id' => $id));

        jres(array('ok' => true, 'msg' => '삭제되었습니다.'));
    }

    // 알 수 없는 POST action
    jres(array('ok' => false, 'msg' => '알 수 없는 POST action입니다.'), 400);

} catch (Throwable $e) {
    // 서버 에러는 로그에 남기고, 프론트에는 JSON으로 전달
    error_log('club_api error: ' . $e->getMessage());
    jres(array('ok' => false, 'msg' => '서버 오류: ' . $e->getMessage()), 500);
}
