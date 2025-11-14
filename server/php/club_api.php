<?php
// /mt/club_api.php
require_once __DIR__ . '/config.php';

// CORS 필요하면 열어두기 (같은 도메인이면 생략 가능)
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Headers: Content-Type');
// header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 입력 파싱 (JSON/폼 둘 다 대응)
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$method = $_SERVER['REQUEST_METHOD'];
$action = $data['action'] ?? ($_GET['action'] ?? '');

try {
    $pdo = get_pdo();

    // -------------------- GET: 조회 계열 --------------------
    if ($method === 'GET') {
        switch ($action) {
            case 'list_members':
                $status = $_GET['status'] ?? ''; // active / withdrawn / ''(전체)
                $sql = "SELECT id, employee_id, name, department, contact,
                               motivation, comment, status, joined_date, withdrawn_date
                        FROM " . TABLE_MT_MEMBERS . " ORDER BY name ASC";
                if ($status === 'active' || $status === 'withdrawn') {
                    $sql = "SELECT id, employee_id, name, department, contact,
                                   motivation, comment, status, joined_date, withdrawn_date
                            FROM " . TABLE_MT_MEMBERS . "
                            WHERE status = :status
                            ORDER BY name ASC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':status' => $status]);
                } else {
                    $stmt = $pdo->query($sql);
                }
                $rows = $stmt->fetchAll();
                json_response(['ok'=>true,'members'=>$rows]);
                break;

            case 'list_ledger':
                $stmt = $pdo->query("
                    SELECT id, trade_date, deposit, withdrawal, description,
                           balance, proof1_url, proof2_url, note
                    FROM " . TABLE_MT_LEDGER . "
                    ORDER BY trade_date DESC, id DESC
                    LIMIT 100
                ");
                $rows = $stmt->fetchAll();
                json_response(['ok'=>true,'ledger'=>$rows]);
                break;

            case 'list_email':
                $stmt = $pdo->query("
                    SELECT id, name, email, is_active, created_at
                    FROM " . TABLE_MT_EMAIL . "
                    ORDER BY created_at DESC
                ");
                json_response(['ok'=>true,'emails'=>$stmt->fetchAll()]);
                break;

            default:
                json_response(['ok'=>false,'msg'=>'알 수 없는 GET action입니다.'],400);
        }
    }

    // -------------------- POST: 쓰기/수정 계열 --------------------
    if ($method === 'POST') {
        switch ($action) {

            // 1) 가입 신청 처리
            case 'join': {
                $employeeId = trim($data['employeeId'] ?? '');
                $name       = trim($data['name'] ?? '');
                $job        = trim($data['job'] ?? '');
                $contact    = preg_replace('/\D+/','', $data['contact'] ?? '');
                $motivation = trim($data['motivation'] ?? '');
                $comment    = trim($data['comment'] ?? '');

                if ($employeeId==='' || $name==='' || $job==='' || $contact==='') {
                    json_response(['ok'=>false,'msg'=>'필수 항목이 누락되었습니다.'],400);
                }

                // 이미 가입된 사번인지 확인
                $stmt = $pdo->prepare("SELECT id, status FROM " . TABLE_MT_MEMBERS . " WHERE employee_id = :eid LIMIT 1");
                $stmt->execute([':eid'=>$employeeId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    if ($existing['status']==='active') {
                        json_response(['ok'=>false,'msg'=>'이미 가입된 회원입니다.'],409);
                    } else {
                        // 탈퇴 -> 재가입이면 상태 갱신
                        $stmt = $pdo->prepare("
                            UPDATE " . TABLE_MT_MEMBERS . "
                            SET name=:name, department=:dept, contact=:contact,
                                motivation=:motivation, comment=:comment,
                                status='active', joined_date=CURDATE(),
                                withdrawn_date=NULL, withdrawn_reason=NULL,
                                updated_at=NOW()
                            WHERE id=:id
                        ");
                        $stmt->execute([
                            ':name'=>$name,
                            ':dept'=>$job,
                            ':contact'=>$contact,
                            ':motivation'=>$motivation,
                            ':comment'=>$comment,
                            ':id'=>$existing['id'],
                        ]);
                        json_response(['ok'=>true,'msg'=>'탈퇴 회원이 재가입 처리되었습니다.','memberId'=>$existing['id']]);
                    }
                }

                // 신규 가입
                $stmt = $pdo->prepare("
                    INSERT INTO " . TABLE_MT_MEMBERS . "
                    (employee_id, name, department, contact,
                     motivation, comment, status, joined_date)
                    VALUES (:eid, :name, :dept, :contact, :motivation, :comment, 'active', CURDATE())
                ");
                $stmt->execute([
                    ':eid'=>$employeeId,
                    ':name'=>$name,
                    ':dept'=>$job,
                    ':contact'=>$contact,
                    ':motivation'=>$motivation,
                    ':comment'=>$comment,
                ]);
                $id = (int)$pdo->lastInsertId();

                json_response(['ok'=>true,'msg'=>'가입 신청이 저장되었습니다.','memberId'=>$id]);
            }

            // 2) 회원 상태 변경 (관리자: 탈퇴 처리 등)
            case 'update_member_status': {
                $id      = (int)($data['id'] ?? 0);
                $status  = $data['status'] ?? 'active'; // 'active' or 'withdrawn'
                $reason  = trim($data['reason'] ?? '');

                if ($id<=0 || !in_array($status,['active','withdrawn'],true)) {
                    json_response(['ok'=>false,'msg'=>'잘못된 요청입니다.'],400);
                }

                if ($status === 'withdrawn') {
                    $stmt = $pdo->prepare("
                        UPDATE " . TABLE_MT_MEMBERS . "
                        SET status='withdrawn', withdrawn_date=CURDATE(),
                            withdrawn_reason=:reason, updated_at=NOW()
                        WHERE id=:id
                    ");
                    $stmt->execute([':reason'=>$reason ?: null, ':id'=>$id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE " . TABLE_MT_MEMBERS . "
                        SET status='active', withdrawn_date=NULL,
                            withdrawn_reason=NULL, updated_at=NOW()
                        WHERE id=:id
                    ");
                    $stmt->execute([':id'=>$id]);
                }

                json_response(['ok'=>true,'msg'=>'회원 상태가 변경되었습니다.']);
            }

            // 3) 전자장부 입·출내역 추가
            case 'add_ledger': {
                $trade_date = trim($data['trade_date'] ?? '');
                $deposit    = (int)($data['deposit'] ?? 0);
                $withdrawal = (int)($data['withdrawal'] ?? 0);
                $desc       = trim($data['description'] ?? '');
                $note       = trim($data['note'] ?? '');
                $proof1     = trim($data['proof1_url'] ?? '');
                $proof2     = trim($data['proof2_url'] ?? '');

                if ($trade_date==='' || $desc==='') {
                    json_response(['ok'=>false,'msg'=>'거래일과 적요는 필수입니다.'],400);
                }

                // 현재 마지막 잔액 조회
                $stmt = $pdo->query("SELECT balance FROM " . TABLE_MT_LEDGER . " ORDER BY id DESC LIMIT 1");
                $lastBalance = (int)($stmt->fetchColumn() ?: 0);
                $newBalance  = $lastBalance + $deposit - $withdrawal;

                $stmt = $pdo->prepare("
                    INSERT INTO " . TABLE_MT_LEDGER . "
                    (trade_date, deposit, withdrawal, description, balance,
                     proof1_url, proof2_url, note)
                    VALUES (:trade_date, :deposit, :withdrawal, :description,
                            :balance, :p1, :p2, :note)
                ");
                $stmt->execute([
                    ':trade_date'=>$trade_date,
                    ':deposit'=>$deposit,
                    ':withdrawal'=>$withdrawal,
                    ':description'=>$desc,
                    ':balance'=>$newBalance,
                    ':p1'=>$proof1 ?: null,
                    ':p2'=>$proof2 ?: null,
                    ':note'=>$note ?: null,
                ]);

                json_response(['ok'=>true,'msg'=>'전자장부에 등록되었습니다.']);
            }

            // 4) 전자장부 항목 삭제 (관리자)
            case 'delete_ledger': {
                $id = (int)($data['id'] ?? 0);
                if ($id<=0) json_response(['ok'=>false,'msg'=>'잘못된 ID입니다.'],400);

                $stmt = $pdo->prepare("DELETE FROM " . TABLE_MT_LEDGER . " WHERE id=:id");
                $stmt->execute([':id'=>$id]);

                json_response(['ok'=>true,'msg'=>'입출내역이 삭제되었습니다.\n(잔액은 직접 확인해 주세요)']);
            }

            // 5) 이메일 알림 수신자 추가
            case 'add_email': {
                $name  = trim($data['name'] ?? '');
                $email = trim($data['email'] ?? '');

                if ($name==='' || $email==='') {
                    json_response(['ok'=>false,'msg'=>'이름과 이메일은 필수입니다.'],400);
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    json_response(['ok'=>false,'msg'=>'이메일 형식이 올바르지 않습니다.'],400);
                }

                // 중복 체크
                $stmt = $pdo->prepare("SELECT id FROM " . TABLE_MT_EMAIL . " WHERE email=:email LIMIT 1");
                $stmt->execute([':email'=>$email]);
                if ($stmt->fetch()) {
                    json_response(['ok'=>false,'msg'=>'이미 등록된 이메일입니다.'],409);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO " . TABLE_MT_EMAIL . " (name, email, is_active)
                    VALUES (:name, :email, 1)
                ");
                $stmt->execute([':name'=>$name, ':email'=>$email]);

                json_response(['ok'=>true,'msg'=>'이메일 알림 수신자로 등록되었습니다.']);
            }

            default:
                json_response(['ok'=>false,'msg'=>'알 수 없는 POST action입니다.'],400);
        }
    }

    json_response(['ok'=>false,'msg'=>'지원하지 않는 메소드입니다.'],405);

} catch (Exception $e) {
    json_response(['ok'=>false,'msg'=>'서버 오류: '.$e->getMessage()],500);
}
