<?php
/**
 * Delete Scraped Content API
 * Removes content from the database
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

if (!isset($input['content_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Content ID required']);
    exit;
}

$contentId = intval($input['content_id']);

try {
    $db = getDB();
    
    // Delete the content
    $stmt = $db->prepare("DELETE FROM educational_content WHERE content_id = ?");
    $result = $stmt->execute([$contentId]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Content not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Content deleted successfully',
        'content_id' => $contentId
    ]);
    
} catch (Exception $e) {
    error_log("Delete content error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete content: ' . $e->getMessage()]);
}
?>
