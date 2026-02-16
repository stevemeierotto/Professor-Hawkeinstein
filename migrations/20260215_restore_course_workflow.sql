-- Migration: Restore core course workflow tables for admin course wizard
-- Date: 2026-02-15
-- Notes: Reintroduces essential tables previously dropped in refactor so admin APIs return data.

-- Ensure authentication provider column exists for login enforcement
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS auth_provider_required ENUM('local','google') NULL AFTER is_active;

-- Create published courses catalog
CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(200) NOT NULL,
    course_description TEXT,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced', 'expert') NOT NULL,
    subject_area VARCHAR(100),
    recommended_agent_id INT NULL,
    multimedia_path VARCHAR(500),
    estimated_hours DECIMAL(5,2),
    prerequisites JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recommended_agent_id) REFERENCES agents(agent_id) ON DELETE SET NULL,
    INDEX idx_difficulty (difficulty_level),
    INDEX idx_subject (subject_area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Course enrollment linkages
CREATE TABLE IF NOT EXISTS course_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    agent_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    status ENUM('assigned', 'in_progress', 'completed', 'paused') DEFAULT 'assigned',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_course (user_id, course_id),
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student progress metrics
CREATE TABLE IF NOT EXISTS progress_tracking (
    progress_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    agent_id INT NOT NULL,
    metric_type VARCHAR(50) NOT NULL,
    metric_value DECIMAL(5,2) NOT NULL,
    milestone VARCHAR(100),
    strengths TEXT,
    weaknesses TEXT,
    notes TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    INDEX idx_user_course (user_id, course_id),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Core draft workflow tables
CREATE TABLE IF NOT EXISTS course_drafts (
    draft_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(255) NOT NULL,
    subject VARCHAR(64) NOT NULL,
    grade VARCHAR(32) NOT NULL,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('draft', 'standards_review', 'outline_review', 'approved', 'published') DEFAULT 'draft',
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approved_standards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    standard_id VARCHAR(128),
    standard_code VARCHAR(64),
    description TEXT,
    approved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_outlines (
    outline_id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    outline_json LONGTEXT,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Educational content repository needed by draft_lesson_content
CREATE TABLE IF NOT EXISTS educational_content (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(2048) NOT NULL,
    title VARCHAR(500),
    content_type VARCHAR(50) DEFAULT 'educational',
    content_html LONGTEXT NOT NULL,
    content_text LONGTEXT,
    video_url VARCHAR(255) DEFAULT NULL,
    metadata TEXT,
    credibility_score DECIMAL(3,2) DEFAULT 0.00,
    domain VARCHAR(255),
    scraped_by INT NOT NULL,
    scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    review_status ENUM('pending', 'approved', 'rejected', 'needs_revision') DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT,
    is_added_to_rag BOOLEAN DEFAULT FALSE,
    grade_level VARCHAR(50),
    subject VARCHAR(100),
    FOREIGN KEY (scraped_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_url (url(255)),
    INDEX idx_review_status (review_status),
    INDEX idx_scraped_at (scraped_at),
    INDEX idx_grade_subject (grade_level, subject),
    INDEX idx_video_url (video_url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Draft lesson linkage and generated content tables
CREATE TABLE IF NOT EXISTS draft_lesson_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    unit_index INT NOT NULL,
    lesson_index INT NOT NULL,
    content_id INT NOT NULL,
    relevance_score DECIMAL(3,2) DEFAULT 0.50,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES educational_content(content_id) ON DELETE CASCADE,
    UNIQUE KEY unique_lesson_content (draft_id, unit_index, lesson_index, content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS draft_lessons (
    lesson_id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    unit_index INT NOT NULL,
    lesson_index INT NOT NULL,
    lesson_title VARCHAR(255) NOT NULL,
    lesson_content LONGTEXT,
    video_url VARCHAR(255) DEFAULT NULL,
    standard_codes TEXT,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE,
    UNIQUE KEY unique_draft_lesson (draft_id, unit_index, lesson_index),
    INDEX idx_video_url (video_url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS draft_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    scope ENUM('lesson', 'unit', 'course') DEFAULT 'lesson',
    unit_index INT,
    lesson_index INT,
    question_type ENUM('multiple_choice', 'true_false', 'short_answer', 'essay') DEFAULT 'multiple_choice',
    question_text TEXT NOT NULL,
    correct_answer TEXT,
    wrong_answers JSON,
    explanation TEXT,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    standard_code VARCHAR(64),
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE,
    INDEX idx_draft_scope (draft_id, scope),
    INDEX idx_draft_unit (draft_id, unit_index),
    INDEX idx_draft_lesson (draft_id, unit_index, lesson_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lesson question banks
CREATE TABLE IF NOT EXISTS lesson_question_banks (
    bank_id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    unit_index INT NOT NULL,
    lesson_index INT NOT NULL,
    question_type ENUM('fill_in_blank', 'multiple_choice', 'short_essay') NOT NULL,
    questions JSON NOT NULL,
    question_count INT DEFAULT 20,
    difficulty_distribution JSON,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_lesson_question_type (draft_id, unit_index, lesson_index, question_type),
    INDEX idx_draft_lesson (draft_id, unit_index, lesson_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View summarizing question banks
CREATE OR REPLACE VIEW lesson_questions_summary AS
SELECT 
    lqb.draft_id,
    lqb.unit_index,
    lqb.lesson_index,
    cd.course_name AS course_name,
    SUM(lqb.question_count) AS total_questions,
    GROUP_CONCAT(lqb.question_type ORDER BY lqb.question_type) AS question_types,
    MIN(lqb.generated_at) AS first_generated,
    MAX(lqb.approved_at) AS last_approved
FROM lesson_question_banks lqb
JOIN course_drafts cd ON lqb.draft_id = cd.draft_id
GROUP BY lqb.draft_id, lqb.unit_index, lqb.lesson_index, cd.course_name;

-- Centralized rate limiting storage
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(128) NOT NULL,
    endpoint_class VARCHAR(32) NOT NULL,
    window_start DATETIME NOT NULL,
    request_count INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier),
    INDEX idx_endpoint_class (endpoint_class),
    INDEX idx_window_start (window_start),
    INDEX idx_composite (identifier, endpoint_class, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Centralized rate limiting storage for API security hardening';

-- Development admin account for bootstrap environments (password: password)
INSERT INTO users (username, email, password_hash, full_name, role)
SELECT 'admin', 'admin@local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');
