-- Idempotency for MCP create poll: (tenant_id, idempotency_key) -> poll_id

CREATE TABLE IF NOT EXISTS tenant_poll_idempotency (
  tenant_id CHAR(36) NOT NULL,
  idempotency_key VARCHAR(255) NOT NULL,
  poll_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, idempotency_key),
  KEY idx_poll (poll_id),
  FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
  FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
