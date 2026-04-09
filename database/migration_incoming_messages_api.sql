-- WhatsApp Incoming Messages API Database Migration
-- Run this file to add support for the incoming messages API

-- 1. Add missing columns to group_messages table
ALTER TABLE group_messages 
ADD COLUMN quoted_message_id VARCHAR(255) NULL AFTER media_caption,
ADD COLUMN media_type VARCHAR(100) NULL AFTER quoted_message_id,
ADD COLUMN media_size INT UNSIGNED NULL AFTER media_type;

-- 2. Update unique constraint to include session_id (to allow same message_id across different sessions)
ALTER TABLE group_messages 
DROP INDEX unique_message,
ADD UNIQUE KEY unique_message_session (session_id, group_id, message_id);

-- 3. Add index for quoted messages lookup
CREATE INDEX idx_quoted_message ON group_messages (quoted_message_id);

-- 4. Add index for media type filtering
CREATE INDEX idx_media_type ON group_messages (media_type);

-- Migration complete message
SELECT 'Group messages table updated for incoming messages API successfully!' as message;