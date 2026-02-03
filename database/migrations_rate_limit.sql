-- ============================================================================
-- TABLE: rate_limit_log
-- ============================================================================
-- Logs API requests for rate limiting purposes
CREATE TABLE IF NOT EXISTS rate_limit_log (
    id SERIAL PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    path VARCHAR(255) NOT NULL,       
    timestamp INTEGER NOT NULL,       
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_rate_limit_ip_path_timestamp 
    ON rate_limit_log(ip, path, timestamp);

CREATE INDEX IF NOT EXISTS idx_rate_limit_timestamp 
    ON rate_limit_log(timestamp);
