<?php
header('Content-Type: application/json; charset=utf-8');

function out($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $name = isset($_GET['name']) ? trim($_GET['name']) : '';
    if ($name === '') {
        out(array('ok' => false, 'message' => '회원명을 입력해 주세요.'), 400);
    }

    $configCandidates = array(
        dirname(__DIR__) . '/private_config/db_common.php',
        __DIR__ . '/../private_config/db_common.php',
        isset($_SERVER['DOCUMENT_ROOT']) ? dirname($_SERVER['DOCUMENT_ROOT']) . '/private_config/db_common.php' : ''
    );

    $configFile = null;
    foreach ($configCandidates as $candidate) {
        if ($candidate && is_file($candidate)) {
            $configFile = $candidate;
            break;
        }
    }

    if (!$configFile) {
        throw new Exception('공통 DB 설정 파일을 찾을 수 없습니다.');
    }

    $cfg = require $configFile;

    if (!is_array($cfg)) {
        throw new Exception('DB 설정 형식을 확인해 주세요.');
    }

    $host = isset($cfg['host']) ? $cfg['host'] : 'localhost';
    $port = isset($cfg['port']) ? $cfg['port'] : 3306;
    $dbName = isset($cfg['name']) ? $cfg['name'] : (isset($cfg['dbname']) ? $cfg['dbname'] : '');
    $dbUser = isset($cfg['user']) ? $cfg['user'] : '';
    $dbPass = isset($cfg['pass']) ? $cfg['pass'] : '';
    $charset = isset($cfg['charset']) ? $cfg['charset'] : 'utf8mb4';

    $pdo = new PDO(
        'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName . ';charset=' . $charset,
        $dbUser,
        $dbPass,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );

    $rows = array();

    // UNION을 사용하지 않고 각각 조회해 collation 문제 방지
    $stmt = $pdo->prepare("
        SELECT employee_id, name, department, contact
        FROM mt_members
        WHERE name = ?
        ORDER BY employee_id
    ");
    $stmt->execute(array($name));
    foreach ($stmt->fetchAll() as $r) {
        $r['member_type'] = '산악회';
        $rows[] = $r;
    }

    $stmt = $pdo->prepare("
        SELECT employee_id, name, department, contact
        FROM cul_members
        WHERE name = ?
        ORDER BY employee_id
    ");
    $stmt->execute(array($name));
    foreach ($stmt->fetchAll() as $r) {
        $r['member_type'] = '문화탐방';
        $rows[] = $r;
    }

    // 같은 사번이 양쪽 동아리에 있으면 한 행으로 통합
    $merged = array();
    foreach ($rows as $r) {
        $key = (string)$r['employee_id'];

        if (!isset($merged[$key])) {
            $merged[$key] = $r;
        } else {
            $types = array_unique(array(
                $merged[$key]['member_type'],
                $r['member_type']
            ));
            $merged[$key]['member_type'] = implode('+', $types);

            if (empty($merged[$key]['department']) && !empty($r['department'])) {
                $merged[$key]['department'] = $r['department'];
            }
            if (empty($merged[$key]['contact']) && !empty($r['contact'])) {
                $merged[$key]['contact'] = $r['contact'];
            }
        }
    }

    out(array(
        'ok' => true,
        'rows' => array_values($merged)
    ));

} catch (Exception $e) {
    error_log('[manual_member_lookup] ' . $e->getMessage());
    out(array(
        'ok' => false,
        'message' => '회원조회 중 오류가 발생했습니다.'
    ), 500);
}
