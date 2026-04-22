-- Migration: Add whatsapp_message_polls table

CREATE TABLE IF NOT EXISTS whatsapp_message_polls (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  group_id VARCHAR(255) NOT NULL,
  file_message_id BIGINT UNSIGNED NOT NULL,
  poll_message_id VARCHAR(255) NOT NULL,
  poll_question VARCHAR(255) NOT NULL,
  poll_options JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_poll_message (poll_message_id),
  KEY idx_file_message (file_message_id),
  KEY idx_session_group (session_id, group_id),
  CONSTRAINT whatsapp_message_polls_ibfk_1 FOREIGN KEY (file_message_id) REFERENCES group_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Added whatsapp_message_polls table.' as message;
