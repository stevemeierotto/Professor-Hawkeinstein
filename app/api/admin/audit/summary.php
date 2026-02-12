<?php
/**
 * 🔒 PRIVACY ENFORCEMENT AUDIT - ADMIN SUMMARY
 * Read-only aggregate audit data for admin visibility
 * 
 * Role Required: admin (or root)
 * 
 * Admins MAY view:
 * - Aggregate suppression counts
 * - Blocked response counts
 * - Rate limit violation counts
 * - High-level enforcement statistics
 * 
 * Admins CANNOT:
 * - Export audit logs
 * - View raw log files
 * - Access individual enforcement events
 * - Bypass privacy safeguards
 * 
 * Created: 2026-02-09 (Phase 6: Audit Access)
 */

define('APP_ROOT', '/var/www/html/basic_educational');
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/api/admin/auth_check.php';
require_once APP_ROOT . '/api/helpers/role_check.php';

setCORSHeaders();

// Security headers
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Require admin authentication (admin or root)
$user = requireAdmin();
$userId = $user['user_id'] ?? 'unknown';
$userRole = $user['role'] ?? 'admin';

require_once APP_ROOT . '/api/helpers/rate_limiter.php';
require_rate_limit_auto('admin_audit_summary');

// Log this audit access
logAuditAccess('view_summary', $userId, $userRole, [
    'time_window' => $_GET['window'] ?? '7d'
]);

