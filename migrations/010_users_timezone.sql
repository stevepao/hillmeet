-- Optional per-user timezone for displaying times in lock notifications
ALTER TABLE users ADD COLUMN timezone VARCHAR(64) NULL AFTER avatar_url;
