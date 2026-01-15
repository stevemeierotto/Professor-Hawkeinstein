-- Analytics Tables Migration
-- Created: January 14, 2026
-- Purpose: Aggregate metrics, time-series rollups, and platform statistics
-- Privacy: NO PII stored, aggregate data only, hashed identifiers where needed

-- ============================================================================
-- PLATFORM AGGREGATE METRICS (Daily Rollups)
-- ============================================================================

CREATE TABLE IF NOT EXISTS analytics_daily_rollup (
    rollup_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    rollup_date DATE NOT NULL UNIQUE,
    
    -- User Activity Metrics
    total_active_users INT DEFAULT 0,
    new_users INT DEFAULT 0,
    returning_users INT DEFAULT 0,
    
    -- Course Engagement
    active_course_enrollments INT DEFAULT 0,
    lessons_completed INT DEFAULT 0,
    quizzes_attempted INT DEFAULT 0,
    quizzes_passed INT DEFAULT 0,
    
    -- Time Metrics (in minutes)
    total_study_time_minutes INT DEFAULT 0,
    avg_session_duration_minutes DECIMAL(10,2) DEFAULT 0,
    
    -- Mastery Metrics
    avg_mastery_score DECIMAL(5,2) DEFAULT 0,
    mastery_90_plus_count INT DEFAULT 0,  -- Students achieving 90%+ mastery
    
    -- Agent Interactions
    total_agent_messages INT DEFAULT 0,
    avg_agent_response_time_ms INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_rollup_date (rollup_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- COURSE EFFECTIVENESS METRICS (Aggregate by Course)
-- ============================================================================

CREATE TABLE IF NOT EXISTS analytics_course_metrics (
    metric_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    calculation_date DATE NOT NULL,
    
    -- Enrollment Stats
    total_enrolled INT DEFAULT 0,
    active_students INT DEFAULT 0,
    completed_students INT DEFAULT 0,
    
    -- Completion Rates
    completion_rate DECIMAL(5,2) DEFAULT 0,  -- Percentage
    avg_completion_time_days DECIMAL(10,2) DEFAULT 0,
    
    -- Mastery Stats
    avg_mastery_score DECIMAL(5,2) DEFAULT 0,
    mastery_distribution JSON,  -- {"0-50": 10, "50-70": 25, "70-90": 40, "90-100": 25}
    
    -- Engagement
    avg_study_time_hours DECIMAL(10,2) DEFAULT 0,
    avg_lessons_per_student DECIMAL(10,2) DEFAULT 0,
    avg_quiz_attempts DECIMAL(10,2) DEFAULT 0,
    
    -- Quality Indicators
    student_satisfaction_score DECIMAL(3,2) DEFAULT 0,  -- Future: student feedback
    retry_rate DECIMAL(5,2) DEFAULT 0,  -- Quiz retry percentage
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    UNIQUE KEY unique_course_date (course_id, calculation_date),
    INDEX idx_course_id (course_id),
    INDEX idx_calculation_date (calculation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- AGENT EFFECTIVENESS METRICS (Aggregate by Agent)
-- ============================================================================

CREATE TABLE IF NOT EXISTS analytics_agent_metrics (
    metric_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    calculation_date DATE NOT NULL,
    
    -- Interaction Volume
    total_interactions INT DEFAULT 0,
    unique_users_served INT DEFAULT 0,
    
    -- Response Quality
    avg_response_time_ms INT DEFAULT 0,
    avg_response_length_chars INT DEFAULT 0,
    
    -- Student Outcomes (students interacting with this agent)
    avg_student_mastery DECIMAL(5,2) DEFAULT 0,
    students_improved_count INT DEFAULT 0,  -- Students showing mastery increase
    
    -- Engagement
    avg_interactions_per_user DECIMAL(10,2) DEFAULT 0,
    total_messages_sent INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    UNIQUE KEY unique_agent_date (agent_id, calculation_date),
    INDEX idx_agent_id (agent_id),
    INDEX idx_calculation_date (calculation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ANONYMIZED USER PROGRESS SNAPSHOTS (Research Export Table)
-- ============================================================================

CREATE TABLE IF NOT EXISTS analytics_user_snapshots (
    snapshot_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_hash VARCHAR(64) NOT NULL,  -- SHA256 hash of user_id (irreversible)
    snapshot_date DATE NOT NULL,
    
    -- Demographics (Optional, Privacy-Respecting)
    age_group ENUM('under_13', '13_17', '18_plus', 'not_provided') DEFAULT 'not_provided',
    geographic_region VARCHAR(50) DEFAULT 'not_provided',  -- State/province level only
    
    -- Progress Metrics (point-in-time)
    courses_enrolled INT DEFAULT 0,
    courses_completed INT DEFAULT 0,
    total_study_hours DECIMAL(10,2) DEFAULT 0,
    avg_mastery_score DECIMAL(5,2) DEFAULT 0,
    
    -- Engagement
    days_active INT DEFAULT 0,
    total_lessons_completed INT DEFAULT 0,
    total_quizzes_attempted INT DEFAULT 0,
    
    -- Milestones
    milestones_achieved INT DEFAULT 0,
    last_activity_date DATE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_hash (user_hash),
    INDEX idx_snapshot_date (snapshot_date),
    INDEX idx_age_group (age_group),
    INDEX idx_geographic_region (geographic_region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TIME-SERIES METRICS (Hourly/Weekly/Monthly Aggregations)
-- ============================================================================

CREATE TABLE IF NOT EXISTS analytics_timeseries (
    timeseries_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    period_type ENUM('hourly', 'daily', 'weekly', 'monthly') NOT NULL,
    period_start DATETIME NOT NULL,
    period_end DATETIME NOT NULL,
    
    -- Activity Metrics
    active_users INT DEFAULT 0,
    new_registrations INT DEFAULT 0,
    lessons_completed INT DEFAULT 0,
    quizzes_completed INT DEFAULT 0,
    
    -- Performance Metrics
    avg_mastery_score DECIMAL(5,2) DEFAULT 0,
    avg_quiz_score DECIMAL(5,2) DEFAULT 0,
    
    -- Engagement Metrics
    total_study_minutes INT DEFAULT 0,
    total_agent_interactions INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_period (period_type, period_start),
    INDEX idx_period_type (period_type),
    INDEX idx_period_start (period_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PUBLIC METRICS CACHE (Pre-computed for public display)
-- ============================================================================

CREATE TABLE IF NOT EXISTS analytics_public_metrics (
    metric_key VARCHAR(100) PRIMARY KEY,
    metric_value TEXT NOT NULL,
    metric_type ENUM('number', 'percentage', 'text', 'json') DEFAULT 'number',
    display_label VARCHAR(200) NOT NULL,
    display_order INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INITIALIZE PUBLIC METRICS WITH DEFAULTS
-- ============================================================================

INSERT INTO analytics_public_metrics (metric_key, metric_value, metric_type, display_label, display_order) VALUES
('total_learners', '0', 'number', 'Total Learners Served', 1),
('avg_mastery_improvement', '0.00', 'percentage', 'Average Mastery Improvement', 2),
('course_completion_rate', '0.00', 'percentage', 'Course Completion Rate', 3),
('total_study_hours', '0', 'number', 'Total Study Hours', 4),
('active_courses', '0', 'number', 'Active Courses', 5),
('lessons_completed', '0', 'number', 'Lessons Completed', 6),
('avg_quiz_score', '0.00', 'percentage', 'Average Quiz Score', 7),
('platform_uptime', '99.9', 'percentage', 'Platform Uptime', 8)
ON DUPLICATE KEY UPDATE last_updated = CURRENT_TIMESTAMP;

-- ============================================================================
-- VIEWS FOR COMMON QUERIES
-- ============================================================================

-- Last 30 Days Platform Activity
CREATE OR REPLACE VIEW analytics_last_30_days AS
SELECT 
    COUNT(DISTINCT user_id) as active_users,
    SUM(CASE WHEN metric_type = 'completion' THEN 1 ELSE 0 END) as lessons_completed,
    AVG(CASE WHEN metric_type = 'mastery' THEN metric_value ELSE NULL END) as avg_mastery,
    SUM(CASE WHEN metric_type = 'time_spent' THEN metric_value ELSE 0 END) / 60 as total_study_hours
FROM progress_tracking
WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Current Month Summary
CREATE OR REPLACE VIEW analytics_current_month AS
SELECT
    DATE_FORMAT(recorded_at, '%Y-%m') as month,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(*) as total_activities,
    AVG(CASE WHEN metric_type = 'mastery' THEN metric_value ELSE NULL END) as avg_mastery
FROM progress_tracking
WHERE recorded_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
GROUP BY month;

-- Course Leaderboard (Aggregate)
CREATE OR REPLACE VIEW analytics_course_leaderboard AS
SELECT
    c.course_id,
    c.course_name,
    COUNT(DISTINCT ca.user_id) as total_students,
    AVG(pt.metric_value) as avg_mastery,
    SUM(CASE WHEN ca.status = 'completed' THEN 1 ELSE 0 END) as completions
FROM courses c
LEFT JOIN course_assignments ca ON c.course_id = ca.course_id
LEFT JOIN progress_tracking pt ON ca.user_id = pt.user_id AND ca.course_id = pt.course_id AND pt.metric_type = 'mastery'
WHERE c.is_active = 1
GROUP BY c.course_id, c.course_name
ORDER BY avg_mastery DESC;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================

-- Log migration
INSERT INTO analytics_public_metrics (metric_key, metric_value, metric_type, display_label, display_order)
VALUES ('schema_version', '004', 'text', 'Analytics Schema Version', 999)
ON DUPLICATE KEY UPDATE metric_value = '004', last_updated = CURRENT_TIMESTAMP;
