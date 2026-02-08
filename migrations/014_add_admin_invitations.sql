-- Admin Invitation System Migration
-- Date: February 6, 2026
-- Description: Add admin invitation system for secure Google SSO onboarding
-- 
-- SAFETY: This migration is PURELY ADDITIVE. No existing data is modified.
-- All new columns are NULLABLE with safe defaults.
-- Existing authentication flows continue to work exactly as before.

-- ============================================================================
-- TABLE: admin_invitations
-- Purpose: Store email-based admin invitations with single-use tokens
-- ============================================================================
CREATE TABLE IF NOT EXISTS admin_invitations (
    invitation_id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Invitation details
    email VARCHAR(255) NOT NULL COMMENT 'Email address of invited admin',
    invite_token VARCHAR(64) UNIQUE NOT NULL COMMENT 'Single-use token for invite acceptance',
    role ENUM('admin', 'staff', 'root') NOT NULL COMMENT 'Role to assign upon acceptance',
    
    -- Audit trail
    invited_by INT NOT NULL COMMENT 'User ID of root user who created invite',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When invite was created',
    expires_at TIMESTAMP NOT NULL COMMENT 'When invite expires (typically 7 days)',
    
    -- Usage tracking
    used_at TIMESTAMP NULL COMMENT 'When invite was accepted (NULL = not yet used)',
    used_by_user_id INT NULL COMMENT 'User ID created or linked via this invite',
    
    -- Foreign keys
    FOREIGN KEY (invited_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (used_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_token (invite_token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at),
    INDEX idx_invited_by (invited_by, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Admin invitation tokens for secure Google SSO onboarding';

-- ============================================================================
-- MODIFY: users table
-- Purpose: Track which authentication provider a user MUST use
-- ============================================================================
-- CRITICAL SAFETY: Column is NULLABLE with NO DEFAULT
-- This means:
--   - Existing users get NULL (no restriction) ✅
--   - Existing logins continue to work ✅
--   - Only new invited admins get enforcement
-- ============================================================================
ALTER TABLE users 
    ADD COLUMN auth_provider_required ENUM('local', 'google') NULL 
    COMMENT 'If set, user MUST authenticate via this provider. NULL = no restriction (backward compat)'
    AFTER role;

-- ============================================================================
-- VERIFICATION QUERIES (for testing after migration)
-- ============================================================================
-- Verify table exists:
--   SELECT COUNT(*) FROM admin_invitations;
--   Expected: 0 (empty table)
--
-- Verify column added:
--   SELECT user_id, username, role, auth_provider_required FROM users LIMIT 5;
--   Expected: All users have NULL in auth_provider_required column
--
-- Verify existing logins still work:
--   Test login with existing username/password
--   Expected: Login succeeds as before

-- ============================================================================
-- ROLLBACK INSTRUCTIONS
-- ============================================================================
-- If this migration causes issues, run these commands to reverse:
--
-- DROP TABLE IF EXISTS admin_invitations;
-- ALTER TABLE users DROP COLUMN IF EXISTS auth_provider_required;
--
-- IMPORTANT: This rollback is SAFE because:
-- 1. No existing data was modified during migration
-- 2. No foreign keys point TO admin_invitations (only FROM it)
-- 3. Dropping the column removes enforcement (all users back to unrestricted)
-- ============================================================================
