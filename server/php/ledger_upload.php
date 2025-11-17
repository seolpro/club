<?php
// /mt/ledger_upload.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'msg' => 'POST만 지원합니다.'], 405);
}

$mode     = $_POST['mode'] ?? 'single';     // single | csv
$admin_pw = $_POST['admin_pw'] ?? '';

if (!in_array($mode, ['single', 'csv'], true)) {
    json_response(['ok' => false, 'msg' => '잘못된 mode입니다.'], 400);
}

// 아주 간단한 비밀번호 체크 (admin_mt.html과 동일하게 맞추기)
if ($admin_pw !== '1004') {
    json_response(['ok' => false, 'msg' => '관리자 비밀번호가 올바르지 않습니다.'], 403);
}

// 업로드 폴더 설정
$uploadDir     = __DIR__ . '/uploads';
$uploadUrlBase = 'uploads'; // admin_mt.html 기준 상대경로 → /mt/uploads/...

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

/**
 * 업로드된 파일 저장 → DB에 넣을 상대 경로 반환
 * 실패/없음이면 null
 */
function save_uploaded_file(string $fieldName): ?string
{
    global $uploadDir, $uploadUrlBase;

    if (empty($_FILES[$fieldName]['name'])) {
        return null;
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp  = $_FILES[$fieldName]['tmp_name'];
    $name = basename($_FILES[$fieldName]['name']);

    $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $baseName = pathinfo($name, PATHINFO_FILENAME);
    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);

    $newFileName = date('Ymd_His') . '_' . $safeBase . ($ext ? '.' . $ext : '');
    $destPath    = $uploadDir . '/' . $newFileName;

    if (!move_uploaded_file($tmp, $destPath)) {
        return null;
    }

    // admin_mt.html 기준: src="uploads/파일명" 으로 호출
    return $uploadUrlBase . '/' . $newFileName;
}

try {
    $pdo = get_pdo();

    // =========================================================
    // 1) 단건 전자장부 + 파일 업로드 (mode = single)
    // =========================================================
    if ($mode === 'single') {
        $trade_date = trim($_POST['trade_date'] ?? '');
        $deposit    = trim($_POST['deposit'] ?? '0');
        $withdrawal = trim($_POST['withdrawal'] ?? '0');
        $desc       = trim($_POST['description'] ?? '');
        $note       = trim($_POST['note'] ?? '');

        // 숫자만 추출
        $deposit    = (int)preg_replace('/[^\d-]/', '', $deposit);
        $withdrawal = (int)preg_replace('/[^\d-]/', '', $withdrawal);

        if ($trade_date === '' || $desc === '') {
            json_response(['ok' => false, 'msg' => '거래일과 적요는 필수입니다.'], 400);
        }

        // 마지막 잔액 조회
        $stmt = $pdo->query("SELECT balance FROM " . TABLE_MT_LEDGER . " ORDER BY id DESC LIMIT 1");
        $lastBalance = (int)($stmt->fetchColumn() ?: 0);
        $newBalance  = $lastBalance + $deposit - $withdrawal;

        // 파일 저장
        $proof1_url = save_uploaded_file('proof1_file'); // 없으면 null
        $proof2_url = save_uploaded_file('proof2_file');

        $stmt = $pdo->prepare("
            INSERT INTO " . TABLE_MT_LEDGER . "
            (trade_date, deposit, withdrawal, description, balance,
             proof1_url, proof2_url, note)
            VALUES (:trade_date, :deposit, :withdrawal, :description,
                    :balance, :p1, :p2, :note)
        ");
        $stmt->execute([
            ':trade_date'  => $trade_date,
            ':deposit'     => $deposit,
            ':withdrawal'  => $withdrawal,
            ':description' => $desc,
            ':balance'     => $newBalance,
            ':p1'          => $proof1_url,
            ':p2'          => $proof2_url,
            ':note'        => $note ?: null,
        ]);

        json_response(['ok' => true, 'msg' => '전자장부에 등록되었습니다.']);
    }

    // =========================================================
    // 2) CSV 업로드 (기존 구글시트 자료 이관) (mode = csv)
    // =========================================================
    if ($mode === 'csv') {
        if (empty($_FILES['ledger_csv']['name']) || $_FILES['ledger_csv']['error'] !== UPLOAD_ERR_OK) {
            json_response(['ok' => false, 'msg' => 'CSV 파일 업로드 오류입니다.'], 400);
        }

        $tmpPath = $_FILES['ledger_csv']['tmp_name'];
        $fp      = fopen($tmpPath, 'r');
        if (!$fp) {
            json_response(['ok' => false, 'msg' => 'CSV 파일을 열 수 없습니다.'], 400);
        }

        // 현재 마지막 잔액
        $stmt = $pdo->query("SELECT balance FROM " . TABLE_MT_LEDGER . " ORDER BY id DESC LIMIT 1");
        $lastBalance = (int)($stmt->fetchColumn() ?: 0);

        $inserted = 0;
        $skipped  = 0;
        $rowNum   = 0;

        // 첫 줄 헤더라고 가정
        while (($row = fgetcsv($fp)) !== false) {
            $rowNum++;
            // UTF-8 BOM 제거
            if ($rowNum === 1 && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }

            // 1행은 헤더 → 건너뛰기
            if ($rowNum === 1) continue;

            // 기존 GAS 기준: [0]일자, [1]입금, [2]출금, [3]적요, [4]잔액, [5]증빙1, [6]증빙2, [7]비고
            $dateStr     = $row[0] ?? '';
            $depositStr  = $row[1] ?? '0';
            $withdrawStr = $row[2] ?? '0';
            $desc        = trim($row[3] ?? '');
            // $balanceCsv   = $row[4] ?? '';
            $proof1      = trim($row[5] ?? '');
            $proof2      = trim($row[6] ?? '');
            $note        = trim($row[7] ?? '');

            if ($desc === '' && $dateStr === '') {
                $skipped++;
                continue;
            }

            // 날짜 파싱 (구글시트에서 "2024-10-10" 형태로 맞춰서 CSV 내보내기 권장)
            $ts = strtotime($dateStr);
            if ($ts === false) {
                $skipped++;
                continue;
            }
            $trade_date = date('Y-m-d', $ts);

            $deposit    = (int)preg_replace('/[^\d-]/', '', $depositStr);
            $withdrawal = (int)preg_replace('/[^\d-]/', '', $withdrawStr);

            $lastBalance = $lastBalance + $deposit - $withdrawal;

            $stmt = $pdo->prepare("
                INSERT INTO " . TABLE_MT_LEDGER . "
                (trade_date, deposit, withdrawal, description, balance,
                 proof1_url, proof2_url, note)
                VALUES (:trade_date, :deposit, :withdrawal, :description,
                        :balance, :p1, :p2, :note)
            ");
            $stmt->execute([
                ':trade_date'  => $trade_date,
                ':deposit'     => $deposit,
                ':withdrawal'  => $withdrawal,
                ':description' => $desc,
                ':balance'     => $lastBalance,
                ':p1'          => $proof1 ?: null,  // 기존 시트에 링크가 있으면 그대로 URL로 사용
                ':p2'          => $proof2 ?: null,
                ':note'        => $note ?: null,
            ]);

            $inserted++;
        }

        fclose($fp);

        json_response([
            'ok'       => true,
            'msg'      => 'CSV 업로드 완료',
            'inserted' => $inserted,
            'skipped'  => $skipped
        ]);
    }

} catch (Exception $e) {
    json_response(['ok' => false, 'msg' => '서버 오류: ' . $e->getMessage()], 500);
}
