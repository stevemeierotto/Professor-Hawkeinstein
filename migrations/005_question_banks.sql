-- Migration 005: Question Banks
-- Created: December 2, 2025
-- Purpose: Store generated question banks for lessons (3 types × 20 questions each)

-- Question banks table (stores each question type as separate row)
CREATE TABLE IF NOT EXISTS lesson_question_banks (
    bank_id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    unit_index INT NOT NULL,
    lesson_index INT NOT NULL,
    question_type ENUM('fill_in_blank', 'multiple_choice', 'short_essay') NOT NULL,
    questions JSON NOT NULL COMMENT 'Array of question objects',
    question_count INT DEFAULT 20,
    difficulty_distribution JSON COMMENT '{"easy": 8, "medium": 8, "hard": 4}',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    
    -- Ensure one bank per lesson per type
    UNIQUE KEY unique_lesson_question_type (draft_id, unit_index, lesson_index, question_type),
    
    -- Index for quick lookups
    INDEX idx_draft_lesson (draft_id, unit_index, lesson_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example JSON structure for 'questions' field:
-- 
-- fill_in_blank:
-- [
--   {
--     "id": "fib_1",
--     "question": "Science is always _____ as we learn new things.",
--     "correct_answer": "changing",
--     "hint": "Think about how we discover new ideas",
--     "difficulty": "easy"
--   }
-- ]
--
-- multiple_choice:
-- [
--   {
--     "id": "mc_1",
--     "question": "What did people long ago think about the shape of Earth?",
--     "options": ["Round", "Flat", "Square", "Triangle"],
--     "correct_answer": "Flat",
--     "explanation": "Long ago, people believed Earth was flat.",
--     "difficulty": "easy"
--   }
-- ]
--
-- short_essay:
-- [
--   {
--     "id": "essay_1",
--     "question": "Why do scientists keep asking questions?",
--     "suggested_answer": "Scientists are curious and want to learn more...",
--     "rubric": {
--       "full_credit": "Explains that science evolves",
--       "partial_credit": "Mentions curiosity",
--       "keywords": ["learn", "discover", "change"]
--     },
--     "difficulty": "medium"
--   }
-- ]

-- View for easy querying of all questions for a lesson
CREATE OR REPLACE VIEW lesson_questions_summary AS
SELECT 
    lqb.draft_id,
    lqb.unit_index,
    lqb.lesson_index,
    cd.course_name as course_name,
    SUM(lqb.question_count) as total_questions,
    GROUP_CONCAT(lqb.question_type ORDER BY lqb.question_type) as question_types,
    MIN(lqb.generated_at) as first_generated,
    MAX(lqb.approved_at) as last_approved
FROM lesson_question_banks lqb
JOIN course_drafts cd ON lqb.draft_id = cd.draft_id
GROUP BY lqb.draft_id, lqb.unit_index, lqb.lesson_index, cd.course_name;
