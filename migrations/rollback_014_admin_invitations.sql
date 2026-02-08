-- Rollback Migration: Admin Invitations
-- Date: February 6, 2026
-- Purpose: Safely remove admin invitation system if needed
--
-- WHEN TO USE THIS:
-- - Testing showed issues with the invitation system
-- - Need to revert to pre-migration state
-- - Want to redesign the invitation flow
--
-- SAFETY: This rollback is SAFE because:
-- 1. admin_invitations is a new table (no existing dependencies)
-- 2. auth_provider_required column was NULLABLE (no enforcement yet)
-- 3. No code changes deployed that depend on these schema changes

-- Remove the admin invitations table
DROP TABLE IF EXISTS admin_invitations;

-- Remove the auth provider enforcement column
ALTER TABLE users DROP COLUMN IF EXISTS auth_provider_required;

-- Verification: Check that rollback succeeded
-- Run these queries after rollback:
--
-- SHOW TABLES LIKE 'admin_invitations';
-- Expected: Empty set (0 rows)
--
-- SHOW COLUMNS FROM users LIKE 'auth_provider_required';
-- Expected: Empty set (0 rows)
--
-- Test login with existing credentials
-- Expected: Login works exactly as before migration
