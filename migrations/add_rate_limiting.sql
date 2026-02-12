-- Migration: Add centralized rate limiting table
-- Created: February 2026
-- Description: Database-backed rate limiting for all API endpoints

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(128) NOT NULL COMMENT 'IP address or user_id',
    endpoint_class VARCHAR(32) NOT NULL COMMENT 'Rate limit profile: PUBLIC, AUTHENTICATED, ADMIN, ROOT, GENERATION',
    window_start DATETIME NOT NULL COMMENT 'Request timestamp for sliding window',
    request_count INT NOT NULL DEFAULT 1 COMMENT 'Per-request counter (always 1 in current implementation)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_identifier (identifier),
    INDEX idx_endpoint_class (endpoint_class),
    INDEX idx_window_start (window_start),
    INDEX idx_composite (identifier, endpoint_class, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Centralized rate limiting storage for API security hardening';

-- Note on request_count column:
-- Current implementation inserts one row per request (request_count=1).
-- This column exists for future optimization where we might UPDATE and increment
-- instead of INSERT for each request within the same second.
