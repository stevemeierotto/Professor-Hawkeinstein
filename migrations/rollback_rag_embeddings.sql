-- Rollback: Remove RAG embeddings enhancements
-- Author: System
-- Date: 2025-11-28

-- Drop agent content links
DROP TABLE IF EXISTS agent_content_links;

-- Remove columns from educational_content
ALTER TABLE educational_content
DROP COLUMN IF EXISTS last_embedded,
DROP COLUMN IF EXISTS embedding_count,
DROP COLUMN IF EXISTS has_embeddings;

-- Drop content embeddings table
DROP TABLE IF EXISTS content_embeddings;

-- Remove columns from embeddings
ALTER TABLE embeddings
DROP COLUMN IF EXISTS chunk_metadata,
DROP COLUMN IF EXISTS text_chunk;

-- Rollback complete
SELECT 'RAG embeddings migration rolled back successfully' AS status;
