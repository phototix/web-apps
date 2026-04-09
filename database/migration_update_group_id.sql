-- Migration to update group_messages table to use string group_id
-- This migration changes the group_messages to use string group_id and session_id
-- and updates the foreign key to reference whatsapp_groups(session_id, group_id)

-- First, check if table exists and drop it (safe since it's empty)
DROP TABLE IF EXISTS group_messages;

-- Recreate the table with string group_id and session_id
CREATE TABLE group_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  group_id VARCHAR(255) NOT NULL,
  message_id VARCHAR(255) NOT NULL,
  sender_number VARCHAR(50) NOT NULL,
  sender_name VARCHAR(255) NULL,
  message_type ENUM('text', 'image', 'video', 'audio', 'document', 'location', 'contact', 'poll', 'other') DEFAULT 'text',
  content TEXT NULL,
  media_url VARCHAR(500) NULL,
  media_caption TEXT NULL,
  is_from_me BOOLEAN DEFAULT FALSE,
  timestamp BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id, group_id) REFERENCES whatsapp_groups(session_id, group_id) ON DELETE CASCADE,
  UNIQUE KEY unique_message (group_id, message_id),
  INDEX idx_group_timestamp (group_id, timestamp DESC),
  INDEX idx_timestamp (timestamp DESC),
  INDEX idx_session_group (session_id, group_id)
) ENGINE=InnoDB ROW_FORMAT=COMPRESSED DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration complete message
SELECT 'Group messages table updated to use string group_id with session_id successfully!' as message;