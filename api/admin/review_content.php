<?php
/**
 * Review Content API
 * Submit review for scraped content
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

$required = ['content_id', 'recommendation'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$contentId = intval($input['content_id']);
$recommendation = $input['recommendation'];

if (!in_array($recommendation, ['approve', 'reject', 'revise'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid recommendation']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    // Insert review record
    $stmt = $db->prepare("
        INSERT INTO content_reviews (
            content_id, reviewer_id, accuracy_score, relevance_score,
            quality_score, strengths, weaknesses, fact_check_notes, recommendation
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $contentId,
        $admin['userId'],
        $input['accuracy_score'] ?? null,
        $input['relevance_score'] ?? null,
        $input['quality_score'] ?? null,
        $input['strengths'] ?? null,
        $input['weaknesses'] ?? null,
        $input['fact_check_notes'] ?? null,
        $recommendation
    ]);
    
    // Update content status
    $status = $recommendation === 'approve' ? 'approved' : 
              ($recommendation === 'reject' ? 'rejected' : 'needs_revision');
    
    $updateStmt = $db->prepare("
        UPDATE educational_content 
        SET review_status = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE content_id = ?
    ");
    
    $updateStmt->execute([$status, $admin['userId'], $contentId]);
    
        // If approved, optionally add to RAG documents
    if ($recommendation === 'approve' && isset($input['add_to_rag']) && $input['add_to_rag']) {
        // Get content details
        $contentStmt = $db->prepare("SELECT * FROM educational_content WHERE content_id = ?");
        $contentStmt->execute([$contentId]);
        $content = $contentStmt->fetch();
        
        // Insert into rag_documents (if table exists)
        if ($content) {
            $ragStmt = $db->prepare("
                INSERT INTO rag_documents (
                    agent_id, document_title, document_type, content,
                    content_chunk, source_url, metadata
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $metadata = json_encode([
                'educational_content_id' => $contentId,
                'credibility_score' => $content['credibility_score'] ?? 0,
                'grade_level' => $content['grade_level'] ?? '',
                'subject' => $content['subject'] ?? ''
            ]);
            
            // Chunk content (simple split by paragraphs for now)
            $contentText = $content['content_text'] ?? '';
            $chunks = array_filter(explode("\n\n", $contentText));
            $chunkText = !empty($chunks) ? $chunks[0] : substr($contentText, 0, 1000);
            
            try {
                $ragStmt->execute([
                    null, // agent_id NULL means shared across agents
                    $content['title'] ?? '',
                    'educational',
                    $contentText,
                    $chunkText,
                    $content['url'] ?? '',
                    $metadata
                ]);
                
                // Mark as added to RAG
                $db->prepare("UPDATE educational_content SET is_added_to_rag = TRUE WHERE content_id = ?")
                   ->execute([$contentId]);
            } catch (Exception $ragError) {
                // RAG document insertion failed, but review was successful
                error_log("RAG insertion failed: " . $ragError->getMessage());
            }
        }
    }    $db->commit();
    
    // Log action
    logAdminAction(
        $admin['user_id'],
        'CONTENT_REVIEWED',
        "Reviewed content #{$contentId} - Recommendation: $recommendation",
        ['content_id' => $contentId, 'recommendation' => $recommendation]
    );
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Review error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit review']);
}
