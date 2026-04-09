-- Migration to add keywords and prompt columns to categories table
-- These fields will be used for AI categorization and prompts

ALTER TABLE categories 
ADD COLUMN keywords TEXT NULL AFTER description,
ADD COLUMN prompt TEXT NULL AFTER keywords;