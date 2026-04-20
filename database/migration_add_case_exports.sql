-- Migration to add case exports tracking table

CREATE TABLE IF NOT EXISTS case_exports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  session_id BIGINT UNSIGNED NOT NULL,
  group_id VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  group_name VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  status ENUM('queued','processing','ready','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  zip_path VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  zip_filename VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  file_size BIGINT UNSIGNED DEFAULT NULL,
  error_message TEXT COLLATE utf8mb4_unicode_ci,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_user_status (user_id, status),
  KEY idx_group_status (group_id, status),
  KEY idx_expires_at (expires_at),
  CONSTRAINT case_exports_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
