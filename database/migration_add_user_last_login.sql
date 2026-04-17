-- Migration to add last login timestamp to users table
ALTER TABLE users
ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
ADD INDEX idx_last_login_at (last_login_at);
