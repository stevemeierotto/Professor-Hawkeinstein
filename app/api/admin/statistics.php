<?php
/**
 * Admin Statistics API
 * Returns dashboard statistics
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$admin = requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_statistics');

$db = getDB();

// Get total agents
$agentResult = $db->query("SELECT COUNT(*) as count FROM agents");
$totalAgents = $agentResult->fetch()['count'];

// Get total active courses
$courseResult = $db->query("SELECT COUNT(*) as count FROM courses WHERE is_active = 1");
$totalCourses = $courseResult->fetch()['count'];

// Get total students
$studentResult = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$totalStudents = $studentResult->fetch()['count'];

// Get pending content reviews (if table exists)
$pendingReviews = 0;
$tableCheck = $db->query("SHOW TABLES LIKE 'educational_content'");
if ($tableCheck->fetch()) {
    $reviewResult = $db->query("SELECT COUNT(*) as count FROM educational_content WHERE review_status = 'pending'");
    if ($reviewResult) {
        $pendingReviews = $reviewResult->fetch()['count'];
    }
}

echo json_encode([
    'total_agents' => $totalAgents,
    'total_courses' => $totalCourses,
    'total_students' => $totalStudents,
    'pending_reviews' => $pendingReviews
]);
