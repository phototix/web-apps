-- Migration to cleanup test users and reset auto-increment
-- This will:
-- 1. Delete all users except brandon@kkbuddy.com
-- 2. Reset the auto-increment counter for users table
-- 3. Ensure brandon@kkbuddy.com has superadmin role

-- First, backup the current users (optional - for safety)
CREATE TABLE IF NOT EXISTS users_backup_before_cleanup LIKE users;
INSERT INTO users_backup_before_cleanup SELECT * FROM users;

-- Delete all users except brandon@kkbuddy.com
DELETE FROM users WHERE email != 'brandon@kkbuddy.com';

-- Make sure brandon@kkbuddy.com exists and has superadmin role
-- If the user doesn't exist, create it with a default password
INSERT INTO users (name, email, password_hash, role) 
VALUES ('Brandon', 'brandon@kkbuddy.com', '$2y$10$f9AT14HmdxhvtcsthXgCHOjUwgDksFAlpy40vbxvruWmlxEAbNMGW', 'superadmin')
ON DUPLICATE KEY UPDATE 
    name = 'Brandon',
    role = 'superadmin';

-- Reset auto-increment counter for users table
ALTER TABLE users AUTO_INCREMENT = 1;

-- Verify the cleanup
SELECT 'Cleanup completed. Current users:' as message;
SELECT id, name, email, role, created_at FROM users ORDER BY id;

-- Note: You may need to update the password hash with a real bcrypt hash
-- To generate a new password hash for 'password123', you can use:
-- php -r "echo password_hash('password123', PASSWORD_BCRYPT);"
-- Then update the INSERT statement above with the generated hash