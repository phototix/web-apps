-- Migration: Add data column to group_messages table
-- This migration adds a data column for additional message payloads

ALTER TABLE group_messages
ADD COLUMN data TEXT NULL AFTER content;

-- Migration complete message
SELECT 'Added data column to group_messages.' as message;
