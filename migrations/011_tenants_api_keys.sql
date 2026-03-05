-- Tenants (MCP / API key scope) and API keys (hash-only, Bearer auth)

CREATE TABLE IF NOT EXISTS tenants (
  tenant_id CHAR(36) NOT NULL PRIMARY KEY,
  owner_user_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_owner (owner_user_id),
  FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_api_keys (
  key_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  key_prefix VARCHAR(32) NOT NULL,
  key_hash VARCHAR(255) NOT NULL,
  label VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  last_used_at DATETIME NULL,
  UNIQUE KEY uq_key_prefix (key_prefix),
  KEY idx_tenant (tenant_id),
  KEY idx_revoked (revoked_at),
  FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
