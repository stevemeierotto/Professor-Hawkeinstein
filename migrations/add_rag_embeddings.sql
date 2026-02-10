-- Migration: Enhanced embeddings table for RAG engine
-- Adds text_chunk storage and proper indexing for content retrieval
-- Author: System
-- Date: 2025-11-28

-- Legacy embeddings table is being superseded by content_embeddings with VECTOR storage
-- (kept for backward compatibility but no longer altered by this migration)

-- Create content_embeddings table for educational content chunks
CREATE TABLE IF NOT EXISTS content_embeddings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    content_id BIGINT NOT NULL,
    chunk_index INT NOT NULL,
    text_chunk LONGTEXT NOT NULL,
    chunk_metadata JSON NULL,
    embedding_vector VECTOR(384) NOT NULL,
    vector_dimension INT DEFAULT 384,
    model_used VARCHAR(100) DEFAULT 'llama.cpp',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_content_id (content_id),
    INDEX idx_chunk_index (chunk_index),
    INDEX idx_text_chunk (text_chunk(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE VECTOR INDEX vdx_content_embeddings_cossim
    ON content_embeddings (embedding_vector)
    USING COSINE;

ALTER TABLE educational_content
ADD COLUMN IF NOT EXISTS has_embeddings BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS embedding_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_embedded TIMESTAMP NULL;

-- Add index for faster embedding lookups
CREATE INDEX IF NOT EXISTS idx_has_embeddings ON educational_content(has_embeddings);

-- Add agent-specific content associations
CREATE TABLE IF NOT EXISTS agent_content_links (
    link_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    content_id BIGINT NOT NULL,
    relevance_score DECIMAL(3,2) DEFAULT 1.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    UNIQUE KEY unique_agent_content (agent_id, content_id),
    INDEX idx_agent_id (agent_id),
    INDEX idx_content_id (content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration complete
SELECT 'RAG embeddings migration applied successfully' AS status;
