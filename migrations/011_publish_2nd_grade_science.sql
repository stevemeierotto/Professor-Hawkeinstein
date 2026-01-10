-- Migration 011: Properly publish 2nd Grade Science course
-- Creates entry in courses table so it shows in admin dashboard

-- Insert 2nd Grade Science into courses table
INSERT INTO courses (course_name, course_description, difficulty_level, subject_area, is_active, created_at)
SELECT 
    course_name,
    CONCAT('Grade: ', grade, '\nSubject: ', subject, '\nComprehensive science course covering living organisms, plant growth, Earth systems, and environmental awareness. Includes 11 lessons with AI-generated content.'),
    'beginner',
    subject,
    1,
    NOW()
FROM course_drafts
WHERE draft_id = 9
AND NOT EXISTS (
    SELECT 1 FROM courses WHERE course_name = '2nd Grade Science'
);

-- Display confirmation
SELECT 
    c.course_id,
    c.course_name,
    c.subject_area,
    c.difficulty_level,
    cd.status as draft_status,
    (SELECT COUNT(*) FROM draft_lesson_content WHERE draft_id = 9) as lesson_count
FROM courses c
LEFT JOIN course_drafts cd ON BINARY cd.course_name = BINARY c.course_name
WHERE c.course_name = '2nd Grade Science';
