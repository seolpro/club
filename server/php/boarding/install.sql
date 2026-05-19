CREATE TABLE IF NOT EXISTS boarding_applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  leader_name VARCHAR(50) NOT NULL,
  leader_contact VARCHAR(30) NOT NULL,
  passenger_count INT UNSIGNED NOT NULL DEFAULT 0,
  comment TEXT NULL,
  consent_yn TINYINT(1) NOT NULL DEFAULT 1,
  ip_addr VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_created_at (created_at),
  KEY idx_leader_name (leader_name),
  KEY idx_leader_contact (leader_contact)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boarding_passengers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id INT UNSIGNED NOT NULL,
  passenger_name VARCHAR(50) NOT NULL,
  birth6 CHAR(6) NOT NULL,
  gender ENUM('남','여') NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_application_id (application_id),
  CONSTRAINT fk_boarding_passengers_application
    FOREIGN KEY (application_id) REFERENCES boarding_applications(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
