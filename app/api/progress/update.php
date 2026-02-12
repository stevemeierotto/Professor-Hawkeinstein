<?php
/**
 * Update Progress Metric
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$userData = requireAuth();

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('progress_update');

$input = getJSONInput();

$courseId = $input['courseId'] ?? null;
$metricType = $input['metricType'] ?? null;
$metricValue = $input['metricValue'] ?? null;
$milestone = $input['milestone'] ?? null;
$notes = $input['notes'] ?? '';

if (empty($courseId) || empty($metricType) || $metricValue === null) {
    sendJSON(['success' => false, 'message' => 'Course ID, metric type, and value required'], 400);
}

try {
    $db = getDB();
    
    // Get course assignment
    $assignmentStmt = $db->prepare("
        SELECT agent_id FROM course_assignments
        WHERE user_id = :userId AND course_id = :courseId
    ");
    $assignmentStmt->execute(['userId' => $userData['userId'], 'courseId' => $courseId]);
    $assignment = $assignmentStmt->fetch();
    
    if (!$assignment) {
        sendJSON(['success' => false, 'message' => 'Course not assigned'], 404);
    }
    
    // Insert progress record
    $progressStmt = $db->prepare("
        INSERT INTO progress_tracking
        (user_id, course_id, agent_id, metric_type, metric_value, milestone, notes)
        VALUES (:userId, :courseId, :agentId, :metricType, :metricValue, :milestone, :notes)
    ");
    
    $progressStmt->execute([
        'userId' => $userData['userId'],
        'courseId' => $courseId,
        'agentId' => $assignment['agent_id'],
        'metricType' => $metricType,
        'metricValue' => $metricValue,
        'milestone' => $milestone,
        'notes' => $notes
    ]);
    
    logActivity($userData['userId'], 'PROGRESS_UPDATE', "Updated $metricType for course $courseId");
    
    sendJSON([
        'success' => true,
        'message' => 'Progress updated successfully',
        'progressId' => $db->lastInsertId()
    ]);
    
} catch (Exception $e) {
    error_log("Progress update error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to update progress'], 500);
}
