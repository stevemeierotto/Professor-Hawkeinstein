<?php
/**
 * Admin Analytics Overview API
 * 
 * Returns platform-wide aggregate metrics for admin dashboard
 * Authorization: Admin only
 */

define('APP_ROOT', '/var/www/html/basic_educational');
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/api/admin/auth_check.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Require admin authentication
requireAdmin();

try {
    $db = getDB();
    
    // Get date range from query params
    $startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['endDate'] ?? date('Y-m-d');
    
    // ========================================================================
    // PLATFORM HEALTH METRICS
    // ========================================================================
    
    // Total users by role
    $usersStmt = $db->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as total_students,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN DATE(last_login) >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as weekly_active_users
        FROM users
    ");
    $userData = $usersStmt->fetch();
    
    // Active courses
    $coursesStmt = $db->query("
        SELECT 
            COUNT(*) as total_courses,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_courses
        FROM courses
    ");
    $courseData = $coursesStmt->fetch();
    
    // Active agents
    $agentsStmt = $db->query("
        SELECT COUNT(*) as active_agents
        FROM agents
        WHERE is_active = 1
    ");
    $agentData = $agentsStmt->fetch();
    
    // ========================================================================
    // ENGAGEMENT METRICS (Date Range)
    // ========================================================================
    
    $engagementStmt = $db->prepare("
        SELECT 
            SUM(total_active_users) as total_active,
            SUM(new_users) as new_registrations,
            SUM(lessons_completed) as lessons_completed,
            SUM(quizzes_passed) as quizzes_passed,
            SUM(total_study_time_minutes) / 60 as total_study_hours,
            AVG(avg_mastery_score) as avg_mastery,
            SUM(mastery_90_plus_count) as high_achievers,
            SUM(total_agent_messages) as agent_interactions
        FROM analytics_daily_rollup
        WHERE rollup_date BETWEEN :start_date AND :end_date
    ");
    $engagementStmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $engagementData = $engagementStmt->fetch();
    
    // ========================================================================
    // MASTERY DISTRIBUTION
    // ========================================================================
    
    $masteryDistStmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN metric_value < 50 THEN 1 ELSE 0 END) as below_50,
            SUM(CASE WHEN metric_value BETWEEN 50 AND 69 THEN 1 ELSE 0 END) as range_50_69,
            SUM(CASE WHEN metric_value BETWEEN 70 AND 89 THEN 1 ELSE 0 END) as range_70_89,
            SUM(CASE WHEN metric_value >= 90 THEN 1 ELSE 0 END) as range_90_plus
        FROM progress_tracking
        WHERE metric_type = 'mastery'
        AND DATE(recorded_at) BETWEEN :start_date AND :end_date
    ");
    $masteryDistStmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $masteryDist = $masteryDistStmt->fetch();
    
    // ========================================================================
    // RECENT ACTIVITY (Last 24 hours)
    // ========================================================================
    
    $recentStmt = $db->query("
        SELECT 
            COUNT(DISTINCT user_id) as active_last_24h,
            COUNT(*) as total_activities
        FROM progress_tracking
        WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $recentData = $recentStmt->fetch();
    
    // ========================================================================
    // TOP PERFORMING COURSES
    // ========================================================================
    
    $topCoursesStmt = $db->prepare("
        SELECT 
            c.course_id,
            c.course_name,
            acm.avg_mastery_score,
            acm.completion_rate,
            acm.total_enrolled
        FROM analytics_course_metrics acm
        JOIN courses c ON acm.course_id = c.course_id
        WHERE acm.calculation_date = (
            SELECT MAX(calculation_date) FROM analytics_course_metrics WHERE course_id = acm.course_id
        )
        ORDER BY acm.avg_mastery_score DESC
        LIMIT 5
    ");
    $topCoursesStmt->execute();
    $topCourses = $topCoursesStmt->fetchAll();
    
    // ========================================================================
    // AGENT PERFORMANCE
    // ========================================================================
    
    $agentPerfStmt = $db->prepare("
        SELECT 
            a.agent_id,
            a.agent_name,
            aam.total_interactions,
            aam.unique_users_served,
            aam.avg_student_mastery
        FROM analytics_agent_metrics aam
        JOIN agents a ON aam.agent_id = a.agent_id
        WHERE aam.calculation_date = (
            SELECT MAX(calculation_date) FROM analytics_agent_metrics WHERE agent_id = aam.agent_id
        )
        ORDER BY aam.total_interactions DESC
        LIMIT 5
    ");
    $agentPerfStmt->execute();
    $topAgents = $agentPerfStmt->fetchAll();
    
    // ========================================================================
    // BUILD RESPONSE
    // ========================================================================
    
    sendJSON([
        'success' => true,
        'dateRange' => [
            'start' => $startDate,
            'end' => $endDate
        ],
        'platformHealth' => [
            'totalUsers' => (int)$userData['total_users'],
            'totalStudents' => (int)$userData['total_students'],
            'totalAdmins' => (int)$userData['total_admins'],
            'activeUsers' => (int)$userData['active_users'],
            'weeklyActiveUsers' => (int)$userData['weekly_active_users'],
            'totalCourses' => (int)$courseData['total_courses'],
            'activeCourses' => (int)$courseData['active_courses'],
            'activeAgents' => (int)$agentData['active_agents']
        ],
        'engagement' => [
            'totalActive' => (int)$engagementData['total_active'],
            'newRegistrations' => (int)$engagementData['new_registrations'],
            'lessonsCompleted' => (int)$engagementData['lessons_completed'],
            'quizzesPassed' => (int)$engagementData['quizzes_passed'],
            'totalStudyHours' => round($engagementData['total_study_hours'], 1),
            'avgMastery' => round($engagementData['avg_mastery'], 2),
            'highAchievers' => (int)$engagementData['high_achievers'],
            'agentInteractions' => (int)$engagementData['agent_interactions']
        ],
        'masteryDistribution' => [
            'below50' => (int)$masteryDist['below_50'],
            '50to69' => (int)$masteryDist['range_50_69'],
            '70to89' => (int)$masteryDist['range_70_89'],
            '90plus' => (int)$masteryDist['range_90_plus']
        ],
        'recentActivity' => [
            'activeLast24h' => (int)$recentData['active_last_24h'],
            'totalActivities' => (int)$recentData['total_activities']
        ],
        'topCourses' => $topCourses,
        'topAgents' => $topAgents
    ]);
    
} catch (Exception $e) {
    error_log("Analytics overview error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to fetch analytics'], 500);
}
