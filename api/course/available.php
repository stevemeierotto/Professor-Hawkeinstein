<?php
/**
 * Get Available Courses (Not Enrolled)
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}


// Require authentication and always use authenticated userId (never trust client userId)
$userData = requireAuth();

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT 
            c.course_id,
            c.course_name,
            c.course_description,
            c.difficulty_level,
            c.subject_area,
            c.estimated_hours,
            a.agent_name,
            a.agent_type
        FROM courses c
        LEFT JOIN agents a ON c.recommended_agent_id = a.agent_id
        WHERE c.is_active = 1
        AND c.course_id NOT IN (
            SELECT course_id 
            FROM course_assignments 
            WHERE user_id = :userId
        )
        ORDER BY c.difficulty_level, c.course_name
    ");
    
    $stmt->execute(['userId' => $userData['userId']]);
    $courses = $stmt->fetchAll();
    
    sendJSON([
        'success' => true,
        'courses' => $courses
    ]);
    
} catch (Exception $e) {
    error_log("Available courses error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to fetch courses'], 500);
}
