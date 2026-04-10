-- Migration: Change message_type from 'text' to 'chat'
-- This migration updates the ENUM definition and existing data

-- First, we need to alter the ENUM type to include 'chat' and remove 'text'
-- Note: MySQL doesn't support removing ENUM values directly, so we need to recreate the column

-- Step 1: Backup existing data (optional - run manually before migration)
-- CREATE TABLE group_messages_backup SELECT * FROM group_messages;

-- Step 2: Update existing records
UPDATE group_messages SET message_type = 'chat' WHERE message_type = 'text';

-- Step 3: If you need to completely change the ENUM (removing 'text'), run this:
-- ALTER TABLE group_messages 
-- MODIFY COLUMN message_type ENUM('chat', 'image', 'video', 'audio', 'document', 'location', 'contact', 'poll', 'other') DEFAULT 'chat';

-- Note: The ALTER TABLE statement above will fail if there are any records with message_type = 'text'
-- Make sure to run the UPDATE statement first to convert all 'text' to 'chat'

-- Verification query
SELECT 
    message_type,
    COUNT(*) as count
FROM group_messages 
GROUP BY message_type
ORDER BY count DESC;