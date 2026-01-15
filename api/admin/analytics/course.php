<?php
/**
 * Admin Course Analytics API
 * 
 * Returns course-specific effectiveness metrics
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
    
    $courseId = $_GET['courseId'] ?? null;
    
    // ========================================================================
    // ALL COURSES SUMMARY
    // ========================================================================
    
    if (!$courseId) {
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
        
        sendJSON([
            'success' => true,
            'courses' => $courses
        ]);
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
    
    sendJSON([
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
    ]);
    
} catch (Exception $e) {
    error_log("Course analytics error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to fetch course analytics'], 500);
}
