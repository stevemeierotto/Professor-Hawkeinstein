<?php
/**
 * Admin Time-Series Analytics API
 * 
 * Returns trend data over time (daily/weekly/monthly)
 * Authorization: Admin only
 */

define('APP_ROOT', '/var/www/html/basic_educational');
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/api/admin/auth_check.php';
require_once APP_ROOT . '/api/helpers/analytics_response_guard.php';
require_once APP_ROOT . '/api/helpers/analytics_cohort_guard.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Require admin authentication
requireAdmin();

try {
    $db = getDB();
    
    $metric = $_GET['metric'] ?? 'all'; // 'users', 'mastery', 'engagement', 'all'
    $period = $_GET['period'] ?? 'daily'; // 'daily', 'weekly', 'monthly'
    $startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['endDate'] ?? date('Y-m-d');
    
    // ========================================================================
    // DAILY ROLLUP TIME-SERIES
    // ========================================================================
    
    if ($period === 'daily') {
        $query = "
            SELECT 
                rollup_date as date,
                total_active_users,
                new_users,
                active_course_enrollments,
                lessons_completed,
                quizzes_passed,
                total_study_time_minutes,
                avg_mastery_score,
                mastery_90_plus_count,
                total_agent_messages
            FROM analytics_daily_rollup
            WHERE rollup_date BETWEEN :start_date AND :end_date
            ORDER BY rollup_date ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $data = $stmt->fetchAll();
        
        // If no rollup data, generate stub data to prevent frontend errors
        if (empty($data)) {
            $data = [[
                'date' => date('Y-m-d'),
                'total_active_users' => 0,
                'new_users' => 0,
                'active_course_enrollments' => 0,
                'lessons_completed' => 0,
                'quizzes_passed' => 0,
                'total_study_time_minutes' => 0,
                'avg_mastery_score' => 0,
                'mastery_90_plus_count' => 0,
                'total_agent_messages' => 0
            ]];
        }
        
        sendProtectedAnalyticsJSON([
            'success' => true,
            'period' => 'daily',
            'dateRange' => ['start' => $startDate, 'end' => $endDate],
            'data' => $data
        ], 200, 'admin_analytics_timeseries_daily');
        return;
    }
    
    // ========================================================================
    // WEEKLY AGGREGATION
    // ========================================================================
    
    if ($period === 'weekly') {
        $query = "
            SELECT 
                DATE_FORMAT(rollup_date, '%Y-%U') as week,
                DATE(DATE_SUB(rollup_date, INTERVAL WEEKDAY(rollup_date) DAY)) as week_start,
                SUM(total_active_users) as total_active_users,
                SUM(new_users) as new_users,
                SUM(lessons_completed) as lessons_completed,
                SUM(quizzes_passed) as quizzes_passed,
                SUM(total_study_time_minutes) as total_study_time_minutes,
                AVG(avg_mastery_score) as avg_mastery_score,
                SUM(mastery_90_plus_count) as mastery_90_plus_count,
                SUM(total_agent_messages) as total_agent_messages
            FROM analytics_daily_rollup
            WHERE rollup_date BETWEEN :start_date AND :end_date
            GROUP BY week, week_start
            ORDER BY week_start ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $data = $stmt->fetchAll();
        
        sendProtectedAnalyticsJSON([
            'success' => true,
            'period' => 'weekly',
            'dateRange' => ['start' => $startDate, 'end' => $endDate],
            'data' => $data
        ], 200, 'admin_analytics_timeseries_weekly');
        return;
    }
    
    // ========================================================================
    // MONTHLY AGGREGATION
    // ========================================================================
    
    if ($period === 'monthly') {
        $query = "
            SELECT 
                DATE_FORMAT(rollup_date, '%Y-%m') as month,
                DATE_FORMAT(rollup_date, '%Y-%m-01') as month_start,
                SUM(total_active_users) as total_active_users,
                SUM(new_users) as new_users,
                SUM(lessons_completed) as lessons_completed,
                SUM(quizzes_passed) as quizzes_passed,
                SUM(total_study_time_minutes) as total_study_time_minutes,
                AVG(avg_mastery_score) as avg_mastery_score,
                SUM(mastery_90_plus_count) as mastery_90_plus_count,
                SUM(total_agent_messages) as total_agent_messages
            FROM analytics_daily_rollup
            WHERE rollup_date BETWEEN :start_date AND :end_date
            GROUP BY month, month_start
            ORDER BY month_start ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $data = $stmt->fetchAll();
        
        sendProtectedAnalyticsJSON([
            'success' => true,
            'period' => 'monthly',
            'dateRange' => ['start' => $startDate, 'end' => $endDate],
            'data' => $data
        ], 200, 'admin_analytics_timeseries_monthly');
        return;
    }
    
    sendJSON(['success' => false, 'message' => 'Invalid period'], 400);
    
} catch (Exception $e) {
    error_log("Time-series analytics error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to fetch time-series data'], 500);
}
