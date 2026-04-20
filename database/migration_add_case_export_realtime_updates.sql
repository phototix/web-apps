-- Migration to extend realtime_updates update_type enum for case exports

ALTER TABLE realtime_updates
  MODIFY update_type enum(
    'qr_update',
    'session_status',
    'new_message',
    'group_update',
    'message_sent',
    'case_export_ready',
    'case_export_failed'
  ) COLLATE utf8mb4_unicode_ci NOT NULL;
