-- Migration: Add caption column to group_messages table
-- This migration adds a caption column for media messages (image, video, document)

-- Add caption column
ALTER TABLE group_messages ADD COLUMN caption TEXT NULL AFTER media_caption;

-- Update existing image records to have correct message_type
UPDATE group_messages SET message_type = 'image' WHERE id IN (11, 12, 13, 14, 15, 16, 28, 29);

-- Verification query
SELECT 
    id,
    message_type,
    media_url,
    media_caption,
    caption
FROM group_messages 
WHERE id IN (11, 12, 13, 14, 15, 16, 28, 29)
ORDER BY id;