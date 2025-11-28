<?php
/**
 * Retrieve Context API
 * Retrieves relevant context chunks for a query using embeddings
 * Used by agent chat to inject RAG context
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/embedding_generator.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

if (!isset($input['query']) || empty($input['query'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: query']);
    exit;
}

$query = $input['query'];
$agentId = isset($input['agent_id']) ? intval($input['agent_id']) : null;
$topK = isset($input['top_k']) ? intval($input['top_k']) : 3;
$minSimilarity = isset($input['min_similarity']) ? floatval($input['min_similarity']) : 0.3;

try {
    $db = getDB();
    $generator = new EmbeddingGenerator();
    
    // Generate query embedding
    $queryEmbedding = $generator->generateEmbedding($query);
    
    // Get all embeddings (with optional agent filtering)
    if ($agentId) {
        // Get content linked to specific agent
        $stmt = $db->prepare("
            SELECT ce.id, ce.content_id, ce.chunk_index, ce.text_chunk, ce.embedding_vector, 
                   sc.title, sc.subject, sc.grade_level
            FROM content_embeddings ce
            JOIN scraped_content sc ON ce.content_id = sc.content_id
            LEFT JOIN agent_content_links acl ON sc.content_id = acl.content_id AND acl.agent_id = ?
            WHERE sc.has_embeddings = TRUE
            ORDER BY acl.relevance_score DESC
        ");
        $stmt->execute([$agentId]);
    } else {
        // Get all available embeddings
        $stmt = $db->prepare("
            SELECT ce.id, ce.content_id, ce.chunk_index, ce.text_chunk, ce.embedding_vector,
                   sc.title, sc.subject, sc.grade_level
            FROM content_embeddings ce
            JOIN scraped_content sc ON ce.content_id = sc.content_id
            WHERE sc.has_embeddings = TRUE
        ");
        $stmt->execute();
    }
    
    $embeddings = $stmt->fetchAll();
    
    if (empty($embeddings)) {
        // No embeddings available - return empty result with fallback flag
        echo json_encode([
            'success' => true,
            'context_chunks' => [],
            'fallback_mode' => true,
            'message' => 'No embeddings available. Content needs to be embedded first.'
        ]);
        exit;
    }
    
    // Calculate similarities
    $similarities = [];
    foreach ($embeddings as $row) {
        $embeddingVector = $generator->deserializeEmbedding($row['embedding_vector']);
        $embeddingVector = array_values($embeddingVector); // Re-index array
        
        $similarity = $generator->cosineSimilarity($queryEmbedding, $embeddingVector);
        
        if ($similarity >= $minSimilarity) {
            $similarities[] = [
                'id' => $row['id'],
                'content_id' => $row['content_id'],
                'chunk_index' => $row['chunk_index'],
                'text_chunk' => $row['text_chunk'],
                'title' => $row['title'],
                'subject' => $row['subject'],
                'grade_level' => $row['grade_level'],
                'similarity' => $similarity
            ];
        }
    }
    
    // Sort by similarity (descending)
    usort($similarities, function($a, $b) {
        return $b['similarity'] <=> $a['similarity'];
    });
    
    // Return top K results
    $topResults = array_slice($similarities, 0, $topK);
    
    echo json_encode([
        'success' => true,
        'query' => $query,
        'context_chunks' => $topResults,
        'total_candidates' => count($embeddings),
        'matched_chunks' => count($similarities),
        'returned_chunks' => count($topResults),
        'fallback_mode' => false,
        'min_similarity' => $minSimilarity
    ]);
    
} catch (Exception $e) {
    error_log("Context retrieval error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve context',
        'details' => $e->getMessage()
    ]);
}
