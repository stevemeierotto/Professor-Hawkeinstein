-- Add invitation token storage to oauth_states table
-- Date: February 6, 2026
-- Purpose: Store invitation tokens alongside OAuth state for retrieval in callback
--
-- This allows us to pass the invitation context through the OAuth flow
-- without exposing it in URLs (more secure)

ALTER TABLE oauth_states 
    ADD COLUMN invite_token VARCHAR(64) NULL 
    COMMENT 'Optional admin invitation token to process after OAuth'
    AFTER state_token;

-- Add index for faster lookups
ALTER TABLE oauth_states 
    ADD INDEX idx_invite_token (invite_token);

-- Verification:
-- SHOW COLUMNS FROM oauth_states LIKE 'invite_token';
-- Expected: Column exists, VARCHAR(64), NULL, no default
