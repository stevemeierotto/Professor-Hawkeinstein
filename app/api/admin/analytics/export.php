<?php
/**
 * 🔒 PRIVACY REGRESSION PROTECTED
 * Changes to this file require privacy review (see docs/ANALYTICS_PRIVACY_VALIDATION.md)
 * Required guards: Phase 2 (PII), Phase 3 (Cohort), Phase 4 (Rate limit, Audit, Export)
 * 
 * Anonymized Data Export API
 * 
 * Exports research-friendly data with proper anonymization
 * Authorization: Admin only
 * Formats: CSV, JSON
 */

define('APP_ROOT', '/var/www/html/basic_educational');
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/api/admin/auth_check.php';
require_once APP_ROOT . '/api/helpers/analytics_response_guard.php';
require_once APP_ROOT . '/api/helpers/analytics_cohort_guard.php';
require_once APP_ROOT . '/api/helpers/analytics_rate_limiter.php';
require_once APP_ROOT . '/api/helpers/analytics_audit_log.php';
require_once APP_ROOT . '/api/helpers/analytics_export_guard.php';

setCORSHeaders();

// Add security headers
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Require admin authentication
$adminUser = requireAdmin();
$userId = $adminUser['user_id'] ?? 'unknown';

// Enforce rate limiting for admin exports (more generous than public)
try {
    enforceRateLimit($userId, ADMIN_ANALYTICS_RATE_LIMIT, 'admin_analytics_export');
} catch (RateLimitExceededException $e) {
    logAnalyticsAccessFailure('admin_analytics_export', 'Rate limit exceeded', $userId);
    http_response_code(429);
    header('Retry-After: ' . ($e->getResetTime() - time()));
    echo json_encode($e->toResponse());
    exit;
}

