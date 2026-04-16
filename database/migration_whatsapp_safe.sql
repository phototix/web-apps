-- WhatsApp Integration Safe Migration (idempotent)

SET @db = DATABASE();

DELIMITER //
CREATE PROCEDURE migration_whatsapp_safe()
BEGIN
    DECLARE col_count INT DEFAULT 0;
    DECLARE fk_count INT DEFAULT 0;

    -- Add columns to users table if missing
    SELECT COUNT(*) INTO col_count
    FROM information_schema.columns
    WHERE table_schema = @db AND table_name = 'users' AND column_name = 'tier';
    IF col_count = 0 THEN
        SET @sql = 'ALTER TABLE users ADD COLUMN tier ENUM(''basic'', ''business'', ''enterprise'') DEFAULT ''basic''';
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;

    SELECT COUNT(*) INTO col_count
    FROM information_schema.columns
    WHERE table_schema = @db AND table_name = 'users' AND column_name = 'invited_by';
    IF col_count = 0 THEN
        SET @sql = 'ALTER TABLE users ADD COLUMN invited_by BIGINT UNSIGNED NULL';
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;

    SELECT COUNT(*) INTO col_count
    FROM information_schema.columns
    WHERE table_schema = @db AND table_name = 'users' AND column_name = 'max_sessions';
    IF col_count = 0 THEN
        SET @sql = 'ALTER TABLE users ADD COLUMN max_sessions INT DEFAULT 1';
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;

    SELECT COUNT(*) INTO col_count
    FROM information_schema.columns
    WHERE table_schema = @db AND table_name = 'users' AND column_name = 'settings';
    IF col_count = 0 THEN
        SET @sql = 'ALTER TABLE users ADD COLUMN settings JSON DEFAULT NULL';
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;

    -- Add foreign key for invited_by if missing
    SELECT COUNT(*) INTO fk_count
    FROM information_schema.key_column_usage
    WHERE table_schema = @db
      AND table_name = 'users'
      AND column_name = 'invited_by'
      AND referenced_table_name = 'users';
    IF fk_count = 0 THEN
        SET @sql = 'ALTER TABLE users ADD CONSTRAINT fk_users_invited_by FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL';
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;

    -- Create WhatsApp sessions table
    CREATE TABLE IF NOT EXISTS whatsapp_sessions (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT UNSIGNED NOT NULL,
      session_name VARCHAR(100) NOT NULL,
      api_key VARCHAR(255) NOT NULL,
      endpoint_url VARCHAR(255) NOT NULL,
      status ENUM('pending', 'authenticating', 'active', 'inactive', 'error') DEFAULT 'pending',
      qr_code TEXT NULL,
      last_qr_update TIMESTAMP NULL,
      webhook_url VARCHAR(255) NULL,
      webhook_secret VARCHAR(255) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      UNIQUE KEY unique_user_session (user_id, session_name),
      INDEX idx_status (status),
      INDEX idx_user_status (user_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Create WhatsApp groups table
    CREATE TABLE IF NOT EXISTS whatsapp_groups (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      session_id BIGINT UNSIGNED NOT NULL,
      group_id VARCHAR(255) NOT NULL,
      name VARCHAR(255) NOT NULL,
      description TEXT NULL,
      participant_count INT DEFAULT 0,
      status ENUM('active', 'archived') DEFAULT 'active',
      is_archived BOOLEAN DEFAULT FALSE,
      last_message_timestamp BIGINT UNSIGNED NULL,
      last_message_preview TEXT NULL,
      unread_count INT DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (session_id) REFERENCES whatsapp_sessions(id) ON DELETE CASCADE,
      UNIQUE KEY unique_session_group (session_id, group_id),
      INDEX idx_session_updated (session_id, updated_at DESC),
      INDEX idx_last_message (session_id, last_message_timestamp DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Create group messages table
    CREATE TABLE IF NOT EXISTS group_messages (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      session_id BIGINT UNSIGNED NOT NULL,
      group_id VARCHAR(255) NOT NULL,
      message_id VARCHAR(255) NOT NULL,
      sender_number VARCHAR(50) NOT NULL,
      sender_name VARCHAR(255) NULL,
      message_type ENUM('chat', 'image', 'video', 'audio', 'document', 'sticker', 'location', 'contact', 'poll', 'other') DEFAULT 'chat',
      content TEXT NULL,
      media_url VARCHAR(500) NULL,
      media_caption TEXT NULL,
      caption TEXT NULL,
      is_from_me BOOLEAN DEFAULT FALSE,
      timestamp BIGINT UNSIGNED NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (session_id, group_id) REFERENCES whatsapp_groups(session_id, group_id) ON DELETE CASCADE,
      UNIQUE KEY unique_message (group_id, message_id),
      INDEX idx_group_timestamp (group_id, timestamp DESC),
      INDEX idx_timestamp (timestamp DESC),
      INDEX idx_session_group (session_id, group_id)
    ) ENGINE=InnoDB ROW_FORMAT=COMPRESSED DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Create webhook events queue table
    CREATE TABLE IF NOT EXISTS webhook_events_queue (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      session_id BIGINT UNSIGNED NOT NULL,
      event_id VARCHAR(100) NOT NULL,
      event_type VARCHAR(50) NOT NULL,
      payload JSON NOT NULL,
      attempts INT DEFAULT 0,
      max_attempts INT DEFAULT 3,
      status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
      next_retry_at TIMESTAMP NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      processed_at TIMESTAMP NULL,
      FOREIGN KEY (session_id) REFERENCES whatsapp_sessions(id) ON DELETE CASCADE,
      UNIQUE KEY unique_event (event_id),
      INDEX idx_status_next_retry (status, next_retry_at),
      INDEX idx_session_status (session_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Create real-time updates table
    CREATE TABLE IF NOT EXISTS realtime_updates (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT UNSIGNED NOT NULL,
      update_type ENUM('qr_update', 'session_status', 'new_message', 'group_update', 'message_sent') NOT NULL,
      entity_id VARCHAR(255) NOT NULL,
      data JSON NOT NULL,
      is_read BOOLEAN DEFAULT FALSE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 1 HOUR),
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      INDEX idx_user_unread (user_id, is_read, created_at DESC),
      INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Create user invites table
    CREATE TABLE IF NOT EXISTS user_invites (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      code VARCHAR(32) NOT NULL,
      created_by BIGINT UNSIGNED NOT NULL,
      tier ENUM('basic', 'business', 'enterprise') DEFAULT 'basic',
      used_by BIGINT UNSIGNED NULL,
      used_at TIMESTAMP NULL,
      expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 7 DAY),
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL,
      UNIQUE KEY unique_code (code),
      INDEX idx_expires (expires_at),
      INDEX idx_used (used_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Backfill tier and max_sessions
    UPDATE users SET tier = 'basic' WHERE tier IS NULL;
    UPDATE users SET max_sessions = 1 WHERE tier = 'basic';
    UPDATE users SET max_sessions = 3 WHERE tier = 'business';
    UPDATE users SET max_sessions = 5 WHERE tier = 'enterprise';

    -- Create default admin user if none exists
    INSERT INTO users (name, email, password_hash, role, tier, max_sessions)
    SELECT 'System Admin', 'admin@erp.ezy.chat', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 'enterprise', 5
    WHERE NOT EXISTS (SELECT 1 FROM users WHERE role IN ('superadmin', 'admin') LIMIT 1);
END//
DELIMITER ;

CALL migration_whatsapp_safe();
DROP PROCEDURE migration_whatsapp_safe;

SELECT 'WhatsApp safe migration completed successfully!' as message;
