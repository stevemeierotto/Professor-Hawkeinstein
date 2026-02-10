<?php
/**
 * 🔒 PRIVACY REGRESSION PROTECTED
 * Changes to this file require privacy review (see docs/ANALYTICS_PRIVACY_VALIDATION.md)
 * Required guards: Phase 2 (PII), Phase 3 (Cohort), Phase 4 (Rate limit, Audit)
 * 
 * Public Metrics API
 * 
 * Returns aggregate platform statistics for public display
 * NO authentication required
 * NO PII - aggregate data only
 */

define('APP_ROOT', '/var/www/html/basic_educational');
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/api/helpers/analytics_response_guard.php';
require_once APP_ROOT . '/api/helpers/analytics_cohort_guard.php';
require_once APP_ROOT . '/api/helpers/analytics_rate_limiter.php';
require_once APP_ROOT . '/api/helpers/analytics_audit_log.php';

setCORSHeaders();

// Add security headers for public endpoint
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300');  // 5 minute cache
header('X-Frame-Options: DENY');

// Enforce rate limiting for public endpoint
$clientIP = getClientIP();
try {
    enforceRateLimit($clientIP, PUBLIC_ANALYTICS_RATE_LIMIT, 'public_metrics');
    $rateLimitStatus = getRateLimitStatus($clientIP, 'public_metrics');
    addRateLimitHeaders(
        $rateLimitStatus['limit'],
        $rateLimitStatus['remaining'],
        $rateLimitStatus['reset']
    );
} catch (RateLimitExceededException $e) {
    http_response_code(429);
    header('Retry-After: ' . ($e->getResetTime() - time()));
    echo json_encode($e->toResponse());
    exit;
}

// Reject any query parameters (public endpoint returns only pre-computed data)
if (!empty($_GET)) {
    logAnalyticsAccessFailure('public_metrics', 'Query parameters not allowed', 'anonymous');
    sendJSON(['success' => false, 'message' => 'Public metrics endpoint does not accept parameters'], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $db = getDB();
    
    // ========================================================================
    // FETCH PUBLIC METRICS FROM CACHE TABLE
    // ========================================================================
    
    $metricsStmt = $db->query("
        SELECT 
            metric_key,
            metric_value,
            metric_type,
            display_label,
            display_order,
            last_updated
        FROM analytics_public_metrics
        ORDER BY display_order ASC
    ");
    
    $metrics = $metricsStmt->fetchAll();
    
    // Format metrics for display
    $formattedMetrics = [];
    foreach ($metrics as $metric) {
        $formattedMetrics[] = [
            'key' => $metric['metric_key'],
            'value' => $metric['metric_value'],
            'type' => $metric['metric_type'],
            'label' => $metric['display_label'],
            'lastUpdated' => $metric['last_updated']
        ];
    }
    
    // ========================================================================
    // LAST 7 DAYS ACTIVITY TREND (for public sparkline)
    // ========================================================================
    
    $trendStmt = $db->prepare("
        SELECT 
            rollup_date,
            total_active_users,
            lessons_completed,
            avg_mastery_score
        FROM analytics_daily_rollup
        WHERE rollup_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY rollup_date ASC
    ");
    $trendStmt->execute();
    $trend = $trendStmt->fetchAll();
    
    // ========================================================================
    // AGGREGATE STATS FOR DISPLAY
    // ========================================================================
    
    // Recent milestone (last 24 hours aggregate)
    $recentStmt = $db->query("
        SELECT 
            total_active_users as users_24h,
            lessons_completed as lessons_24h
        FROM analytics_daily_rollup
        ORDER BY rollup_date DESC
        LIMIT 1
    ");
    $recentData = $recentStmt->fetch();
    
    // Subject area distribution (aggregate only)
    $subjectsStmt = $db->query("
        SELECT 
            c.subject_area,
            COUNT(DISTINCT ca.user_id) as student_count
        FROM courses c
        JOIN course_assignments ca ON c.course_id = ca.course_id
        WHERE c.is_active = 1
        GROUP BY c.subject_area
        ORDER BY student_count DESC
        LIMIT 5
    ");
    $subjects = $subjectsStmt->fetchAll();
    
    // Log successful access
    logAnalyticsAccess(
        'public_metrics',
        'view',
        'anonymous',
        'public',
        [],
        true,
        ['metric_count' => count($formattedMetrics)]
    );
    
    sendProtectedAnalyticsJSON([
        'success' => true,
        'metrics' => $formattedMetrics,
        'recentActivity' => [
            'users24h' => (int)$recentData['users_24h'],
            'lessons24h' => (int)$recentData['lessons_24h']
        ],
        'weeklyTrend' => $trend,
        'popularSubjects' => $subjects,
        'lastUpdated' => date('Y-m-d H:i:s'),
        'privacyNotice' => 'All data is aggregated. No individual student information is displayed.'
    ], 200, 'public_metrics');
    
} catch (Exception $e) {
    error_log("Public metrics error: " . $e->getMessage());
    logAnalyticsAccessFailure('public_metrics', 'Internal error: ' . $e->getMessage(), 'anonymous');
    sendJSON(['success' => false, 'message' => 'Failed to fetch public metrics'], 500);
}
