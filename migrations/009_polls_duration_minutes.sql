-- Event-level duration (minutes). Each time slot is start + duration; end is inferred.
ALTER TABLE polls ADD COLUMN duration_minutes INT UNSIGNED NOT NULL DEFAULT 60 AFTER timezone;
