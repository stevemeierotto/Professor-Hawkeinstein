<?php
/**
 * Get User's Enrolled Courses
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$userData = requireAuth();
$userId = $_GET['userId'] ?? $userData['userId'];

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT 
            ca.course_id,
            ca.status,
            ca.assigned_at,
            ca.started_at,
            c.course_name,
            c.course_description,
            c.difficulty_level,
            c.subject_area,
            c.estimated_hours,
            a.agent_name,
            a.agent_type,
            (SELECT AVG(metric_value) 
             FROM progress_tracking pt 
             WHERE pt.course_id = ca.course_id 
             AND pt.user_id = ca.user_id 
             AND pt.metric_type = 'completion') as progress_percentage
        FROM course_assignments ca
        JOIN courses c ON ca.course_id = c.course_id
        JOIN agents a ON ca.agent_id = a.agent_id
        WHERE ca.user_id = :userId
        ORDER BY ca.assigned_at DESC
    ");
    
    $stmt->execute(['userId' => $userId]);
    $courses = $stmt->fetchAll();
    
    sendJSON([
        'success' => true,
        'courses' => $courses
    ]);
    
} catch (Exception $e) {
    error_log("Enrolled courses error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to fetch courses'], 500);
}
