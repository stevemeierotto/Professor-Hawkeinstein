<?php
/**
 * 🔒 PRIVACY REGRESSION PROTECTED
 * Changes to this file require privacy review (see docs/ANALYTICS_PRIVACY_VALIDATION.md)
 * Required guards: Phase 2 (PII), Phase 3 (Cohort), Phase 4 (Rate limit, Audit)
 * 
 * Admin Course Analytics API
 * 
 * Returns course-specific effectiveness metrics
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
    enforceRateLimit($userId, ADMIN_ANALYTICS_RATE_LIMIT, 'admin_analytics_course');
} catch (RateLimitExceededException $e) {
    logAnalyticsAccessFailure('admin_analytics_course', 'Rate limit exceeded', $userId);
    http_response_code(429);
    echo json_encode($e->toResponse());
    exit;
}

try {
    $db = getDB();
    
    $courseId = $_GET['courseId'] ?? null;
    
    // ========================================================================
    // ALL COURSES SUMMARY
    // ========================================================================
    
    if (!$courseId) {
        // Try analytics_course_metrics first, fallback to direct calculation
        $coursesStmt = $db->query("
            SELECT 
                c.course_id,
                c.course_name,
                c.difficulty_level,
                c.subject_area,
                acm.total_enrolled,
                acm.active_students,
                acm.completed_students,
                acm.completion_rate,
                acm.avg_completion_time_days,
                acm.avg_mastery_score,
                acm.avg_study_time_hours,
                acm.calculation_date
            FROM courses c
            LEFT JOIN analytics_course_metrics acm ON c.course_id = acm.course_id
            WHERE c.is_active = 1
            AND (acm.calculation_date = (
                SELECT MAX(calculation_date) 
                FROM analytics_course_metrics 
                WHERE course_id = c.course_id
            ) OR acm.calculation_date IS NULL)
            ORDER BY c.course_name
        ");
        
        $courses = $coursesStmt->fetchAll();
        
        // If no analytics data, calculate directly
        if (empty($courses) || (count($courses) > 0 && empty($courses[0]['total_enrolled']))) {
            $directCoursesStmt = $db->query("
                SELECT 
                    c.course_id,
                    c.course_name,
                    c.difficulty_level,
                    c.subject_area,
                    COUNT(DISTINCT ca.user_id) as total_enrolled,
                    COUNT(DISTINCT CASE WHEN ca.status = 'in_progress' THEN ca.user_id END) as active_students,
                    COUNT(DISTINCT CASE WHEN ca.status = 'completed' THEN ca.user_id END) as completed_students,
                    (COUNT(DISTINCT CASE WHEN ca.status = 'completed' THEN ca.user_id END) * 100.0 / 
                     NULLIF(COUNT(DISTINCT ca.user_id), 0)) as completion_rate,
                    AVG(CASE WHEN pt.metric_type = 'mastery' THEN pt.metric_value END) as avg_mastery_score,
                    AVG(CASE WHEN pt.metric_type = 'time_spent' THEN pt.metric_value END) / 60 as avg_study_time_hours,
                    NULL as avg_completion_time_days,
                    CURDATE() as calculation_date
                FROM courses c
                LEFT JOIN course_assignments ca ON c.course_id = ca.course_id
                LEFT JOIN progress_tracking pt ON c.course_id = pt.course_id
                WHERE c.is_active = 1
                GROUP BY c.course_id, c.course_name, c.difficulty_level, c.subject_area
                ORDER BY c.course_name
            ");
            $courses = $directCoursesStmt->fetchAll();
        }
        
        // Log successful access
        logAnalyticsAccess('admin_analytics_course', 'list_courses', $userId, 'admin', [], true);
        
        sendProtectedAnalyticsJSON([
            'success' => true,
            'courses' => $courses
        ], 200, 'admin_analytics_courses_list');
        return;
    }
    
    // ========================================================================
    // SPECIFIC COURSE DETAILS
    // ========================================================================
    
    // Course basic info
    $courseStmt = $db->prepare("
        SELECT 
            course_id,
            course_name,
            course_description,
            difficulty_level,
            subject_area,
            estimated_hours,
            is_active
        FROM courses
        WHERE course_id = :course_id
    ");
    $courseStmt->execute(['course_id' => $courseId]);
    $course = $courseStmt->fetch();
    
    if (!$course) {
        sendJSON(['success' => false, 'message' => 'Course not found'], 404);
    }
    
    // Latest metrics
    $metricsStmt = $db->prepare("
        SELECT *
        FROM analytics_course_metrics
        WHERE course_id = :course_id
        ORDER BY calculation_date DESC
        LIMIT 1
    ");
    $metricsStmt->execute(['course_id' => $courseId]);
    $metrics = $metricsStmt->fetch();
    
    // Historical trend (last 30 days)
    $trendStmt = $db->prepare("
        SELECT 
            calculation_date,
            avg_mastery_score,
            completion_rate,
            active_students
        FROM analytics_course_metrics
        WHERE course_id = :course_id
        AND calculation_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY calculation_date ASC
    ");
    $trendStmt->execute(['course_id' => $courseId]);
    $trend = $trendStmt->fetchAll();
    
    // Student list (anonymized summary)
    $studentsStmt = $db->prepare("
        SELECT 
            ca.status,
            ca.assigned_at,
            ca.started_at,
            ca.completed_at,
            DATEDIFF(COALESCE(ca.completed_at, NOW()), ca.started_at) as days_enrolled,
            AVG(pt.metric_value) as avg_mastery
        FROM course_assignments ca
        LEFT JOIN progress_tracking pt ON ca.user_id = pt.user_id 
            AND ca.course_id = pt.course_id 
            AND pt.metric_type = 'mastery'
        WHERE ca.course_id = :course_id
        GROUP BY ca.assignment_id
        ORDER BY ca.assigned_at DESC
    ");
    $studentsStmt->execute(['course_id' => $courseId]);
    $students = $studentsStmt->fetchAll();
    
    // Lesson completion breakdown
    $lessonsStmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT pt.user_id) as students_completed,
            pt.milestone as lesson_name
        FROM progress_tracking pt
        WHERE pt.course_id = :course_id
        AND pt.metric_type = 'completion'
        AND pt.milestone IS NOT NULL
        GROUP BY pt.milestone
        ORDER BY students_completed DESC
    ");
    $lessonsStmt->execute(['course_id' => $courseId]);
    $lessons = $lessonsStmt->fetchAll();
    
    // Agent usage for this course
    $agentUsageStmt = $db->prepare("
        SELECT 
            a.agent_name,
            COUNT(*) as interaction_count,
            COUNT(DISTINCT am.user_id) as unique_students
        FROM agent_memories am
        JOIN agents a ON am.agent_id = a.agent_id
        JOIN course_assignments ca ON am.user_id = ca.user_id AND ca.course_id = :course_id
        GROUP BY a.agent_id, a.agent_name
        ORDER BY interaction_count DESC
    ");
    $agentUsageStmt->execute(['course_id' => $courseId]);
    $agentUsage = $agentUsageStmt->fetchAll();
    
    // Log successful access
    logAnalyticsAccess('admin_analytics_course', 'view_course_detail', $userId, 'admin', ['courseId' => $courseId], true);
    
    sendProtectedAnalyticsJSON([
        'success' => true,
        'course' => $course,
        'currentMetrics' => $metrics,
        'trend' => $trend,
        'studentSummary' => [
            'total' => count($students),
            'byStatus' => [
                'assigned' => count(array_filter($students, fn($s) => $s['status'] === 'assigned')),
                'in_progress' => count(array_filter($students, fn($s) => $s['status'] === 'in_progress')),
                'completed' => count(array_filter($students, fn($s) => $s['status'] === 'completed')),
                'paused' => count(array_filter($students, fn($s) => $s['status'] === 'paused'))
            ],
            'students' => $students
        ],
        'lessonBreakdown' => $lessons,
        'agentUsage' => $agentUsage
    ], 200, 'admin_analytics_course_detail');
    
} catch (Exception $e) {
    error_log("Course analytics error: " . $e->getMessage());
    logAnalyticsAccessFailure('admin_analytics_course', 'Exception: ' . $e->getMessage(), $userId ?? 'unknown');
    sendJSON(['success' => false, 'message' => 'Failed to fetch course analytics'], 500);
}
