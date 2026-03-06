-- MCP tool call audit: one row per tools/call (hillmeet_*, etc.)

CREATE TABLE IF NOT EXISTS mcp_audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  tool VARCHAR(128) NOT NULL,
  request_id VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ok TINYINT(1) NOT NULL,
  error_code INT NULL,
  duration_ms INT UNSIGNED NULL,
  KEY idx_tenant_created (tenant_id, created_at),
  KEY idx_tool (tool),
  FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
