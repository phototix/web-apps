-- Migration to add categories table for message categorization
-- This table supports hierarchical categories (parent-child relationships)

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  color VARCHAR(7) DEFAULT '#6c757d', -- HEX color code for UI display
  parent_id BIGINT UNSIGNED NULL,
  sort_order INT DEFAULT 0,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_user_parent (user_id, parent_id),
  INDEX idx_user_active (user_id, is_active),
  INDEX idx_parent_sort (parent_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add category_id column to group_messages table
ALTER TABLE group_messages 
ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER media_caption,
ADD FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
ADD INDEX idx_category (category_id);

-- Add category_id column to whatsapp_groups table (optional, for group-level categorization)
ALTER TABLE whatsapp_groups 
ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER participant_count,
ADD FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
ADD INDEX idx_category (category_id);