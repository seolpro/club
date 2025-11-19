<?php
// /www/cul/config.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'seolhopro');
define('DB_USER', 'seolhopro');
define('DB_PASS', 'ajou2130--');

// === 문화탐방 동아리 전용 테이블명 ===
define('TABLE_CUL_MEMBERS', 'cul_members');
define('TABLE_CUL_LEDGER',  'cul_ledger');
define('TABLE_CUL_EMAIL',   'cul_email_list');

// === 신청 테이블 (문화탐방 신청서) ===
define('TABLE_MT', 'cul_applications');
define('MAX_CAPACITY', 40);
define('ADMIN_EMAIL', 'sktseolho@gmail.com');

// ✅ GAS 메일 릴레이 WebApp URL (사용 안 하면 빈 문자열)
define('GAS_MAIL_URL', '');

// === 기존 코드 호환용 alias (필요할 때만) ===
if (!defined('TABLE_MT_MEMBERS')) define('TABLE_MT_MEMBERS', TABLE_CUL_MEMBERS);
if (!defined('TABLE_MT_LEDGER'))  define('TABLE_MT_LEDGER',  TABLE_CUL_LEDGER);
if (!defined('TABLE_MT_EMAIL'))   define('TABLE_MT_EMAIL',   TABLE_CUL_EMAIL);

// 최대 정원(옵션)
define('MT_MAX_MEMBERS', 9999);

function get_pdo() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
    }
    return $pdo;
}

function json_response($data, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
