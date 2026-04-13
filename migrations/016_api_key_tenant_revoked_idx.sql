-- Add composite index on tenant_api_keys(tenant_id, revoked_at).
--
-- The revoke query in McpKeyService joins users → tenants → tenant_api_keys
-- and filters on both the join column (tenant_id) AND revoked_at IS NULL:
--
--   UPDATE tenant_api_keys k
--   INNER JOIN tenants t ON t.tenant_id = k.tenant_id
--   INNER JOIN users   u ON u.id = t.owner_user_id
--   SET k.revoked_at = CURRENT_TIMESTAMP
--   WHERE u.email_normalized = ?
--     AND k.revoked_at IS NULL
--
-- A composite (tenant_id, revoked_at) index lets MySQL satisfy the per-tenant
-- join AND the NULL filter in a single range scan, instead of scanning all rows
-- for a tenant and filtering revoked_at in a second pass.
--
-- The existing single-column idx_revoked(revoked_at) remains; it may still be
-- used by full-table housekeeping queries that scan purely by revocation date.

ALTER TABLE tenant_api_keys
  ADD KEY idx_tenant_revoked (tenant_id, revoked_at);
