-- Migration to update user_invites table for new invitation system
-- Add max_uses, current_uses, and change expiry to 30 days

USE erp_ezy_chat;

-- Add new columns to user_invites table
ALTER TABLE user_invites 
ADD COLUMN max_uses INT NOT NULL DEFAULT 1 AFTER expires_at,
ADD COLUMN current_uses INT NOT NULL DEFAULT 0 AFTER max_uses;

-- Update default expiry to 30 days
ALTER TABLE user_invites 
ALTER COLUMN expires_at SET DEFAULT (NOW() + INTERVAL 30 DAY);

-- Update existing invites to have 30 day expiry
UPDATE user_invites 
SET expires_at = DATE_ADD(created_at, INTERVAL 30 DAY) 
WHERE expires_at < DATE_ADD(created_at, INTERVAL 30 DAY);
