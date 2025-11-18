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

// JSON / form-data 모두 대응
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST; // multipart/form-data 등
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $data['action'] ?? ($_GET['action'] ?? '');

// ===== 업로드 경로 설정 =====
// 실제 서버 경로 및 URL은 환경에 맞게 조정
$UPLOAD_DIR = __DIR__ . '/uploads';       // 물리 경로 예: /home/hosting_users/ajoucu/www/mt/uploads
$UPLOAD_URL = '/mt/uploads';              // 웹에서 접근하는 URL 경로 (도메인 뒤에 붙는 경로)

// 디렉토리 없으면 생성
if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0775, true);
}

// 파일 업로드 공통 함수
function save_uploaded_file($fieldName, $UPLOAD_DIR, $UPLOAD_URL) {
    if (empty($_FILES[$fieldName]['name']) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $origName = $_FILES[$fieldName]['name'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext === '') $ext = 'dat';

    // 파일명 충돌 방지용 난수
    $newName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = rtrim($UPLOAD_DIR, '/') . '/' . $newName;

    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetPath)) {
        return null;
    }

    // URL 반환 (도메인 기준 상대경로)
    return rtrim($UPLOAD_URL, '/') . '/' . $newName;
}

try {
    $pdo = get_pdo();

    // -------------------- GET: 조회 계열 --------------------
    if ($method === 'GET') {
        switch ($action) {
            case 'list_members':
                $status = $_GET['status'] ?? ''; // active / withdrawn / ''(전체)
                $baseSql = "
                    SELECT id, employee_id, name, department, contact,
                           motivation, comment, status, joined_date, withdrawn_date
                    FROM " . TABLE_MT_MEMBERS . " 
                ";
                if ($status === 'active' || $status === 'withdrawn') {
                    $sql = $baseSql . " WHERE status = :status ORDER BY name ASC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':status' => $status]);
                } else {
                    $sql = $baseSql . " ORDER BY name ASC";
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
            //    프론트에서 'insert_ledger' 또는 'add_ledger' 둘 다 허용
            case 'add_ledger':
            case 'insert_ledger': {
                // JSON/form 모두에서 들어올 수 있음
                $trade_date = trim($data['trade_date'] ?? '');
                // "10,000" 이런 것도 대비해서 숫자만 추출
                $deposit    = (int)preg_replace('/\D+/', '', $data['deposit'] ?? '0');
                $withdrawal = (int)preg_replace('/\D+/', '', $data['withdrawal'] ?? '0');
                $desc       = trim($data['description'] ?? '');
                $note       = trim($data['note'] ?? '');

                if ($trade_date==='' || $desc==='') {
                    json_response(['ok'=>false,'msg'=>'거래일과 적요는 필수입니다.'],400);
                }

                // 증빙 파일(이미지/PDF) 업로드 처리
                // 이미 URL이 들어오는 구조(앱 등)도 고려해서, 파일 우선 -> 없으면 기존 URL 필드 사용
                $proof1 = null;
                $proof2 = null;

                // 1) 파일 업로드가 있으면 파일 저장
                $p1 = save_uploaded_file('proof1_file', $UPLOAD_DIR, $UPLOAD_URL);
                if ($p1) $proof1 = $p1;

                $p2 = save_uploaded_file('proof2_file', $UPLOAD_DIR, $UPLOAD_URL);
                if ($p2) $proof2 = $p2;

                // 2) 파일이 없고, 직접 URL이 넘어온 경우만 사용
                if (!$proof1 && !empty($data['proof1_url'])) {
                    $proof1 = trim($data['proof1_url']);
                }
                if (!$proof2 && !empty($data['proof2_url'])) {
                    $proof2 = trim($data['proof2_url']);
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

            // 5) CSV 업로드로 기존 전자장부 내역 일괄 추가
            case 'upload_ledger_csv': {
                if (empty($_FILES['ledger_csv']['name']) || $_FILES['ledger_csv']['error'] !== UPLOAD_ERR_OK) {
                    json_response(['ok'=>false,'msg'=>'CSV 파일이 업로드되지 않았습니다.'],400);
                }

                $tmpName = $_FILES['ledger_csv']['tmp_name'];
                $fh = fopen($tmpName, 'r');
                if (!$fh) {
                    json_response(['ok'=>false,'msg'=>'CSV 파일을 열 수 없습니다.'],500);
                }

                // 현재 마지막 잔액
                $stmt = $pdo->query("SELECT balance FROM " . TABLE_MT_LEDGER . " ORDER BY id DESC LIMIT 1");
                $balance = (int)($stmt->fetchColumn() ?: 0);

                // CSV 헤더 한 줄 건너뛴다고 가정 (첫 줄이 헤더)
                $first = true;
                $insertCount = 0;

                $insertStmt = $pdo->prepare("
                    INSERT INTO " . TABLE_MT_LEDGER . "
                    (trade_date, deposit, withdrawal, description, balance, note)
                    VALUES (:trade_date, :deposit, :withdrawal, :description, :balance, :note)
                ");

                while (($row = fgetcsv($fh)) !== false) {
                    // BOM 제거 등
                    if ($first) {
                        $first = false;
                        // 필요하다면 헤더를 분석해서 스킵
                        // 여기서는 단순 헤더 스킵용으로 사용
                        continue;
                    }

                    // 열 구조는 스프레드시트 CSV 형식에 맞게 조정
                    // 예: 0:날짜, 1:입금, 2:출금, 3:적요, 4:비고 (가정)
                    $trade_date = trim($row[0] ?? '');
                    $depStr     = trim($row[1] ?? '0');
                    $witStr     = trim($row[2] ?? '0');
                    $desc       = trim($row[3] ?? '');
                    $note       = trim($row[4] ?? '');

                    if ($trade_date === '' || $desc === '') {
                        // 필수 값이 없으면 스킵
                        continue;
                    }

                    // 숫자만 추출
                    $deposit    = (int)preg_replace('/\D+/', '', $depStr ?: '0');
                    $withdrawal = (int)preg_replace('/\D+/', '', $witStr ?: '0');

                    $balance += $deposit - $withdrawal;

                    $insertStmt->execute([
                        ':trade_date'=>$trade_date,
                        ':deposit'=>$deposit,
                        ':withdrawal'=>$withdrawal,
                        ':description'=>$desc,
                        ':balance'=>$balance,
                        ':note'=>$note ?: null,
                    ]);
                    $insertCount++;
                }
                fclose($fh);

                json_response(['ok'=>true,'msg'=>"CSV 업로드 완료 ({$insertCount}건 반영)"]);
            }

            // 6) 이메일 알림 수신자 추가
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

                    // ✅ 회원 CSV 일괄 업로드 (관리자)
        case 'upload_members_csv': {
            if (empty($_FILES['members_csv']['name']) || $_FILES['members_csv']['error'] !== UPLOAD_ERR_OK) {
                json_response(['ok'=>false,'msg'=>'CSV 파일이 업로드되지 않았습니다.'],400);
            }

            $tmpName = $_FILES['members_csv']['tmp_name'];
            $fh = fopen($tmpName, 'r');
            if (!$fh) {
                json_response(['ok'=>false,'msg'=>'CSV 파일을 열 수 없습니다.'],500);
            }

            $first = true;
            $insertCount = 0;
            $updateCount = 0;

            while (($row = fgetcsv($fh)) !== false) {
                // [0]사번, [1]이름, [2]부서명, [3]휴대전화번호, [4]가입일시
                if ($first) {
                    // 첫 줄 헤더(사번,이름,부서명...)는 건너뜀
                    $first = false;
                    continue;
                }

                $employeeId = trim($row[0] ?? '');
                $name       = trim($row[1] ?? '');
                $dept       = trim($row[2] ?? '');
                $phoneRaw   = trim($row[3] ?? '');
                $joinRaw    = trim($row[4] ?? '');

                if ($employeeId === '' || $name === '') {
                    // 핵심 정보 없으면 스킵
                    continue;
                }

                // 휴대전화번호: 숫자만 저장
                $contact   = preg_replace('/\D+/', '', $phoneRaw);
                $joinedDate = parse_join_date($joinRaw);

                // 기존 사번 여부 확인
                $stmt = $pdo->prepare("SELECT id FROM " . TABLE_MT_MEMBERS . " WHERE employee_id = :eid LIMIT 1");
                $stmt->execute([':eid'=>$employeeId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // 이미 있으면 UPDATE
                    $sql = "
                        UPDATE " . TABLE_MT_MEMBERS . "
                        SET name = :name,
                            department = :dept,
                            contact = :contact,
                            updated_at = NOW()
                    ";
                    $params = [
                        ':name'    => $name,
                        ':dept'    => $dept,
                        ':contact' => $contact,
                        ':id'      => $existing['id'],
                    ];

                    if ($joinedDate !== null) {
                        $sql .= ", joined_date = :joined_date";
                        $params[':joined_date'] = $joinedDate;
                    }

                    $sql .= " WHERE id = :id";
                    $stmt2 = $pdo->prepare($sql);
                    $stmt2->execute($params);
                    $updateCount++;
                } else {
                    // 없으면 신규 INSERT
                    $stmt2 = $pdo->prepare("
                        INSERT INTO " . TABLE_MT_MEMBERS . "
                        (employee_id, name, department, contact,
                         motivation, comment, status, joined_date)
                        VALUES (:eid, :name, :dept, :contact,
                                '', '', 'active', :joined_date)
                    ");
                    $stmt2->execute([
                        ':eid'        => $employeeId,
                        ':name'       => $name,
                        ':dept'       => $dept,
                        ':contact'    => $contact,
                        ':joined_date'=> $joinedDate,
                    ]);
                    $insertCount++;
                }
            }
            fclose($fh);

            $msg = "총 처리 건수: 신규 {$insertCount}건, 업데이트 {$updateCount}건";
            json_response(['ok'=>true,'msg'=>$msg]);
        }


            // 가입일 텍스트를 DATE 형식(Y-m-d)으로 변환
function parse_join_date($str) {
    $str = trim($str);
    if ($str === '') return null;

    // 1) 2024-03-01
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $str, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }

    // 2) 2024. 4. 6 오전 8:27:07  /  2024. 4. 6
    if (preg_match('/^(\d{4})\.\s*(\d{1,2})\.\s*(\d{1,2})/', $str, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }

    // 3) 2025.2월가입, 2025. 2월가입 → 해당 월 1일로 처리
    if (preg_match('/^(\d{4})\.\s*(\d{1,2})월/', $str, $m)) {
        return sprintf('%04d-%02d-01', $m[1], $m[2]);
    }

    // 4) 2024년 이전 → 2023-12-31 로 처리
    if (preg_match('/(20\d{2})년\s*이전/', $str, $m)) {
        $year = (int)$m[1] - 1;
        return sprintf('%04d-12-31', $year);
    }

    // 인식이 안 되면 NULL
    return null;
}

            default:
                json_response(['ok'=>false,'msg'=>'알 수 없는 POST action입니다.'],400);
        }
    }

    json_response(['ok'=>false,'msg'=>'지원하지 않는 메소드입니다.'],405);

} catch (Exception $e) {
    json_response(['ok'=>false,'msg'=>'서버 오류: '.$e->getMessage()],500);
}
