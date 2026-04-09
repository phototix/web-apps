-- Migration to add parent_id column and invitation system
-- Also removes unique constraint on email to allow duplicates for different admins

USE erp_ezy_chat;

-- Add parent_id column to users table
ALTER TABLE users 
ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER id,
ADD CONSTRAINT fk_users_parent 
    FOREIGN KEY (parent_id) 
    REFERENCES users(id) 
    ON DELETE SET NULL;

-- Remove unique constraint on email to allow duplicates
ALTER TABLE users 
DROP INDEX email;

-- Create a new index on email (non-unique)
ALTER TABLE users 
ADD INDEX idx_email (email);

-- Create invitation_codes table for admin invitation links
CREATE TABLE IF NOT EXISTS invitation_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_by BIGINT UNSIGNED NULL,
    used_at TIMESTAMP NULL,
    max_uses INT NOT NULL DEFAULT 1,
    current_uses INT NOT NULL DEFAULT 0,
    INDEX idx_code (code),
    INDEX idx_created_by (created_by),
    INDEX idx_expires_at (expires_at),
    CONSTRAINT fk_invitation_created_by 
        FOREIGN KEY (created_by) 
        REFERENCES users(id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_invitation_used_by 
        FOREIGN KEY (used_by) 
        REFERENCES users(id) 
        ON DELETE SET NULL
);
