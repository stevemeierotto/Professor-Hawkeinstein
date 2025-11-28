<?php
/**
 * Embed Content API
 * Generates embeddings for scraped content chunks
 * Admin only
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/embedding_generator.php';

setCORSHeaders();

// Require admin authorization
$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

if (!isset($input['content_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: content_id']);
    exit;
}

$contentId = intval($input['content_id']);
$forceRegenerate = isset($input['force']) && $input['force'] === true;

try {
    $db = getDB();
    
    // Get content
    $stmt = $db->prepare("SELECT content_id, title, content_text, has_embeddings FROM scraped_content WHERE content_id = ?");
    $stmt->execute([$contentId]);
    $content = $stmt->fetch();
    
    if (!$content) {
        http_response_code(404);
        echo json_encode(['error' => 'Content not found']);
        exit;
    }
    
    // Check if embeddings already exist
    if ($content['has_embeddings'] && !$forceRegenerate) {
        // Return existing embeddings count
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM content_embeddings WHERE content_id = ?");
        $countStmt->execute([$contentId]);
        $count = $countStmt->fetch()['count'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Embeddings already exist',
            'content_id' => $contentId,
            'embedding_count' => $count,
            'regenerated' => false
        ]);
        exit;
    }
    
    // Delete existing embeddings if regenerating
    if ($forceRegenerate) {
        $deleteStmt = $db->prepare("DELETE FROM content_embeddings WHERE content_id = ?");
        $deleteStmt->execute([$contentId]);
    }
    
    // Generate embeddings
    $generator = new EmbeddingGenerator();
    $chunks = $generator->chunkText($content['content_text'], 500, 50);
    
    if (empty($chunks)) {
        http_response_code(400);
        echo json_encode(['error' => 'No content to embed']);
        exit;
    }
    
    $modelInfo = $generator->getModelInfo();
    $embeddingsStored = 0;
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        $insertStmt = $db->prepare("
            INSERT INTO content_embeddings (content_id, chunk_index, text_chunk, embedding_vector, vector_dimension, model_used)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($chunks as $index => $chunk) {
            $embedding = $generator->generateEmbedding($chunk);
            $embeddingBlob = $generator->serializeEmbedding($embedding);
            
            $insertStmt->execute([
                $contentId,
                $index,
                $chunk,
                $embeddingBlob,
                $modelInfo['dimension'],
                $modelInfo['model']
            ]);
            
            $embeddingsStored++;
        }
        
        // Update scraped_content
        $updateStmt = $db->prepare("
            UPDATE scraped_content 
            SET has_embeddings = TRUE, 
                embedding_count = ?,
                last_embedded = NOW()
            WHERE content_id = ?
        ");
        $updateStmt->execute([$embeddingsStored, $contentId]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Embeddings generated successfully',
            'content_id' => $contentId,
            'title' => $content['title'],
            'chunks_processed' => count($chunks),
            'embeddings_stored' => $embeddingsStored,
            'model' => $modelInfo['model'],
            'dimension' => $modelInfo['dimension'],
            'fallback_mode' => $modelInfo['fallback']
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Embedding generation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to generate embeddings',
        'details' => $e->getMessage()
    ]);
}
