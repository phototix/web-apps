-- Comprehensive user cleanup and reset
-- This script will:
-- 1. Backup current data (optional)
-- 2. Delete all data related to non-brandon users
-- 3. Ensure brandon@kkbuddy.com exists as superadmin
-- 4. Reset auto-increment counters

-- Step 1: Backup current data (optional - comment out if not needed)
CREATE TABLE IF NOT EXISTS users_backup_before_cleanup LIKE users;
INSERT INTO users_backup_before_cleanup SELECT * FROM users;

CREATE TABLE IF NOT EXISTS whatsapp_sessions_backup LIKE whatsapp_sessions;
INSERT INTO whatsapp_sessions_backup SELECT * FROM whatsapp_sessions;

CREATE TABLE IF NOT EXISTS categories_backup LIKE categories;
INSERT INTO categories_backup SELECT * FROM categories;

-- Step 2: Get the ID of brandon@kkbuddy.com (if exists)
SET @brandon_id = (SELECT id FROM users WHERE email = 'brandon@kkbuddy.com');

-- Step 3: Delete all data related to users except brandon
-- Note: Due to foreign key constraints with ON DELETE CASCADE,
-- deleting users will automatically delete related records in:
-- - whatsapp_sessions
-- - categories
-- - webhook_events_queue
-- - api_keys (created_by)
-- The invited_by and used_by columns will be set to NULL

-- First, handle any sessions that might have invited_by references
UPDATE users SET invited_by = NULL WHERE invited_by IS NOT NULL AND invited_by != @brandon_id;

-- Delete all users except brandon@kkbuddy.com
DELETE FROM users WHERE email != 'brandon@kkbuddy.com';

-- Step 4: Ensure brandon@kkbuddy.com exists with correct credentials
-- Default password: password123 (bcrypt hash)
INSERT INTO users (name, email, password_hash, role) 
VALUES ('Brandon', 'brandon@kkbuddy.com', '$2y$10$f9AT14HmdxhvtcsthXgCHOjUwgDksFAlpy40vbxvruWmlxEAbNMGW', 'superadmin')
ON DUPLICATE KEY UPDATE 
    name = 'Brandon',
    role = 'superadmin',
    password_hash = '$2y$10$f9AT14HmdxhvtcsthXgCHOjUwgDksFAlpy40vbxvruWmlxEAbNMGW';

-- Get the final brandon ID
SET @final_brandon_id = (SELECT id FROM users WHERE email = 'brandon@kkbuddy.com');

-- Step 5: Clean up any orphaned data (just in case)
-- Update any remaining invited_by references
UPDATE users SET invited_by = NULL WHERE invited_by IS NOT NULL AND invited_by != @final_brandon_id;

-- Update api_keys used_by references
UPDATE api_keys SET used_by = NULL WHERE used_by IS NOT NULL AND used_by != @final_brandon_id;

-- Step 6: Reset auto-increment counters
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE whatsapp_sessions AUTO_INCREMENT = 1;
ALTER TABLE whatsapp_groups AUTO_INCREMENT = 1;
ALTER TABLE group_messages AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE webhook_events_queue AUTO_INCREMENT = 1;
ALTER TABLE api_keys AUTO_INCREMENT = 1;

-- Step 7: Verification
SELECT '=== CLEANUP COMPLETED ===' as message;
SELECT 'Current users:' as message;
SELECT id, name, email, role, created_at FROM users ORDER BY id;

SELECT '=== TABLE COUNTS ===' as message;
SELECT 
    'users' as table_name, 
    COUNT(*) as record_count 
FROM users
UNION ALL
SELECT 
    'whatsapp_sessions', 
    COUNT(*) 
FROM whatsapp_sessions
UNION ALL
SELECT 
    'whatsapp_groups', 
    COUNT(*) 
FROM whatsapp_groups
UNION ALL
SELECT 
    'group_messages', 
    COUNT(*) 
FROM group_messages
UNION ALL
SELECT 
    'categories', 
    COUNT(*) 
FROM categories
UNION ALL
SELECT 
    'webhook_events_queue', 
    COUNT(*) 
FROM webhook_events_queue
UNION ALL
SELECT 
    'api_keys', 
    COUNT(*) 
FROM api_keys;

-- Step 8: Login instructions
SELECT '=== LOGIN INSTRUCTIONS ===' as message;
SELECT 'Email: brandon@kkbuddy.com' as instruction;
SELECT 'Password: password123' as instruction;
SELECT 'Role: superadmin' as instruction;