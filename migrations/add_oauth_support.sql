-- Google OAuth 2.0 Support Migration
-- Date: January 17, 2026
-- Description: Add auth_providers and auth_events tables for OAuth/MFA support

-- Create auth_providers table
CREATE TABLE IF NOT EXISTS auth_providers (
    auth_provider_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider_type ENUM('local', 'google') NOT NULL,
    provider_user_id VARCHAR(255) NULL COMMENT 'Google sub claim or other provider ID',
    provider_email VARCHAR(255) NULL COMMENT 'Email from OAuth provider',
    is_primary BOOLEAN DEFAULT FALSE COMMENT 'Primary authentication method',
    linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_provider_user (provider_type, provider_user_id),
    INDEX idx_user_provider (user_id, provider_type),
    INDEX idx_provider_lookup (provider_type, provider_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create auth_events table for audit logging
CREATE TABLE IF NOT EXISTS auth_events (
    event_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL if login failed',
    event_type ENUM(
        'login_success', 
        'login_failed', 
        'oauth_link', 
        'oauth_unlink',
        'oauth_login',
        'password_changed'
    ) NOT NULL,
    auth_method ENUM('local', 'google') NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    metadata JSON NULL COMMENT 'Additional context (error messages, etc.)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_events (user_id, created_at),
    INDEX idx_event_type (event_type, created_at),
    INDEX idx_ip_address (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modify users table to support OAuth-only accounts
ALTER TABLE users 
    MODIFY COLUMN password_hash VARCHAR(255) NULL COMMENT 'NULL for OAuth-only accounts',
    ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER email,
    ADD COLUMN email_verification_token VARCHAR(64) NULL AFTER email_verified,
    ADD COLUMN email_verification_expires TIMESTAMP NULL AFTER email_verification_token,
    ADD INDEX idx_email_verified (email, email_verified);

-- Backfill auth_providers for existing local users
INSERT INTO auth_providers (user_id, provider_type, is_primary)
SELECT user_id, 'local', TRUE
FROM users
WHERE password_hash IS NOT NULL
ON DUPLICATE KEY UPDATE is_primary = TRUE;

-- Create oauth_states table for CSRF protection
CREATE TABLE IF NOT EXISTS oauth_states (
    state_token VARCHAR(64) PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Run this migration with: php migrations/run_oauth_migration.php
-- Or manually: mysql -u user -p database < migrations/add_oauth_support.sql
