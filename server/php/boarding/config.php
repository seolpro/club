<?php
declare(strict_types=1);

// =====================================================
// 카페24 DB 정보에 맞게 수정하세요.
// =====================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'seolhopro');
define('DB_USER', 'seolhopro');
define('DB_PASS', 'ajou2130--');
define('DB_CHARSET', 'utf8mb4');

// 관리자 로그인 비밀번호
define('ADMIN_PASSWORD', '0911');

// 테이블 접두어: 필요 없으면 빈 문자열로 변경 가능
define('TB_PREFIX', 'boarding_');

function tb(string $name): string {
    return TB_PREFIX . $name;
}
