-- Poll invites: track when invite was accepted and by which user

ALTER TABLE poll_invites
  ADD COLUMN accepted_at DATETIME NULL AFTER sent_at,
  ADD COLUMN accepted_by_user_id INT UNSIGNED NULL AFTER accepted_at;

ALTER TABLE poll_invites
  ADD CONSTRAINT fk_invites_accepted_by
  FOREIGN KEY (accepted_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
