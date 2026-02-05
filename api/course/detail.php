<?php
/**
 * Get Course Details
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}


// Require authentication (never trust client userId)
$userData = requireAuth();

$courseId = $_GET['courseId'] ?? null;

if (empty($courseId)) {
    sendJSON(['success' => false, 'message' => 'Course ID required'], 400);
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT 
            c.*,
            a.agent_name,
            a.agent_type,
            a.specialization
        FROM courses c
        LEFT JOIN agents a ON c.recommended_agent_id = a.agent_id
        WHERE c.course_id = :courseId AND c.is_active = 1
    ");
    
    $stmt->execute(['courseId' => $courseId]);
    $course = $stmt->fetch();
    
    if (!$course) {
        sendJSON(['success' => false, 'message' => 'Course not found'], 404);
    }
    
    sendJSON([
        'success' => true,
        'course' => $course
    ]);
    
} catch (Exception $e) {
    error_log("Course detail error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to fetch course'], 500);
}
