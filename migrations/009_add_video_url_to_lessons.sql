-- Migration 009: Add video_url column to lessons and draft_lessons tables
-- This allows storing YouTube video links for each lesson
-- Run with: mysql -u professorhawkeinstein_user -p professorhawkeinstein_platform < migrations/009_add_video_url_to_lessons.sql

-- Add video_url to lessons table
ALTER TABLE lessons 
ADD COLUMN video_url VARCHAR(255) DEFAULT NULL AFTER lesson_content,
ADD INDEX idx_video_url (video_url);

-- Add video_url to draft_lessons table
ALTER TABLE draft_lessons 
ADD COLUMN video_url VARCHAR(255) DEFAULT NULL AFTER lesson_content,
ADD INDEX idx_video_url (video_url);

-- Display confirmation
SELECT 'Migration 009 completed: video_url column added to lessons and draft_lessons tables' AS status;
