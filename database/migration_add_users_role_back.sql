-- Migration to add 'users' role back for team members
-- Superadmins and standalone admins have 'admin' role
-- Team members under admins have 'users' role

USE erp_ezy_chat;

-- Add 'users' role back to enum
ALTER TABLE users 
MODIFY COLUMN role ENUM('superadmin', 'admin', 'users') NOT NULL DEFAULT 'admin';
