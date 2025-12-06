-- Migration 005: Add tables for draft lessons, questions, and scraped content linking
-- Run with: mysql -u professorhawkeinstein_user -p professorhawkeinstein_platform < migrations/005_draft_lessons.sql

-- Table to link scraped content to specific draft lessons
CREATE TABLE IF NOT EXISTS draft_lesson_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    unit_index INT NOT NULL,
    lesson_index INT NOT NULL,
    content_id INT NOT NULL,
    relevance_score DECIMAL(3,2) DEFAULT 0.50,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES scraped_content(content_id) ON DELETE CASCADE,
    UNIQUE KEY unique_lesson_content (draft_id, unit_index, lesson_index, content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for generated lesson content
CREATE TABLE IF NOT EXISTS draft_lessons (
    lesson_id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    unit_index INT NOT NULL,
    lesson_index INT NOT NULL,
    lesson_title VARCHAR(255) NOT NULL,
    lesson_content LONGTEXT,
    standard_codes TEXT,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE,
    UNIQUE KEY unique_draft_lesson (draft_id, unit_index, lesson_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for generated quiz/test questions
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add content_type column to scraped_content if it doesn't exist
-- (It should already exist based on the schema we saw)
