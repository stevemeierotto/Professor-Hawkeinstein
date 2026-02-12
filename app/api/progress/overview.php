<?php
/**
 * Get Student Progress Overview
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$userData = requireAuth();

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('progress_overview');

$userId = $_GET['userId'] ?? $userData['userId'];

try {
    $db = getDB();
    
    // Get active courses count
    $coursesStmt = $db->prepare("
        SELECT COUNT(*) as active_courses
        FROM course_assignments
        WHERE user_id = :userId AND status IN ('assigned', 'in_progress')
    ");
    $coursesStmt->execute(['userId' => $userId]);
    $coursesData = $coursesStmt->fetch();
    
    // Get overall mastery (average of completion metrics)
    $masteryStmt = $db->prepare("
        SELECT AVG(metric_value) as overall_mastery
        FROM progress_tracking
        WHERE user_id = :userId AND metric_type = 'mastery'
    ");
    $masteryStmt->execute(['userId' => $userId]);
    $masteryData = $masteryStmt->fetch();
    
    // Get study time this month
    $studyTimeStmt = $db->prepare("
        SELECT SUM(metric_value) as study_time
        FROM progress_tracking
        WHERE user_id = :userId 
        AND metric_type = 'time_spent'
        AND recorded_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
    ");
    $studyTimeStmt->execute(['userId' => $userId]);
    $studyTimeData = $studyTimeStmt->fetch();
    
    // Get milestones count
    $milestonesStmt = $db->prepare("
        SELECT COUNT(DISTINCT milestone) as milestones
        FROM progress_tracking
        WHERE user_id = :userId AND milestone IS NOT NULL AND milestone != ''
    ");
    $milestonesStmt->execute(['userId' => $userId]);
    $milestonesData = $milestonesStmt->fetch();
    
    // Get strengths and weaknesses
    $strengthsStmt = $db->prepare("
        SELECT strengths, weaknesses
        FROM progress_tracking
        WHERE user_id = :userId
        ORDER BY recorded_at DESC
        LIMIT 1
    ");
    $strengthsStmt->execute(['userId' => $userId]);
    $swData = $strengthsStmt->fetch();
    
    sendJSON([
        'success' => true,
        'overview' => [
            'activeCourses' => (int)$coursesData['active_courses'],
            'overallMastery' => round((float)$masteryData['overall_mastery'], 2),
            'studyTimeHours' => round((float)$studyTimeData['study_time'], 1),
            'milestones' => (int)$milestonesData['milestones'],
            'strengths' => json_decode($swData['strengths'] ?? '[]', true),
            'weaknesses' => json_decode($swData['weaknesses'] ?? '[]', true)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Progress overview error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to fetch progress'], 500);
}
