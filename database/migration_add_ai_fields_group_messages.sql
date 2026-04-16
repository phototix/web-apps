-- Add AI fields to group_messages table
-- Run this file to add ai_describe, amount, and ai_read columns

ALTER TABLE group_messages
ADD COLUMN ai_describe TEXT NULL AFTER caption,
ADD COLUMN amount DECIMAL(9,2) NULL AFTER ai_describe,
ADD COLUMN ai_read BOOLEAN NOT NULL DEFAULT FALSE AFTER amount;

-- Migration complete message
SELECT 'Added ai_describe, amount, and ai_read to group_messages.' as message;
