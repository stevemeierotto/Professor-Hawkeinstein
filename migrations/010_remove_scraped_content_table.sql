-- Migration 010: Remove scraped_content table and update all references to educational_content
-- This completes the transition from scraped_content to educational_content naming

-- Step 1: Drop foreign key constraint from content_reviews
ALTER TABLE content_reviews 
DROP FOREIGN KEY content_reviews_ibfk_1;

-- Step 2: Add new foreign key pointing to educational_content
ALTER TABLE content_reviews 
ADD CONSTRAINT content_reviews_ibfk_1 
FOREIGN KEY (content_id) REFERENCES educational_content(content_id) ON DELETE CASCADE;

-- Step 3: Drop the now-unused scraped_content table
DROP TABLE IF EXISTS scraped_content;

-- Display confirmation
SELECT 'Migration 010 completed: scraped_content table removed, all references updated to educational_content' AS status;
