#!/usr/bin/env php
<?php
/**
 * Backfill Analytics Data
 * 
 * Processes historical data from progress_tracking to populate analytics tables
 * Run this once to populate existing data, then rely on daily cron job
 */

define('APP_ROOT', '/home/steve/Professor_Hawkeinstein');
require_once APP_ROOT . '/config/database.php';

echo "=== Analytics Backfill Started ===\n\n";

try {
    $db = getDB();
    
    // Find date range of existing progress_tracking data
    $dateRangeStmt = $db->query("
        SELECT 
            MIN(DATE(recorded_at)) as min_date,
            MAX(DATE(recorded_at)) as max_date
        FROM progress_tracking
    ");
    $dateRange = $dateRangeStmt->fetch();
    
    if (!$dateRange['min_date']) {
        echo "No progress tracking data found. Nothing to backfill.\n";
        exit(0);
    }
    
    echo "Found progress data from {$dateRange['min_date']} to {$dateRange['max_date']}\n";
    echo "Processing each day...\n\n";
    
    // Process each day in the range
    $currentDate = new DateTime($dateRange['min_date']);
    $endDate = new DateTime($dateRange['max_date']);
    $processedCount = 0;
    
    while ($currentDate <= $endDate) {
        $targetDate = $currentDate->format('Y-m-d');
        echo "Processing $targetDate... ";
        
        // Check if already processed
        $existsStmt = $db->prepare("SELECT COUNT(*) as count FROM analytics_daily_rollup WHERE rollup_date = :date");
        $existsStmt->execute(['date' => $targetDate]);
        if ($existsStmt->fetch()['count'] > 0) {
            echo "already exists, skipping\n";
            $currentDate->modify('+1 day');
            continue;
        }
        
        // Count active users
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
                rollup_date, total_active_users, new_users,
                lessons_completed, quizzes_attempted, quizzes_passed, total_study_time_minutes,
                avg_mastery_score, mastery_90_plus_count, total_agent_messages
            ) VALUES (
                :rollup_date, :active_users, :new_users,
                :lessons, :quizzes_attempted, :quizzes_passed, :study_time,
                :avg_mastery, :high_mastery, :agent_messages
            )
        ");
        
        $rollupStmt->execute([
            'rollup_date' => $targetDate,
            'active_users' => $activeUsers,
            'new_users' => $newUsers,
            'lessons' => $lessonData['lessons'],
            'quizzes_attempted' => $lessonData['quizzes_attempted'] ?? 0,
            'quizzes_passed' => $lessonData['quizzes_passed'] ?? 0,
            'study_time' => round($studyTime),
            'avg_mastery' => round($masteryData['avg_mastery'] ?? 0, 2),
            'high_mastery' => $masteryData['high_mastery_count'] ?? 0,
            'agent_messages' => $agentMessages
        ]);
        
        echo "✓ ($activeUsers active users, $agentMessages interactions)\n";
        $processedCount++;
        
        $currentDate->modify('+1 day');
    }
    
    echo "\n=== Backfill Complete ===\n";
    echo "Processed $processedCount days of data\n\n";
    
    // Now run course and agent metrics
    echo "Calculating course metrics...\n";
    $courses = $db->query("SELECT course_id FROM courses WHERE is_active = 1")->fetchAll();
    foreach ($courses as $course) {
        // Similar logic as in aggregate_analytics.php for courses
        echo "  Course {$course['course_id']}... ";
        
        $metricsStmt = $db->prepare("
            INSERT INTO analytics_course_metrics (
                course_id, calculation_date, total_enrolled, active_students, completed_students
            ) VALUES (
                :course_id, :calc_date, 
                (SELECT COUNT(DISTINCT user_id) FROM course_assignments WHERE course_id = :course_id2),
                (SELECT COUNT(DISTINCT user_id) FROM course_assignments WHERE course_id = :course_id3 AND status = 'in_progress'),
                (SELECT COUNT(DISTINCT user_id) FROM course_assignments WHERE course_id = :course_id4 AND status = 'completed')
            ) ON DUPLICATE KEY UPDATE
                total_enrolled = VALUES(total_enrolled),
                active_students = VALUES(active_students),
                completed_students = VALUES(completed_students)
        ");
        
        $metricsStmt->execute([
            'course_id' => $course['course_id'],
            'course_id2' => $course['course_id'],
            'course_id3' => $course['course_id'],
            'course_id4' => $course['course_id'],
            'calc_date' => date('Y-m-d')
        ]);
        
        echo "✓\n";
    }
    
    echo "\nCalculating agent metrics...\n";
    $agents = $db->query("SELECT agent_id FROM agents WHERE is_active = 1")->fetchAll();
    foreach ($agents as $agent) {
        echo "  Agent {$agent['agent_id']}... ";
        
        $agentMetricsStmt = $db->prepare("
            INSERT INTO analytics_agent_metrics (
                agent_id, calculation_date, total_interactions, unique_users_served
            ) VALUES (
                :agent_id, :calc_date,
                (SELECT COUNT(*) FROM agent_memories WHERE agent_id = :agent_id2),
                (SELECT COUNT(DISTINCT user_id) FROM agent_memories WHERE agent_id = :agent_id3)
            ) ON DUPLICATE KEY UPDATE
                total_interactions = VALUES(total_interactions),
                unique_users_served = VALUES(unique_users_served)
        ");
        
        $agentMetricsStmt->execute([
            'agent_id' => $agent['agent_id'],
            'agent_id2' => $agent['agent_id'],
            'agent_id3' => $agent['agent_id'],
            'calc_date' => date('Y-m-d')
        ]);
        
        echo "✓\n";
    }
    
    echo "\n✅ All analytics data backfilled successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
