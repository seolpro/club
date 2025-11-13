<?php
// /www/mt/config.php

define('DB_HOST', 'localhost');     // 화면에 나온 DB 주소
define('DB_NAME', 'seolhopro');     // 보통 아이디와 동일, 나중에 phpMyAdmin에서 다르면 여기만 수정
define('DB_USER', 'seolhopro');     // DB 아이디
define('DB_PASS', 'ajou2130--');  // "DB 비밀번호 변경"에서 설정한 값

define('TABLE_MT', 'mt_applications');
define('MAX_CAPACITY', 40);
define('ADMIN_EMAIL', 'sktseolho@gmail.com');   // ← 여기만 변경

// ✅ GAS 메일 릴레이 WebApp URL
define('GAS_MAIL_URL', 'https://script.google.com/macros/s/AKfycbz4lfshq6Mf7dbNMQFrIyL6CZ4kiyB9EoN97G0vmowJxl_0FI3IOFeLzGja61xbff17/exec');

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
