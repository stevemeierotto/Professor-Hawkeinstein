-- Migration: Add draft_id to courses table
-- Purpose: Link published courses back to their original drafts
-- This resolves the draft_id vs course_id confusion throughout the codebase

-- Add draft_id column to courses table
ALTER TABLE courses 
ADD COLUMN draft_id INT(11) NULL AFTER course_id,
ADD INDEX idx_draft_id (draft_id);

-- Add foreign key constraint to course_drafts
ALTER TABLE courses
ADD CONSTRAINT fk_courses_draft_id 
FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) 
ON DELETE SET NULL;

-- Update existing course (2nd Grade Science) to link to draft_id 9
UPDATE courses 
SET draft_id = 9 
WHERE course_name = '2nd Grade Science';
