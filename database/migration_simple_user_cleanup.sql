-- Simple user cleanup and reset
-- This script will:
-- 1. Delete all users except brandon@kkbuddy.com
-- 2. Ensure brandon@kkbuddy.com exists as superadmin
-- 3. Reset the users table auto-increment

-- Backup current users (optional)
CREATE TABLE IF NOT EXISTS users_backup_simple_cleanup LIKE users;
INSERT INTO users_backup_simple_cleanup SELECT * FROM users;

-- Delete all users except brandon@kkbuddy.com
DELETE FROM users WHERE email != 'brandon@kkbuddy.com';

-- Ensure brandon@kkbuddy.com exists with superadmin role
-- Password: password123
INSERT INTO users (name, email, password_hash, role) 
VALUES ('Brandon', 'brandon@kkbuddy.com', '$2y$10$f9AT14HmdxhvtcsthXgCHOjUwgDksFAlpy40vbxvruWmlxEAbNMGW', 'superadmin')
ON DUPLICATE KEY UPDATE 
    name = 'Brandon',
    role = 'superadmin';

-- Reset auto-increment counter
ALTER TABLE users AUTO_INCREMENT = 1;

-- Verify
SELECT 'Cleanup completed successfully!' as message;
SELECT 'Remaining user:' as message;
SELECT id, name, email, role FROM users;

-- Login info
SELECT 'Login with:' as message;
SELECT 'Email: brandon@kkbuddy.com' as credential;
SELECT 'Password: password123' as credential;