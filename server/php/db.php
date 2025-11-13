<?php
// /www/mt/db.php
require_once __DIR__ . '/config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h3>DB 연결 & 테이블 생성 테스트</h3>";

try {
    $pdo = get_pdo();
    echo "<p>✅ DB 연결 성공</p>";

    $sql = "
    CREATE TABLE IF NOT EXISTS `" . TABLE_MT . "` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `name` VARCHAR(50) NOT NULL,
      `contact` VARCHAR(20) NOT NULL,
      `participants` TINYINT UNSIGNED NOT NULL,
      `non_members` TINYINT UNSIGNED NOT NULL DEFAULT 0,
      `member_names` VARCHAR(255) DEFAULT NULL,
      `course` VARCHAR(255) DEFAULT NULL,
      `comment` VARCHAR(255) DEFAULT NULL,
      `is_waiting` TINYINT(1) NOT NULL DEFAULT 0,
      `delete_reason` VARCHAR(255) DEFAULT NULL,
      `deleted_at` DATETIME DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);

    echo "<p>✅ 테이블 <b>" . TABLE_MT . "</b> 생성(또는 이미 존재)</p>";
    echo "<p>이제 <b>index.html</b> 에서 신청 테스트를 해보세요.</p>";

} catch (Exception $e) {
    echo "<p>❌ 오류 발생</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
