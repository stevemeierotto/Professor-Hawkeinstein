-- ============================================================================
-- PHASE 1: Analytics Database Access Lock-Down
-- ============================================================================
-- Purpose: Create restricted read-only user for analytics data access
-- Privacy Goal: Prevent access to PII tables (users, progress_tracking, etc.)
-- Created: 2026-02-08
-- Database: professorhawkeinstein_platform (MariaDB 10.11)
-- ============================================================================

-- Drop user if exists (safe idempotent operation)
DROP USER IF EXISTS 'analytics_reader'@'%';

-- Create analytics_reader user with secure password
-- Using '%' wildcard to allow access from any host (Docker network)
CREATE USER 'analytics_reader'@'%' IDENTIFIED BY 'AnalyticsReadOnly2026!';

-- ============================================================================
-- GRANT PERMISSIONS: SELECT on analytics_* tables ONLY
-- ============================================================================

-- Grant SELECT on all analytics tables
GRANT SELECT ON professorhawkeinstein_platform.analytics_agent_metrics TO 'analytics_reader'@'%';
GRANT SELECT ON professorhawkeinstein_platform.analytics_course_leaderboard TO 'analytics_reader'@'%';
GRANT SELECT ON professorhawkeinstein_platform.analytics_course_metrics TO 'analytics_reader'@'%';
GRANT SELECT ON professorhawkeinstein_platform.analytics_current_month TO 'analytics_reader'@'%';
GRANT SELECT ON professorhawkeinstein_platform.analytics_daily_rollup TO 'analytics_reader'@'%';
GRANT SELECT ON professorhawkeinstein_platform.analytics_last_30_days TO 'analytics_reader'@'%';
GRANT SELECT ON professorhawkeinstein_platform.analytics_public_metrics TO 'analytics_reader'@'%';
GRANT SELECT ON professorhawkeinstein_platform.analytics_timeseries TO 'analytics_reader'@'%';
GRANT SELECT ON professorhawkeinstein_platform.analytics_user_snapshots TO 'analytics_reader'@'%';

-- ============================================================================
-- EXPLICIT DENIALS: Ensure NO access to PII tables
-- ============================================================================
-- Note: In MySQL/MariaDB, lack of GRANT = implicit denial
-- No REVOKE needed - user was just created with ONLY analytics_* SELECT grants
-- The following tables are EXPLICITLY EXCLUDED by design:
--   - users (PII: usernames, emails, passwords)
--   - progress_tracking (PII: student performance data)
--   - agent_memories (PII: conversation histories)
--   - student_advisors (PII: advisor assignments)
--   - agents (operational data, not analytics)
--   - educational_content (operational data)
--   - course_drafts (operational data)
--   - draft_lesson_content (operational data)

-- Apply all changes
FLUSH PRIVILEGES;

-- ============================================================================
-- VERIFICATION QUERIES (commented out - uncomment to test)
-- ============================================================================

-- View analytics_reader permissions:
-- SHOW GRANTS FOR 'analytics_reader'@'%';

-- Verify user exists:
-- SELECT User, Host FROM mysql.user WHERE User='analytics_reader';

-- Test access to analytics table (should succeed):
-- SELECT COUNT(*) FROM analytics_course_metrics;

-- Test access to PII table (should fail with permission denied):
-- SELECT COUNT(*) FROM users;

-- ============================================================================
-- PERMISSIONS SUMMARY
-- ============================================================================
-- GRANTED:
--   - SELECT on all analytics_* tables (9 tables)
--
-- DENIED (explicitly):
--   - ALL privileges on: users, progress_tracking, agent_memories, 
--     student_advisors, agents, educational_content, course_drafts,
--     draft_lesson_content
--
-- DENIED (implicitly - no grant):
--   - INSERT, UPDATE, DELETE, DROP on any table
--   - CREATE, ALTER, INDEX on any table
--   - GRANT OPTION (cannot grant permissions to others)
--   - Administrative privileges (SUPER, RELOAD, etc.)
-- ============================================================================
