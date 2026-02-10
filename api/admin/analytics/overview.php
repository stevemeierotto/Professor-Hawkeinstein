<?php
/**
 * 🔒 PRIVACY REGRESSION PROTECTED
 * Changes to this file require privacy review (see docs/ANALYTICS_PRIVACY_VALIDATION.md)
 * Required guards: Phase 2 (PII), Phase 3 (Cohort), Phase 4 (Rate limit, Audit)
 * 
 * Admin Analytics Overview API
 * 
 * Returns platform-wide aggregate metrics for admin dashboard
 * Authorization: Admin only
 */

define('APP_ROOT', '/var/www/html/basic_educational');
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/api/admin/auth_check.php';
require_once APP_ROOT . '/api/helpers/analytics_response_guard.php';
require_once APP_ROOT . '/api/helpers/analytics_cohort_guard.php';
require_once APP_ROOT . '/api/helpers/analytics_rate_limiter.php';
require_once APP_ROOT . '/api/helpers/analytics_audit_log.php';

setCORSHeaders();

// Security headers
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Require admin authentication
$adminUser = requireAdmin();
$userId = $adminUser['user_id'] ?? 'unknown';

// Enforce rate limit for admin analytics
try {
    enforceRateLimit($userId, ADMIN_ANALYTICS_RATE_LIMIT, 'admin_analytics_overview');
} catch (RateLimitExceededException $e) {
    logAnalyticsAccessFailure('admin_analytics_overview', 'Rate limit exceeded', $userId);
    http_response_code(429);
    echo json_encode($e->toResponse());
    exit;
}

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
    
    // Try analytics_daily_rollup first, fallback to raw data if empty
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
    
    // If no rollup data exists, compute directly from progress_tracking
    if (empty($engagementData['total_active']) || $engagementData['total_active'] === null) {
        $rawEngagementStmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as total_active,
                COUNT(CASE WHEN metric_type = 'completion' THEN 1 END) as lessons_completed,
                COUNT(CASE WHEN metric_type = 'mastery' AND metric_value >= 70 THEN 1 END) as quizzes_passed,
                SUM(CASE WHEN metric_type = 'time_spent' THEN metric_value ELSE 0 END) / 60 as total_study_hours,
                AVG(CASE WHEN metric_type = 'mastery' THEN metric_value END) as avg_mastery,
                COUNT(CASE WHEN metric_type = 'mastery' AND metric_value >= 90 THEN 1 END) as high_achievers
            FROM progress_tracking
            WHERE DATE(recorded_at) BETWEEN :start_date AND :end_date
        ");
        $rawEngagementStmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $rawData = $rawEngagementStmt->fetch();
        
        $engagementData = [
            'total_active' => $rawData['total_active'] ?? 0,
            'new_registrations' => 0, // Will calculate separately
            'lessons_completed' => $rawData['lessons_completed'] ?? 0,
            'quizzes_passed' => $rawData['quizzes_passed'] ?? 0,
            'total_study_hours' => $rawData['total_study_hours'] ?? 0,
            'avg_mastery' => $rawData['avg_mastery'] ?? 0,
            'high_achievers' => $rawData['high_achievers'] ?? 0,
            'agent_interactions' => 0 // Will calculate from agent_memories
        ];
        
        // Get agent interactions count
        $agentCountStmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM agent_memories
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date
        ");
        $agentCountStmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $engagementData['agent_interactions'] = $agentCountStmt->fetch()['count'] ?? 0;
    }
    
    // ========================================================================
    // MASTERY DISTRIBUTION
    // ========================================================================
    
    // Try analytics rollup first
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
    
    // Default to zeros if no data
    if (empty($masteryDist['below_50']) && empty($masteryDist['range_50_69']) && 
        empty($masteryDist['range_70_89']) && empty($masteryDist['range_90_plus'])) {
        $masteryDist = [
            'below_50' => 0,
            'range_50_69' => 0,
            'range_70_89' => 0,
            'range_90_plus' => 0
        ];
    }
    
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
    
    // Try analytics_course_metrics first, fallback to direct calculation
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
    
    // If no metrics exist, calculate directly
    if (empty($topCourses)) {
        $directCoursesStmt = $db->prepare("
            SELECT 
                c.course_id,
                c.course_name,
                AVG(CASE WHEN pt.metric_type = 'mastery' THEN pt.metric_value END) as avg_mastery_score,
                COUNT(DISTINCT ca.user_id) as total_enrolled,
                (COUNT(DISTINCT CASE WHEN ca.status = 'completed' THEN ca.user_id END) * 100.0 / 
                 NULLIF(COUNT(DISTINCT ca.user_id), 0)) as completion_rate
            FROM courses c
            LEFT JOIN course_assignments ca ON c.course_id = ca.course_id
            LEFT JOIN progress_tracking pt ON c.course_id = pt.course_id
            WHERE c.is_active = 1
            GROUP BY c.course_id, c.course_name
            HAVING total_enrolled > 0
            ORDER BY avg_mastery_score DESC
            LIMIT 5
        ");
        $directCoursesStmt->execute();
        $topCourses = $directCoursesStmt->fetchAll();
    }
    
    // ========================================================================
    // AGENT PERFORMANCE
    // ========================================================================
    
    // Try analytics_agent_metrics first, fallback to direct calculation
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
    
    // If no metrics exist, calculate directly from agent_memories
    if (empty($topAgents)) {
        $directAgentsStmt = $db->prepare("
            SELECT 
                a.agent_id,
                a.agent_name,
                COUNT(am.memory_id) as total_interactions,
                COUNT(DISTINCT am.user_id) as unique_users_served,
                AVG(pt.metric_value) as avg_student_mastery
            FROM agents a
            LEFT JOIN agent_memories am ON a.agent_id = am.agent_id
            LEFT JOIN progress_tracking pt ON am.user_id = pt.user_id AND pt.metric_type = 'mastery'
            WHERE a.is_active = 1
            GROUP BY a.agent_id, a.agent_name
            HAVING total_interactions > 0
            ORDER BY total_interactions DESC
            LIMIT 5
        ");
        $directAgentsStmt->execute();
        $topAgents = $directAgentsStmt->fetchAll();
    }
    
    // ========================================================================
    // BUILD RESPONSE
    // ========================================================================
    
    $parameters = [
        'startDate' => $startDate,
        'endDate' => $endDate
    ];
    
    $response = [
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
    ];
    
    // Log successful access
    logAnalyticsAccess('admin_analytics_overview', 'view_dashboard', $userId, 'admin', $parameters, true);
    
    sendProtectedAnalyticsJSON($response, 200, 'admin_analytics_overview');
    
} catch (Exception $e) {
    error_log("Analytics overview error: " . $e->getMessage());
    logAnalyticsAccessFailure('admin_analytics_overview', 'Exception: ' . $e->getMessage(), $userId ?? 'unknown');
    sendJSON(['success' => false, 'message' => 'Failed to fetch analytics'], 500);
}
