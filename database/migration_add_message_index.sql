-- Migration to add composite index for faster group messages queries
-- This index optimizes queries that filter by session_id and group_id and order by timestamp

ALTER TABLE group_messages 
ADD INDEX idx_session_group_timestamp (session_id, group_id, timestamp DESC);

-- Note: If the index already exists, this will fail. You can check with:
-- SHOW INDEX FROM group_messages WHERE Key_name = 'idx_session_group_timestamp';