try {
    $db = getDB();
    
    $format = $_GET['format'] ?? 'json'; // 'json' or 'csv'
    $dataset = $_GET['dataset'] ?? 'user_progress'; // 'user_progress', 'course_metrics', 'platform_aggregate'
    $startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-90 days'));
    $endDate = $_GET['endDate'] ?? date('Y-m-d');
    $confirmed = isset($_GET['confirmed']) && $_GET['confirmed'] === '1';
    
    // Validate date range
    $dateRange = ['start' => $startDate, 'end' => $endDate];
    
    // ========================================================================
    // DATASET: USER PROGRESS SNAPSHOTS (Anonymized)
    // ========================================================================
    
    if ($dataset === 'user_progress') {
        $stmt = $db->prepare("
            SELECT 
                user_hash,
                snapshot_date,
                age_group,
                geographic_region,
                courses_enrolled,
                courses_completed,
                total_study_hours,
                avg_mastery_score,
                days_active,
                total_lessons_completed,
                total_quizzes_attempted,
                milestones_achieved
            FROM analytics_user_snapshots
            WHERE snapshot_date BETWEEN :start_date AND :end_date
            ORDER BY snapshot_date DESC, user_hash
        ");
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Validate export limits
        $validation = validateExportParameters($dataset, $dateRange, count($data), $confirmed);
        if (!$validation['valid']) {
            logAnalyticsAccessFailure('admin_analytics_export', 'Validation failed: ' . implode(', ', $validation['errors']), $userId);
            sendJSON([
                'success' => false,
                'message' => 'Export validation failed',
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings']
            ], 400);
        }
        
        // Log export
        logAnalyticsExport($dataset, $format, $userId, $dateRange, count($data), true);
        
        if ($format === 'csv') {
            exportCSV($data, 'user_progress_export_' . date('Ymd'));
        } else {
            sendProtectedAnalyticsJSON([
                'success' => true,
                'dataset' => 'user_progress',
                'dateRange' => ['start' => $startDate, 'end' => $endDate],
                'recordCount' => count($data),
                'data' => $data,
                'metadata' => [
                    'privacy_notice' => 'All user identifiers are irreversibly hashed. No PII included.',
                    'age_groups' => ['under_13', '13_17', '18_plus', 'not_provided'],
                    'geographic_precision' => 'State/province level only'
                ]
            ], 200, 'admin_analytics_export_user_progress');
        }
        return;
    }
    
    // ========================================================================
    // DATASET: COURSE EFFECTIVENESS METRICS
    // ========================================================================
    
    if ($dataset === 'course_metrics') {
        $stmt = $db->prepare("
            SELECT 
                c.course_name,
                c.difficulty_level,
                c.subject_area,
                acm.calculation_date,
                acm.total_enrolled,
                acm.active_students,
                acm.completed_students,
                acm.completion_rate,
                acm.avg_completion_time_days,
                acm.avg_mastery_score,
                acm.avg_study_time_hours,
                acm.avg_lessons_per_student,
                acm.avg_quiz_attempts,
                acm.retry_rate
            FROM analytics_course_metrics acm
            JOIN courses c ON acm.course_id = c.course_id
            WHERE acm.calculation_date BETWEEN :start_date AND :end_date
            ORDER BY acm.calculation_date DESC, c.course_name
        ");
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Validate export limits
        $validation = validateExportParameters($dataset, $dateRange, count($data), $confirmed);
        if (!$validation['valid']) {
            logAnalyticsAccessFailure('admin_analytics_export', 'Validation failed: ' . implode(', ', $validation['errors']), $userId);
            sendJSON([
                'success' => false,
                'message' => 'Export validation failed',
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings']
            ], 400);
        }
        
        // Log export
        logAnalyticsExport($dataset, $format, $userId, $dateRange, count($data), true);
        
        if ($format === 'csv') {
            exportCSV($data, 'course_metrics_export_' . date('Ymd'));
        } else {
            sendProtectedAnalyticsJSON([
                'success' => true,
                'dataset' => 'course_metrics',
                'dateRange' => ['start' => $startDate, 'end' => $endDate],
                'recordCount' => count($data),
                'data' => $data
            ], 200, 'admin_analytics_export_course_metrics');
        }
        return;
    }
    
    // ========================================================================
    // DATASET: PLATFORM AGGREGATE STATISTICS
    // ========================================================================
    
    if ($dataset === 'platform_aggregate') {
        $stmt = $db->prepare("
            SELECT 
                rollup_date,
                total_active_users,
                new_users,
                active_course_enrollments,
                lessons_completed,
                quizzes_attempted,
                quizzes_passed,
                total_study_time_minutes,
                avg_session_duration_minutes,
                avg_mastery_score,
                mastery_90_plus_count,
                total_agent_messages,
                avg_agent_response_time_ms
            FROM analytics_daily_rollup
            WHERE rollup_date BETWEEN :start_date AND :end_date
            ORDER BY rollup_date DESC
        ");
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Validate export limits
        $validation = validateExportParameters($dataset, $dateRange, count($data), $confirmed);
        if (!$validation['valid']) {
            logAnalyticsAccessFailure('admin_analytics_export', 'Validation failed: ' . implode(', ', $validation['errors']), $userId);
            sendJSON([
                'success' => false,
                'message' => 'Export validation failed',
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings']
            ], 400);
        }
        
        // Log export
        logAnalyticsExport($dataset, $format, $userId, $dateRange, count($data), true);
        
        if ($format === 'csv') {
            exportCSV($data, 'platform_aggregate_export_' . date('Ymd'));
        } else {
            sendProtectedAnalyticsJSON([
                'success' => true,
                'dataset' => 'platform_aggregate',
                'dateRange' => ['start' => $startDate, 'end' => $endDate],
                'recordCount' => count($data),
                'data' => $data
            ], 200, 'admin_analytics_export_platform_aggregate');
        }
        return;
    }
    
    // ========================================================================
    // DATASET: AGENT EFFECTIVENESS
    // ========================================================================
    
    if ($dataset === 'agent_metrics') {
        $stmt = $db->prepare("
            SELECT 
                a.agent_name,
                a.agent_type,
                aam.calculation_date,
                aam.total_interactions,
                aam.unique_users_served,
                aam.avg_response_time_ms,
                aam.avg_response_length_chars,
                aam.avg_student_mastery,
                aam.students_improved_count,
                aam.avg_interactions_per_user,
                aam.total_messages_sent
            FROM analytics_agent_metrics aam
            JOIN agents a ON aam.agent_id = a.agent_id
            WHERE aam.calculation_date BETWEEN :start_date AND :end_date
            ORDER BY aam.calculation_date DESC, a.agent_name
        ");
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Validate export limits
        $validation = validateExportParameters($dataset, $dateRange, count($data), $confirmed);
        if (!$validation['valid']) {
            logAnalyticsAccessFailure('admin_analytics_export', 'Validation failed: ' . implode(', ', $validation['errors']), $userId);
            sendJSON([
                'success' => false,
                'message' => 'Export validation failed',
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings']
            ], 400);
        }
        
        // Log export
        logAnalyticsExport($dataset, $format, $userId, $dateRange, count($data), true);
        
        if ($format === 'csv') {
            exportCSV($data, 'agent_metrics_export_' . date('Ymd'));
        } else {
            sendProtectedAnalyticsJSON([
                'success' => true,
                'dataset' => 'agent_metrics',
                'dateRange' => ['start' => $startDate, 'end' => $endDate],
                'recordCount' => count($data),
                'data' => $data
            ], 200, 'admin_analytics_export_agent_metrics');
        }
        return;
    }
    
    logAnalyticsAccessFailure('admin_analytics_export', 'Invalid dataset: ' . $dataset, $userId);
    sendJSON(['success' => false, 'message' => 'Invalid dataset'], 400);
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    logAnalyticsAccessFailure('admin_analytics_export', 'Exception: ' . $e->getMessage(), $userId ?? 'unknown');
    sendJSON(['success' => false, 'message' => 'Failed to generate export'], 500);
}

/**
 * Export data as CSV
 */
function exportCSV($data, $filename) {
    if (empty($data)) {
        sendJSON(['success' => false, 'message' => 'No data to export'], 404);
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // CSV header row
    fputcsv($output, array_keys($data[0]));
    
    // Data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}
