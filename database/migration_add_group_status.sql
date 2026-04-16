-- Add status column to whatsapp_groups if missing
SET @status_column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_groups'
      AND COLUMN_NAME = 'status'
);

SET @alter_stmt := IF(
    @status_column_exists = 0,
    'ALTER TABLE whatsapp_groups ADD COLUMN status ENUM(\'active\', \'archived\') DEFAULT \'active\'',
    'SELECT 1'
);

PREPARE status_stmt FROM @alter_stmt;
EXECUTE status_stmt;
DEALLOCATE PREPARE status_stmt;

-- Backfill status from is_archived when available
UPDATE whatsapp_groups
SET status = 'archived'
WHERE is_archived = 1;
