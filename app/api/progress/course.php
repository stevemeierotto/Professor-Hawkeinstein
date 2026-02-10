<?php
/**
 * Get Course-Specific Progress
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$userData = requireAuth();
$userId = $_GET['userId'] ?? $userData['userId'];
$courseId = $_GET['courseId'] ?? null;

if (empty($courseId)) {
    sendJSON(['success' => false, 'message' => 'Course ID required'], 400);
}

try {
    $db = getDB();
    
    // Get course assignment info
    $assignmentStmt = $db->prepare("
        SELECT ca.*, c.course_name, c.estimated_hours, a.agent_name
        FROM course_assignments ca
        JOIN courses c ON ca.course_id = c.course_id
        JOIN agents a ON ca.agent_id = a.agent_id
        WHERE ca.user_id = :userId AND ca.course_id = :courseId
    ");
    $assignmentStmt->execute(['userId' => $userId, 'courseId' => $courseId]);
    $assignment = $assignmentStmt->fetch();
    
    if (!$assignment) {
        sendJSON(['success' => false, 'message' => 'Course not assigned'], 404);
    }
    
    // Get progress metrics
    $metricsStmt = $db->prepare("
        SELECT 
            metric_type,
            metric_value,
            milestone,
            recorded_at
        FROM progress_tracking
        WHERE user_id = :userId AND course_id = :courseId
        ORDER BY recorded_at DESC
        LIMIT 10
    ");
    $metricsStmt->execute(['userId' => $userId, 'courseId' => $courseId]);
    $metrics = $metricsStmt->fetchAll();
    
    sendJSON([
        'success' => true,
        'course' => [
            'courseId' => $assignment['course_id'],
            'courseName' => $assignment['course_name'],
            'agentName' => $assignment['agent_name'],
            'status' => $assignment['status'],
            'assignedAt' => $assignment['assigned_at'],
            'startedAt' => $assignment['started_at'],
            'estimatedHours' => (float)$assignment['estimated_hours']
        ],
        'progress' => $metrics
    ]);
    
} catch (Exception $e) {
    error_log("Course progress error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to fetch course progress'], 500);
}
