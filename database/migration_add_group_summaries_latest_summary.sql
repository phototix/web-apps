-- Migration to add latest summary to group summaries

ALTER TABLE whatsapp_group_summaries
  ADD COLUMN latest_summary TEXT NULL AFTER prompt;
