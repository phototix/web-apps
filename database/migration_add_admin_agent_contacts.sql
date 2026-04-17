-- Migration to add agent contacts to admin users
ALTER TABLE users
ADD COLUMN agent_contacts TEXT NULL AFTER last_login_at;
