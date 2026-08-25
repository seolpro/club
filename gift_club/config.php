<?php

declare(strict_types=1);

/**
 * gift_club 공통 설정
 * Cafe24 공통 DB 설정 파일(/private_config/db_common.php)을 우선 사용합니다.
 */
session_start();

date_default_timezone_set('Asia/Seoul');

$configCandidates = [
    dirname(__DIR__) . '/private_config/db_common.php',

    dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/private_config/db_common.php',

    ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/private_config/db_common.php',
];

$dbConfigFile = null;
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        $dbConfigFile = $candidate;
        break;
    }
}

if ($dbConfigFile === null) {
    http_response_code(500);
    exit('공통 DB 설정 파일을 찾을 수 없습니다. /private_config/db_common.php 위치를 확인해 주세요.');
}

$dbCfg = require $dbConfigFile;

if (!is_array($dbCfg)) {
    http_response_code(500);
    exit('DB 설정 파일 형식이 올바르지 않습니다.');
}

define('DB_HOST', (string)($dbCfg['host'] ?? 'localhost'));
define('DB_PORT', (int)($dbCfg['port'] ?? 3306));
define('DB_NAME', (string)($dbCfg['name'] ?? $dbCfg['dbname'] ?? ''));
define('DB_USER', (string)($dbCfg['user'] ?? ''));
define('DB_PASS', (string)($dbCfg['pass'] ?? $dbCfg['password'] ?? ''));
define('DB_CHARSET', (string)($dbCfg['charset'] ?? 'utf8mb4'));

define('APP_NAME', '동아리 명절선물 입금계좌 수집');
define('APP_DIR', 'gift_club');
define('TABLE_PREFIX', 'gift_');

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST
        . ';port=' . DB_PORT
        . ';dbname=' . DB_NAME
        . ';charset=' . DB_CHARSET;

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');

    if ($token === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(419);
        exit('요청이 만료되었거나 올바르지 않습니다. 이전 페이지로 돌아가 다시 시도해 주세요.');
    }
}

function normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function mask_account(string $account): string
{
    $digits = preg_replace('/\D+/', '', $account) ?? '';

    if (strlen($digits) <= 6) {
        return $digits;
    }

    return substr($digits, 0, 3)
        . str_repeat('*', max(2, strlen($digits) - 7))
        . substr($digits, -4);
}

function admin_required(): void
{
    if (empty($_SESSION['gift_admin_id'])) {
        redirect('login.php');
    }
}

function is_installed(): bool
{
    try {
        $stmt = db()->query("SHOW TABLES LIKE 'gift_clubs'");
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}
