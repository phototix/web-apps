-- Migration to add expiry column to users table for tier expiration management

-- Add expiry_date column to users table
ALTER TABLE users 
ADD COLUMN expiry_date DATE NULL AFTER max_sessions,
ADD INDEX idx_expiry_date (expiry_date);

-- Update existing users: set expiry_date to 1 year from creation for enterprise, 6 months for business, 1 month for basic
UPDATE users 
SET expiry_date = 
    CASE 
        WHEN tier = 'enterprise' THEN DATE_ADD(created_at, INTERVAL 1 YEAR)
        WHEN tier = 'business' THEN DATE_ADD(created_at, INTERVAL 6 MONTH)
        ELSE DATE_ADD(created_at, INTERVAL 1 MONTH)
    END
WHERE expiry_date IS NULL;