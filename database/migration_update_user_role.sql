-- Migration to update user role enum from ('superadmin', 'admin', 'users') to ('superadmin', 'admin')
-- and change default role from 'users' to 'admin'

USE erp_ezy_chat;

-- First, update any existing users with 'users' role to 'admin'
UPDATE users SET role = 'admin' WHERE role = 'users';

-- Now modify the column to remove 'users' from enum and set default to 'admin'
ALTER TABLE users 
MODIFY COLUMN role ENUM('superadmin', 'admin') NOT NULL DEFAULT 'admin';
