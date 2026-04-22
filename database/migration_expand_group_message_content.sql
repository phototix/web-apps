-- Migration: Expand group_messages content column for larger payloads

ALTER TABLE group_messages
  MODIFY COLUMN content LONGTEXT COLLATE utf8mb4_unicode_ci NULL;

SELECT 'Expanded group_messages.content to LONGTEXT.' AS message;
