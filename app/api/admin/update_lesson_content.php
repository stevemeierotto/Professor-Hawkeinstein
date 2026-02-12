<?php
/**
 * Update Lesson Content API
 * 
 * Updates the title and content_text of educational_content entries
 * 
 * POST /api/admin/update_lesson_content.php
 * Body: {
 *   "draftId": 123,
 *   "unitIndex": 0,
 *   "lessonIndex": 0,
 *   "updates": [
 *     {
 *       "contentId": 456,
 *       "title": "Updated Title",
 *       "content_text": "Updated content..."
 *     }
 *   ]
 * }
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_update_lesson_content');

header('Content-Type: application/json');

// Get JSON body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$draftId = isset($data['draftId']) ? (int)$data['draftId'] : 0;
$unitIndex = isset($data['unitIndex']) ? (int)$data['unitIndex'] : null;
$lessonIndex = isset($data['lessonIndex']) ? (int)$data['lessonIndex'] : null;
$updates = isset($data['updates']) ? $data['updates'] : [];

if (!$draftId || $unitIndex === null || $lessonIndex === null || empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$db = getDb();

try {
    $db->beginTransaction();
    
    $updatedCount = 0;
    
    foreach ($updates as $update) {
        if (!isset($update['contentId']) || !isset($update['title']) || !isset($update['content_text'])) {
            continue;
        }
        
        $contentId = (int)$update['contentId'];
        $title = trim($update['title']);
        $content_text = trim($update['content_text']);
        
        // Verify this content is actually linked to this lesson
        $verifyStmt = $db->prepare("
            SELECT COUNT(*) 
            FROM draft_lesson_content 
            WHERE draft_id = ? AND unit_index = ? AND lesson_index = ? AND content_id = ?
        ");
        $verifyStmt->execute([$draftId, $unitIndex, $lessonIndex, $contentId]);
        $exists = $verifyStmt->fetchColumn();
        
        if (!$exists) {
            continue; // Skip if not linked to this lesson
        }
        
        // Update the educational_content entry
        $updateStmt = $db->prepare("
            UPDATE educational_content 
            SET title = ?, content_text = ?
            WHERE content_id = ?
        ");
        $updateStmt->execute([$title, $content_text, $contentId]);
        
        $updatedCount++;
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Updated $updatedCount content item(s)",
        'updatedCount' => $updatedCount
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
