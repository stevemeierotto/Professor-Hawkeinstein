// Require authentication (never trust client userId)
require_once __DIR__ . '/../helpers/auth_helpers.php';
require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();
$userData = requireAuth();
<?php
/**
 * Get draft_id for a published course
 * Returns the draft that was used to publish this course
 */

require_once __DIR__ . '/../../config/database.php';



try {
    $courseId = $_GET['course_id'] ?? null;
    
    if (!$courseId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameter.'
        ]);
        exit;
    }
    
    $db = getDB();
    
    // Get draft_id from published_courses table
    $stmt = $db->prepare("
        SELECT draft_id 
        FROM published_courses 
        WHERE course_id = ?
        LIMIT 1
    ");
    
    $stmt->execute([$courseId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'draft_id' => $result['draft_id']
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Resource not found.'
        ]);
    }
    
} catch (Exception $e) {
    // PHASE 5: Log details server-side, return generic error to client
    error_log("[get_course_draft.php] Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A server error occurred.'
    ]);
}
