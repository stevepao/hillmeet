-- Store poll secret encrypted so the owner can retrieve the share URL later (e.g. from get_poll / list_polls).
-- Only the server can decrypt; used to build the full ?secret=... link for the organizer.

ALTER TABLE polls ADD COLUMN secret_encrypted TEXT NULL AFTER secret_hash;
