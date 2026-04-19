-- Migration to update summary_schedule column for group summaries

ALTER TABLE whatsapp_group_summaries
  MODIFY COLUMN summary_schedule TEXT NOT NULL;
