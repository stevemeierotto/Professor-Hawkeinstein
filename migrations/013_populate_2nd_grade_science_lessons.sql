-- Populate units and lessons tables for 2nd Grade Science course
-- This migration creates the unit and lesson structure from the course draft

USE professorhawkeinstein_platform;

-- Create Unit 1 for 2nd Grade Science
-- agent_id 1 is Professor Hawkeinstein (student advisor) - content is added to RAG
INSERT INTO units (course_id, agent_id, unit_number, unit_title, unit_description, total_lessons)
VALUES (5, 1, 1, 'Exploring the Natural World', 'An introduction to basic science concepts for 2nd grade students, covering living organisms, plants, materials, Earth systems, and natural resources.', 11);

-- Get the unit_id we just created
SET @unit_id = LAST_INSERT_ID();

-- Create lessons from draft_lesson_content
-- Note: draft_lesson_content has unit_index and lesson_index starting at 0
-- We need to convert to lesson_number starting at 1
-- Copying content_text directly into lesson_content field
INSERT INTO lessons (unit_id, lesson_number, lesson_title, lesson_content, video_url)
SELECT 
    @unit_id,
    dlc.lesson_index + 1,
    ec.title,
    ec.content_text,
    ec.video_url
FROM draft_lesson_content dlc
JOIN educational_content ec ON dlc.content_id = ec.content_id
WHERE dlc.draft_id = 9 AND dlc.unit_index = 0
ORDER BY dlc.lesson_index;

-- Verify the results
SELECT 
    c.course_id, c.course_name,
    u.unit_id, u.unit_number, u.unit_title,
    l.lesson_id, l.lesson_number, l.lesson_title, LENGTH(l.lesson_content) as content_length
FROM courses c
JOIN units u ON c.course_id = u.course_id
JOIN lessons l ON u.unit_id = l.unit_id
WHERE c.course_id = 5
ORDER BY l.lesson_number;
