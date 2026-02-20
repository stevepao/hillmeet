-- Users: add email_normalized for case-insensitive uniqueness and lookups

-- Add column (nullable first for backfill)
ALTER TABLE users
  ADD COLUMN email_normalized VARCHAR(255) NULL AFTER email;

-- Backfill from email (trim + lowercase)
UPDATE users SET email_normalized = LOWER(TRIM(email)) WHERE email_normalized IS NULL;

-- Deduplicate: keep lowest id per email_normalized, update others to point to it (optional merge)
-- For simplicity we only enforce uniqueness: if duplicates exist, keep one and null the rest's email_normalized so we can fix manually
-- Actually: just make it NOT NULL and add unique. If there are duplicates the unique will fail and we fix manually.
ALTER TABLE users
  MODIFY COLUMN email_normalized VARCHAR(255) NOT NULL;

CREATE UNIQUE INDEX uq_email_normalized ON users (email_normalized);

-- Ensure email column is also normalized for display consistency (optional)
-- UPDATE users SET email = email_normalized WHERE email != email_normalized;