try {
    // Parse time window
    $window = $_GET['window'] ?? '7d'; // 1d, 7d, 30d, 90d
    $hours = match($window) {
        '1d' => 24,
        '7d' => 168,
        '30d' => 720,
        '90d' => 2160,
        default => 168
    };
    
    $startTimestamp = time() - ($hours * 3600);
    
    // Read analytics audit log
    $auditLogFile = '/tmp/analytics_audit.log';
    $auditAccessLogFile = '/tmp/audit_access.log';
    
    // Initialize counters
    $stats = [
        'time_window' => $window,
        'start_time' => date('c', $startTimestamp),
        'end_time' => date('c'),
        
        // Phase 2: PII Validation
        'pii_blocks' => 0,
        
        // Phase 3: Cohort Suppression
        'cohort_suppressions' => 0,
        
        // Phase 4: Rate Limiting
        'rate_limit_violations' => 0,
        
        // Phase 4: Audit Logging
        'analytics_access_count' => 0,
        'analytics_export_count' => 0,
        'access_failures' => 0,
        
        // Phase 5: CI Checks
        'ci_check_runs' => 0, // Note: CI runs not logged to audit file
        
        // Phase 6: Audit Access
        'privileged_audit_access' => 0,
        
        // Totals
        'total_enforcement_events' => 0,
        'total_analytics_requests' => 0
    ];
    
    // Parse analytics audit log
    if (file_exists($auditLogFile)) {
        $lines = file($auditLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $event = @json_decode($line, true);
            if (!$event || !isset($event['timestamp'])) {
                continue;
            }
            
            // Filter by time window
            if ($event['timestamp'] < $startTimestamp) {
                continue;
            }
            
            // Count by action type
            $action = $event['action'] ?? '';
            
            // Analytics access (Phase 4)
            if (in_array($action, ['view_dashboard', 'view_course_detail', 'view_daily_trend', 'view_weekly_trend', 'view_monthly_trend', 'list_courses', 'view_public_metrics'])) {
                $stats['analytics_access_count']++;
                $stats['total_analytics_requests']++;
            }
            
            // Analytics exports (Phase 4)
            if ($action === 'export') {
                $stats['analytics_export_count']++;
                $stats['total_analytics_requests']++;
            }
            
            // Access failures
            if (isset($event['success']) && $event['success'] === false) {
                $stats['access_failures']++;
            }
            
            // Rate limit violations (Phase 4)
            if (strpos($action, 'Rate limit exceeded') !== false || 
                (isset($event['metadata']['reason']) && strpos($event['metadata']['reason'], 'Rate limit') !== false)) {
                $stats['rate_limit_violations']++;
                $stats['total_enforcement_events']++;
            }
            
            // PII blocks (Phase 2) - would be logged as failures with specific reason
            if (isset($event['metadata']['reason']) && strpos($event['metadata']['reason'], 'PII') !== false) {
                $stats['pii_blocks']++;
                $stats['total_enforcement_events']++;
            }
            
            // Cohort suppressions (Phase 3) - would be in metadata
            if (isset($event['metadata']['cohort_suppression']) && $event['metadata']['cohort_suppression'] === true) {
                $stats['cohort_suppressions']++;
                $stats['total_enforcement_events']++;
            }
        }
    }
    
    // Parse audit access log (Phase 6)
    if (file_exists($auditAccessLogFile)) {
        $lines = file($auditAccessLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $event = @json_decode($line, true);
            if (!$event || !isset($event['timestamp'])) {
                continue;
            }
            
            // Filter by time window
            if ($event['timestamp'] < $startTimestamp) {
                continue;
            }
            
            $stats['privileged_audit_access']++;
        }
    }
    
    // Calculate derived metrics
    $stats['enforcement_rate'] = $stats['total_analytics_requests'] > 0
        ? round(($stats['total_enforcement_events'] / $stats['total_analytics_requests']) * 100, 2)
        : 0;
    
    $stats['failure_rate'] = $stats['total_analytics_requests'] > 0
        ? round(($stats['access_failures'] / $stats['total_analytics_requests']) * 100, 2)
        : 0;
    
    // Top endpoints by access
    $endpointCounts = [];
    if (file_exists($auditLogFile)) {
        $lines = file($auditLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $event = @json_decode($line, true);
            if (!$event || !isset($event['timestamp']) || $event['timestamp'] < $startTimestamp) {
                continue;
            }
            
            $endpoint = $event['endpoint'] ?? 'unknown';
            if (!isset($endpointCounts[$endpoint])) {
                $endpointCounts[$endpoint] = 0;
            }
            $endpointCounts[$endpoint]++;
        }
    }
    
    arsort($endpointCounts);
    $stats['top_endpoints'] = array_slice($endpointCounts, 0, 5);
    
    // Response
    sendJSON([
        'success' => true,
        'message' => 'Privacy enforcement audit summary',
        'viewer_role' => $userRole,
        'access_level' => 'aggregate_only',
        'statistics' => $stats,
        'phases' => [
            'phase_1' => [
                'name' => 'Database Lock-Down',
                'status' => 'active',
                'enforcement' => 'SELECT-only analytics_reader user'
            ],
            'phase_2' => [
                'name' => 'PII Response Validation',
                'status' => 'active',
                'enforcement' => 'Blocks: ' . $stats['pii_blocks']
            ],
            'phase_3' => [
                'name' => 'Cohort Minimum (k=5)',
                'status' => 'active',
                'enforcement' => 'Suppressions: ' . $stats['cohort_suppressions']
            ],
            'phase_4' => [
                'name' => 'Operational Safeguards',
                'status' => 'active',
                'enforcement' => 'Rate limit blocks: ' . $stats['rate_limit_violations']
            ],
            'phase_5' => [
                'name' => 'CI Regression Prevention',
                'status' => 'active',
                'enforcement' => 'Automated checks on every PR'
            ],
            'phase_6' => [
                'name' => 'Audit Access',
                'status' => 'active',
                'enforcement' => 'Role-based audit visibility'
            ]
        ],
        'notice' => 'Aggregate statistics only. For full audit logs, root access required.',
        'export_capability' => hasRootRole($user) ? 'available' : 'not_available'
    ], 200);
    
} catch (Exception $e) {
    error_log("Audit summary error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to generate audit summary'], 500);
}
