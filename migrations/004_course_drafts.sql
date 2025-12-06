-- Migration: Create course_drafts, approved_standards, and course_outlines tables for course wizard
-- Run with: mysql -u professorhawkeinstein_user -p professorhawkeinstein_platform < migrations/004_course_drafts.sql

CREATE TABLE IF NOT EXISTS course_drafts (
    draft_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(255) NOT NULL,
    subject VARCHAR(64) NOT NULL,
    grade VARCHAR(32) NOT NULL,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('draft', 'standards_review', 'outline_review', 'approved', 'published') DEFAULT 'draft',
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS approved_standards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    standard_id VARCHAR(128),
    standard_code VARCHAR(64),
    description TEXT,
    approved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS course_outlines (
    outline_id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    outline_json LONGTEXT,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
