<?php
/**
 * Embedding Generation Helper
 * Generates vector embeddings for text chunks using sentence transformers
 * Fallback to TF-IDF if embedding model unavailable
 */

// No require here - let the calling script handle it

class EmbeddingGenerator {
    private $model = 'all-MiniLM-L6-v2';
    private $dimension = 384;
    private $useFallback = false;
    
    public function __construct() {
        // Check if Python embedding service is available
        $this->useFallback = !$this->checkEmbeddingService();
    }
    
    /**
     * Generate embedding for a text chunk
     * @param string $text The text to embed
     * @return array Embedding vector as float array
     */
    public function generateEmbedding($text) {
        if ($this->useFallback) {
            return $this->generateFallbackEmbedding($text);
        }
        
        try {
            // Use Python sentence-transformers via local API
            $result = $this->callPythonEmbedding($text);
            if ($result) {
                return $result;
            }
        } catch (Exception $e) {
            error_log("Embedding generation failed: " . $e->getMessage());
        }
        
        // Fallback to TF-IDF
        return $this->generateFallbackEmbedding($text);
    }
    
    /**
     * Generate embeddings for multiple text chunks (batch)
     * @param array $texts Array of text strings
     * @return array Array of embedding vectors
     */
    public function generateBatchEmbeddings($texts) {
        $embeddings = [];
        foreach ($texts as $text) {
            $embeddings[] = $this->generateEmbedding($text);
        }
        return $embeddings;
    }
    
    /**
     * Call Python embedding service (if available)
     */
    private function callPythonEmbedding($text) {
        // For now, return null (would call external service)
        // Future: implement HTTP call to Python embedding server
        return null;
    }
    
    /**
     * Check if embedding service is available
     */
    private function checkEmbeddingService() {
        // Check if Python embedding server is running
        // For now, always return false (use fallback)
        return false;
    }
    
    /**
     * Fallback: Generate simple TF-IDF-style embedding
     * This is a simplified version for when no embedding model is available
     */
    private function generateFallbackEmbedding($text) {
        // Clean and tokenize text
        $text = strtolower(trim($text));
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Create a fixed-size vocabulary hash
        $embedding = array_fill(0, $this->dimension, 0.0);
        
        // Simple hash-based embedding
        foreach ($words as $word) {
            $hash = crc32($word) % $this->dimension;
            $embedding[$hash] += 1.0;
        }
        
        // Normalize vector
        $magnitude = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $embedding)));
        if ($magnitude > 0) {
            $embedding = array_map(function($x) use ($magnitude) { 
                return $x / $magnitude; 
            }, $embedding);
        }
        
        return $embedding;
    }
    
    /**
     * Calculate cosine similarity between two embeddings
     */
    public function cosineSimilarity($vec1, $vec2) {
        if (count($vec1) !== count($vec2)) {
            throw new Exception("Vectors must have same dimension");
        }
        
        $dotProduct = 0.0;
        $mag1 = 0.0;
        $mag2 = 0.0;
        
        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $mag1 += $vec1[$i] * $vec1[$i];
            $mag2 += $vec2[$i] * $vec2[$i];
        }
        
        $magnitude = sqrt($mag1) * sqrt($mag2);
        return $magnitude > 0 ? $dotProduct / $magnitude : 0.0;
    }
    
    /**
     * Serialize embedding vector to BLOB for database storage
     */
    public function serializeEmbedding($embedding) {
        return pack('f*', ...$embedding);
    }
    
    /**
     * Deserialize embedding vector from BLOB
     */
    public function deserializeEmbedding($blob) {
        return unpack('f*', $blob);
    }
    
    /**
     * Chunk text into smaller pieces for embedding
     * @param string $text Full text to chunk
     * @param int $maxChunkSize Maximum characters per chunk
     * @param int $overlap Overlap between chunks
     * @return array Array of text chunks
     */
    public function chunkText($text, $maxChunkSize = 500, $overlap = 50) {
        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        $currentChunk = '';
        foreach ($sentences as $sentence) {
            if (strlen($currentChunk) + strlen($sentence) <= $maxChunkSize) {
                $currentChunk .= ' ' . $sentence;
            } else {
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                }
                $currentChunk = $sentence;
            }
        }
        
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }
        
        return $chunks;
    }
    
    /**
     * Get model information
     */
    public function getModelInfo() {
        return [
            'model' => $this->model,
            'dimension' => $this->dimension,
            'fallback' => $this->useFallback
        ];
    }
}

// Helper functions for direct use
function generateEmbedding($text) {
    $generator = new EmbeddingGenerator();
    return $generator->generateEmbedding($text);
}

function chunkAndEmbed($text) {
    $generator = new EmbeddingGenerator();
    $chunks = $generator->chunkText($text);
    $embeddings = [];
    
    foreach ($chunks as $chunk) {
        $embeddings[] = [
            'text' => $chunk,
            'embedding' => $generator->generateEmbedding($chunk)
        ];
    }
    
    return $embeddings;
}
