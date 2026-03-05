-- MCP session store (persist across HTTP requests)

CREATE TABLE IF NOT EXISTS mcp_sessions (
  id CHAR(36) NOT NULL PRIMARY KEY,
  data LONGTEXT NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
