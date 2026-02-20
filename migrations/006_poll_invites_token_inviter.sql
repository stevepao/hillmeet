-- Poll invites: per-invite token (hashed) and inviter; enforce unique poll+email

-- Remove duplicates (keep lowest id per poll_id, email) so UNIQUE can be added
DELETE p1 FROM poll_invites p1
INNER JOIN poll_invites p2 ON p1.poll_id = p2.poll_id AND p1.email = p2.email AND p1.id > p2.id;

ALTER TABLE poll_invites
  ADD COLUMN token_hash VARCHAR(64) NULL AFTER email,
  ADD COLUMN invited_by_user_id INT UNSIGNED NULL AFTER token_hash,
  ADD UNIQUE KEY unique_poll_email (poll_id, email);

ALTER TABLE poll_invites
  ADD CONSTRAINT fk_invites_invited_by
  FOREIGN KEY (invited_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
