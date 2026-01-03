-- Migration: Rename scraped_content to educational_content
-- Reason: Table contains both CSP standards metadata AND LLM-generated lessons
-- The name "scraped_content" is misleading since we moved from web scraping to generation

-- Step 1: Rename the table
RENAME TABLE scraped_content TO educational_content;

-- Step 2: Add FULLTEXT index for RAG search (if not exists)
-- FULLTEXT indexes enable fast text search for the student advisor RAG system
CREATE FULLTEXT INDEX IF NOT EXISTS ft_educational_content 
ON educational_content(title, content_text);

-- Step 3: Verify the rename
-- After running this migration, verify with:
-- SHOW TABLES LIKE '%content%';
-- SELECT COUNT(*) FROM educational_content;
