-- Migration: Add sticker to message_type enum
ALTER TABLE group_messages
    MODIFY COLUMN message_type ENUM('chat', 'image', 'video', 'audio', 'document', 'sticker', 'location', 'contact', 'poll', 'other') DEFAULT 'chat';
