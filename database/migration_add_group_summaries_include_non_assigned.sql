-- Migration to add include_non_assigned to group summaries

ALTER TABLE whatsapp_group_summaries
  ADD COLUMN include_non_assigned TINYINT(1) NOT NULL DEFAULT 0 AFTER prompt;
