<?php
/**
 * Get draft_id for a published course
 * Returns the draft that was used to publish this course
 */

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

try {
    $courseId = $_GET['course_id'] ?? null;
    
    if (!$courseId) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing course_id parameter'
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
        echo json_encode([
            'success' => false,
            'message' => 'Course not found'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get_course_draft.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
