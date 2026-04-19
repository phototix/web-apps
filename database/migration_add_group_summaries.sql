-- Migration to add group schedule summaries

CREATE TABLE IF NOT EXISTS whatsapp_group_summaries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  session_id BIGINT UNSIGNED NOT NULL,
  session_name VARCHAR(100) NOT NULL,
  group_id VARCHAR(255) NOT NULL,
  group_name VARCHAR(255) NOT NULL,
  frequency ENUM('daily', 'weekly', 'monthly') NOT NULL,
  summary_schedule TEXT NOT NULL,
  prompt TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (session_id) REFERENCES whatsapp_sessions(id) ON DELETE CASCADE,
  UNIQUE KEY unique_session_group (session_id, group_id),
  INDEX idx_user_session (user_id, session_id),
  INDEX idx_frequency (frequency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
