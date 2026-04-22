-- Migration to add file handling category assignment setting
ALTER TABLE users
ADD COLUMN file_handling_category_assignment INT NOT NULL DEFAULT 1;
