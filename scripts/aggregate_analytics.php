#!/usr/bin/env php
<?php
/**
 * Analytics Aggregation Script
 * 
 * Populates rollup tables from raw progress_tracking data
 * Run via cron: 0 1 * * * /usr/bin/php /home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php
 * 
 * Purpose:
 * - Daily rollups for platform-wide metrics
 * - Course effectiveness calculations
 * - Agent performance metrics
 * - Anonymized user snapshots
 * - Public metrics updates
 * 
 * Privacy: Operates on aggregate data only, no PII exposure
 */

define('APP_ROOT', '/home/steve/Professor_Hawkeinstein');
require_once APP_ROOT . '/config/database.php';

// Log output
$logFile = '/tmp/analytics_aggregation.log';
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

logMessage("=== Analytics Aggregation Started ===");

try {
    $db = getDB();
    $targetDate = date('Y-m-d', strtotime('-1 day')); // Process yesterday's data
    
    // ========================================================================
    // 1. DAILY ROLLUP - Platform-wide metrics
    // ========================================================================
    
    logMessage("Processing daily rollup for $targetDate...");
    
    // Count active users (users with any activity)
    $activeUsersStmt = $db->prepare("
        SELECT COUNT(DISTINCT user_id) as count
        FROM progress_tracking
        WHERE DATE(recorded_at) = :target_date
    ");
    $activeUsersStmt->execute(['target_date' => $targetDate]);
    $activeUsers = $activeUsersStmt->fetch()['count'];
    
    // Count new users
    $newUsersStmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM users
        WHERE DATE(created_at) = :target_date
    ");
    $newUsersStmt->execute(['target_date' => $targetDate]);
    $newUsers = $newUsersStmt->fetch()['count'];
    
    // Active course enrollments
    $activeEnrollmentsStmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM course_assignments
        WHERE (started_at IS NULL OR DATE(started_at) <= :target_date1)
        AND (completed_at IS NULL OR DATE(completed_at) >= :target_date2)
        AND status IN ('assigned', 'in_progress')
    ");
    $activeEnrollmentsStmt->execute(['target_date1' => $targetDate, 'target_date2' => $targetDate]);
    $activeEnrollments = $activeEnrollmentsStmt->fetch()['count'];
    
    // Lessons and quizzes
    $lessonsStmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN metric_type = 'completion' THEN 1 END) as lessons,
            COUNT(CASE WHEN metric_type = 'mastery' THEN 1 END) as quizzes_attempted,
            SUM(CASE WHEN metric_type = 'mastery' AND metric_value >= 70 THEN 1 ELSE 0 END) as quizzes_passed
        FROM progress_tracking
        WHERE DATE(recorded_at) = :target_date
    ");
    $lessonsStmt->execute(['target_date' => $targetDate]);
    $lessonData = $lessonsStmt->fetch();
    
    // Study time
    $studyTimeStmt = $db->prepare("
        SELECT SUM(metric_value) as total_minutes
        FROM progress_tracking
        WHERE DATE(recorded_at) = :target_date
        AND metric_type = 'time_spent'
    ");
    $studyTimeStmt->execute(['target_date' => $targetDate]);
    $studyTime = $studyTimeStmt->fetch()['total_minutes'] ?? 0;
    
    // Mastery metrics
    $masteryStmt = $db->prepare("
        SELECT AVG(metric_value) as avg_mastery,
               COUNT(CASE WHEN metric_value >= 90 THEN 1 END) as high_mastery_count
        FROM progress_tracking
        WHERE DATE(recorded_at) = :target_date
        AND metric_type = 'mastery'
    ");
    $masteryStmt->execute(['target_date' => $targetDate]);
    $masteryData = $masteryStmt->fetch();
    
    // Agent interactions
    $agentStmt = $db->prepare("
        SELECT COUNT(*) as total_messages
        FROM agent_memories
        WHERE DATE(created_at) = :target_date
    ");
    $agentStmt->execute(['target_date' => $targetDate]);
    $agentMessages = $agentStmt->fetch()['total_messages'];
    
    // Insert daily rollup
    $rollupStmt = $db->prepare("
        INSERT INTO analytics_daily_rollup (
            rollup_date, total_active_users, new_users, active_course_enrollments,
            lessons_completed, quizzes_attempted, quizzes_passed, total_study_time_minutes,
            avg_mastery_score, mastery_90_plus_count, total_agent_messages
        ) VALUES (
            :rollup_date, :active_users, :new_users, :enrollments,
            :lessons, :quizzes_attempted, :quizzes_passed, :study_time,
            :avg_mastery, :high_mastery, :agent_messages
        ) ON DUPLICATE KEY UPDATE
            total_active_users = VALUES(total_active_users),
            new_users = VALUES(new_users),
            active_course_enrollments = VALUES(active_course_enrollments),
            lessons_completed = VALUES(lessons_completed),
            quizzes_attempted = VALUES(quizzes_attempted),
            quizzes_passed = VALUES(quizzes_passed),
            total_study_time_minutes = VALUES(total_study_time_minutes),
            avg_mastery_score = VALUES(avg_mastery_score),
            mastery_90_plus_count = VALUES(mastery_90_plus_count),
            total_agent_messages = VALUES(total_agent_messages),
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $rollupStmt->execute([
        'rollup_date' => $targetDate,
        'active_users' => $activeUsers,
        'new_users' => $newUsers,
        'enrollments' => $activeEnrollments,
        'lessons' => $lessonData['lessons'],
        'quizzes_attempted' => $lessonData['quizzes_attempted'] ?? 0,
        'quizzes_passed' => $lessonData['quizzes_passed'] ?? 0,
        'study_time' => round($studyTime),
        'avg_mastery' => round($masteryData['avg_mastery'] ?? 0, 2),
        'high_mastery' => $masteryData['high_mastery_count'] ?? 0,
        'agent_messages' => $agentMessages
    ]);
    
    logMessage("Daily rollup completed: $activeUsers active users, $newUsers new users");
    
    // ========================================================================
    // 2. COURSE METRICS - Per-course effectiveness
    // ========================================================================
    
    logMessage("Calculating course metrics...");
    
    $coursesStmt = $db->query("SELECT course_id FROM courses WHERE is_active = 1");
    $courses = $coursesStmt->fetchAll();
    
    foreach ($courses as $course) {
        $courseId = $course['course_id'];
        
        // Enrollment stats
        $enrollStmt = $db->prepare("
            SELECT 
                COUNT(*) as total_enrolled,
                SUM(CASE WHEN status IN ('assigned', 'in_progress') THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM course_assignments
            WHERE course_id = :course_id
        ");
        $enrollStmt->execute(['course_id' => $courseId]);
        $enrollData = $enrollStmt->fetch();
        
        // Completion rate
        $completionRate = $enrollData['total_enrolled'] > 0 
            ? ($enrollData['completed'] / $enrollData['total_enrolled']) * 100 
            : 0;
        
        // Average completion time
        $completionTimeStmt = $db->prepare("
            SELECT AVG(DATEDIFF(completed_at, started_at)) as avg_days
            FROM course_assignments
            WHERE course_id = :course_id AND completed_at IS NOT NULL
        ");
        $completionTimeStmt->execute(['course_id' => $courseId]);
        $avgCompletionDays = $completionTimeStmt->fetch()['avg_days'] ?? 0;
        
        // Mastery stats
        $masteryStmt = $db->prepare("
            SELECT AVG(metric_value) as avg_mastery
            FROM progress_tracking
            WHERE course_id = :course_id AND metric_type = 'mastery'
        ");
        $masteryStmt->execute(['course_id' => $courseId]);
        $avgMastery = $masteryStmt->fetch()['avg_mastery'] ?? 0;
        
        // Study time
        $studyStmt = $db->prepare("
            SELECT AVG(metric_value) / 60 as avg_hours
            FROM progress_tracking
            WHERE course_id = :course_id AND metric_type = 'time_spent'
        ");
        $studyStmt->execute(['course_id' => $courseId]);
        $avgStudyHours = $studyStmt->fetch()['avg_hours'] ?? 0;
        
        // Insert course metrics
        $courseMetricStmt = $db->prepare("
            INSERT INTO analytics_course_metrics (
                course_id, calculation_date, total_enrolled, active_students,
                completed_students, completion_rate, avg_completion_time_days,
                avg_mastery_score, avg_study_time_hours
            ) VALUES (
                :course_id, :calc_date, :enrolled, :active,
                :completed, :completion_rate, :avg_days,
                :avg_mastery, :avg_hours
            ) ON DUPLICATE KEY UPDATE
                total_enrolled = VALUES(total_enrolled),
                active_students = VALUES(active_students),
                completed_students = VALUES(completed_students),
                completion_rate = VALUES(completion_rate),
                avg_completion_time_days = VALUES(avg_completion_time_days),
                avg_mastery_score = VALUES(avg_mastery_score),
                avg_study_time_hours = VALUES(avg_study_time_hours),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $courseMetricStmt->execute([
            'course_id' => $courseId,
            'calc_date' => $targetDate,
            'enrolled' => $enrollData['total_enrolled'],
            'active' => $enrollData['active'],
            'completed' => $enrollData['completed'],
            'completion_rate' => round($completionRate, 2),
            'avg_days' => round($avgCompletionDays, 2),
            'avg_mastery' => round($avgMastery, 2),
            'avg_hours' => round($avgStudyHours, 2)
        ]);
    }
    
    logMessage("Course metrics completed for " . count($courses) . " courses");
    
    // ========================================================================
    // 3. AGENT METRICS - Per-agent effectiveness
    // ========================================================================
    
    logMessage("Calculating agent metrics...");
    
    $agentsStmt = $db->query("SELECT agent_id FROM agents WHERE is_active = 1");
    $agents = $agentsStmt->fetchAll();
    
    foreach ($agents as $agent) {
        $agentId = $agent['agent_id'];
        
        // Interaction volume
        $interactionStmt = $db->prepare("
            SELECT 
                COUNT(*) as total_interactions,
                COUNT(DISTINCT user_id) as unique_users
            FROM agent_memories
            WHERE agent_id = :agent_id AND DATE(created_at) = :target_date
        ");
        $interactionStmt->execute(['agent_id' => $agentId, 'target_date' => $targetDate]);
        $interactionData = $interactionStmt->fetch();
        
        // Student mastery (students who interacted with this agent)
        $masteryStmt = $db->prepare("
            SELECT AVG(pt.metric_value) as avg_mastery
            FROM progress_tracking pt
            INNER JOIN agent_memories am ON pt.user_id = am.user_id
            WHERE am.agent_id = :agent_id 
            AND pt.metric_type = 'mastery'
            AND DATE(am.created_at) <= :target_date
        ");
        $masteryStmt->execute(['agent_id' => $agentId, 'target_date' => $targetDate]);
        $agentMastery = $masteryStmt->fetch()['avg_mastery'] ?? 0;
        
        // Insert agent metrics
        $agentMetricStmt = $db->prepare("
            INSERT INTO analytics_agent_metrics (
                agent_id, calculation_date, total_interactions, unique_users_served,
                avg_student_mastery
            ) VALUES (
                :agent_id, :calc_date, :interactions, :users, :mastery
            ) ON DUPLICATE KEY UPDATE
                total_interactions = VALUES(total_interactions),
                unique_users_served = VALUES(unique_users_served),
                avg_student_mastery = VALUES(avg_student_mastery),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $agentMetricStmt->execute([
            'agent_id' => $agentId,
            'calc_date' => $targetDate,
            'interactions' => $interactionData['total_interactions'],
            'users' => $interactionData['unique_users'],
            'mastery' => round($agentMastery, 2)
        ]);
    }
    
    logMessage("Agent metrics completed for " . count($agents) . " agents");
    
    // ========================================================================
    // 4. PUBLIC METRICS UPDATE - Cached values for public display
    // ========================================================================
    
    logMessage("Updating public metrics...");
    
    // Total learners
    $totalLearnersStmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $totalLearners = $totalLearnersStmt->fetch()['count'];
    
    // Average mastery improvement (comparing first and last mastery scores)
    $improvementStmt = $db->query("
        SELECT AVG(improvement) as avg_improvement
        FROM (
            SELECT 
                user_id,
                (MAX(metric_value) - MIN(metric_value)) as improvement
            FROM progress_tracking
            WHERE metric_type = 'mastery'
            GROUP BY user_id
            HAVING COUNT(*) > 1
        ) as improvements
    ");
    $avgImprovement = $improvementStmt->fetch()['avg_improvement'] ?? 0;
    
    // Course completion rate
    $completionStmt = $db->query("
        SELECT 
            (SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as rate
        FROM course_assignments
    ");
    $completionRate = $completionStmt->fetch()['rate'] ?? 0;
    
    // Total study hours
    $totalHoursStmt = $db->query("
        SELECT SUM(metric_value) / 60 as hours
        FROM progress_tracking
        WHERE metric_type = 'time_spent'
    ");
    $totalHours = $totalHoursStmt->fetch()['hours'] ?? 0;
    
    // Active courses
    $activeCoursesStmt = $db->query("SELECT COUNT(*) as count FROM courses WHERE is_active = 1");
    $activeCourses = $activeCoursesStmt->fetch()['count'];
    
    // Lessons completed
    $lessonsCompletedStmt = $db->query("
        SELECT COUNT(*) as count
        FROM progress_tracking
        WHERE metric_type = 'completion'
    ");
    $lessonsCompleted = $lessonsCompletedStmt->fetch()['count'];
    
    // Average quiz score
    $avgQuizStmt = $db->query("
        SELECT AVG(metric_value) as avg_score
        FROM progress_tracking
        WHERE metric_type = 'mastery'
    ");
    $avgQuizScore = $avgQuizStmt->fetch()['avg_score'] ?? 0;
    
    // Update public metrics
    $publicMetrics = [
        'total_learners' => $totalLearners,
        'avg_mastery_improvement' => round($avgImprovement, 2),
        'course_completion_rate' => round($completionRate, 2),
        'total_study_hours' => round($totalHours, 0),
        'active_courses' => $activeCourses,
        'lessons_completed' => $lessonsCompleted,
        'avg_quiz_score' => round($avgQuizScore, 2)
    ];
    
    $updateMetricStmt = $db->prepare("
        UPDATE analytics_public_metrics
        SET metric_value = :value, last_updated = CURRENT_TIMESTAMP
        WHERE metric_key = :key
    ");
    
    foreach ($publicMetrics as $key => $value) {
        $updateMetricStmt->execute(['key' => $key, 'value' => $value]);
    }
    
    logMessage("Public metrics updated: $totalLearners learners, " . round($completionRate, 1) . "% completion rate");
    
    // ========================================================================
    // 5. ANONYMIZED USER SNAPSHOTS - For research exports
    // ========================================================================
    
    logMessage("Creating anonymized user snapshots...");
    
    $studentsStmt = $db->query("SELECT user_id FROM users WHERE role = 'student'");
    $students = $studentsStmt->fetchAll();
    
    foreach ($students as $student) {
        $userId = $student['user_id'];
        $userHash = hash('sha256', $userId . '_' . date('Y-m-d')); // Daily rotating hash
        
        // Get user progress data
        $progressStmt = $db->prepare("
            SELECT
                COUNT(DISTINCT ca.course_id) as courses_enrolled,
                SUM(CASE WHEN ca.status = 'completed' THEN 1 ELSE 0 END) as courses_completed,
                SUM(CASE WHEN pt.metric_type = 'time_spent' THEN pt.metric_value ELSE 0 END) / 60 as study_hours,
                AVG(CASE WHEN pt.metric_type = 'mastery' THEN pt.metric_value ELSE NULL END) as avg_mastery,
                COUNT(DISTINCT DATE(pt.recorded_at)) as days_active,
                SUM(CASE WHEN pt.metric_type = 'completion' THEN 1 ELSE 0 END) as lessons_completed,
                COUNT(CASE WHEN pt.metric_type = 'mastery' THEN 1 END) as quizzes_attempted
            FROM users u
            LEFT JOIN course_assignments ca ON u.user_id = ca.user_id
            LEFT JOIN progress_tracking pt ON u.user_id = pt.user_id
            WHERE u.user_id = :user_id
            GROUP BY u.user_id
        ");
        $progressStmt->execute(['user_id' => $userId]);
        $progressData = $progressStmt->fetch();
        
        if ($progressData && $progressData['courses_enrolled'] > 0) {
            $snapshotStmt = $db->prepare("
                INSERT INTO analytics_user_snapshots (
                    user_hash, snapshot_date, courses_enrolled, courses_completed,
                    total_study_hours, avg_mastery_score, days_active,
                    total_lessons_completed, total_quizzes_attempted
                ) VALUES (
                    :user_hash, :snapshot_date, :courses_enrolled, :courses_completed,
                    :study_hours, :avg_mastery, :days_active,
                    :lessons_completed, :quizzes_attempted
                )
            ");
            
            $snapshotStmt->execute([
                'user_hash' => $userHash,
                'snapshot_date' => $targetDate,
                'courses_enrolled' => $progressData['courses_enrolled'] ?? 0,
                'courses_completed' => $progressData['courses_completed'] ?? 0,
                'study_hours' => round($progressData['study_hours'] ?? 0, 2),
                'avg_mastery' => round($progressData['avg_mastery'] ?? 0, 2),
                'days_active' => $progressData['days_active'] ?? 0,
                'lessons_completed' => $progressData['lessons_completed'] ?? 0,
                'quizzes_attempted' => $progressData['quizzes_attempted'] ?? 0
            ]);
        }
    }
    
    logMessage("User snapshots created for " . count($students) . " students");
    
    logMessage("=== Analytics Aggregation Completed Successfully ===");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

exit(0);
