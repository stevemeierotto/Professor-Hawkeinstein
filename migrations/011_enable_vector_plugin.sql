-- Migration 011: Enable MariaDB VECTOR storage for content embeddings
-- Requirements:
--   1. MariaDB 11.4+ with the vector plugin enabled (e.g. set `plugin_load_add=ha_vector`)
--   2. `mysqld` must start with `--plugin-maturity=alpha` for current vector support builds
--   3. Apply this migration inside the phef-database container: `docker compose exec phef-database mysql ...`

-- Ensure chunk metadata column exists for future filtering
ALTER TABLE content_embeddings
    ADD COLUMN IF NOT EXISTS chunk_metadata JSON NULL AFTER text_chunk;

-- Convert embedding storage from BLOB to native VECTOR type
ALTER TABLE content_embeddings
    MODIFY COLUMN embedding_vector VECTOR(384) NOT NULL;

-- Drop any legacy text or FULLTEXT indexes that relied on BLOB storage
DROP INDEX IF EXISTS idx_text_chunk ON content_embeddings;
DROP INDEX IF EXISTS vdx_content_embeddings_cossim ON content_embeddings;

-- Recreate supporting indexes, including cosine-distance vector index
CREATE INDEX idx_text_chunk ON content_embeddings (text_chunk(255));
CREATE VECTOR INDEX vdx_content_embeddings_cossim
    ON content_embeddings (embedding_vector)
    USING COSINE;

-- Verify default dimension metadata stays accurate
UPDATE content_embeddings
   SET vector_dimension = 384
 WHERE vector_dimension IS NULL OR vector_dimension <> 384;

SELECT 'Migration 011 completed: content_embeddings now uses VECTOR(384).' AS status;
