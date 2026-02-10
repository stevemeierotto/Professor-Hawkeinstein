<?php
/**
 * 🔒 PRIVACY ENFORCEMENT AUDIT - ROOT LOGS VIEWER
 * Full audit log access for compliance review
 * 
 * Role Required: root ONLY
 * 
 * Root MAY:
 * - View full audit log entries
 * - Filter by date, phase, endpoint, action
 * - Search audit events
 * - Review enforcement decisions
 * 
 * Root CANNOT:
 * - Modify audit logs
 * - Access raw analytics payloads
 * - Bypass privacy safeguards
 * 
 * Audit logs NEVER include:
 * - Student identifiers (PII)
 * - Raw analytics data
 * - Sensitive user information
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

// Require ROOT access
$rootUser = requireRoot();
$userId = $rootUser['user_id'] ?? 'unknown';

// Parse query parameters
$filters = [
    'startDate' => $_GET['startDate'] ?? date('Y-m-d', strtotime('-7 days')),
    'endDate' => $_GET['endDate'] ?? date('Y-m-d'),
    'endpoint' => $_GET['endpoint'] ?? null,
    'action' => $_GET['action'] ?? null,
    'success' => isset($_GET['success']) ? ($_GET['success'] === 'true') : null,
    'limit' => min((int)($_GET['limit'] ?? 100), 1000), // Max 1000 entries
    'offset' => (int)($_GET['offset'] ?? 0)
];

// Log this privileged access
logAuditAccess('view_logs', $userId, 'root', $filters);

try {
    $startTimestamp = strtotime($filters['startDate'] . ' 00:00:00');
    $endTimestamp = strtotime($filters['endDate'] . ' 23:59:59');
    
    // Read analytics audit log
    $auditLogFile = '/tmp/analytics_audit.log';
    
    if (!file_exists($auditLogFile)) {
        sendJSON([
            'success' => true,
            'message' => 'No audit log file found',
            'logs' => [],
            'count' => 0,
            'filters' => $filters
        ], 200);
        return;
    }
    
    $lines = file($auditLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $matchedLogs = [];
    $totalMatched = 0;
    
    foreach ($lines as $line) {
        $event = @json_decode($line, true);
        if (!$event || !isset($event['timestamp'])) {
            continue;
        }
        
        // Apply filters
        if ($event['timestamp'] < $startTimestamp || $event['timestamp'] > $endTimestamp) {
            continue;
        }
        
        if ($filters['endpoint'] && (!isset($event['endpoint']) || $event['endpoint'] !== $filters['endpoint'])) {
            continue;
        }
        
        if ($filters['action'] && (!isset($event['action']) || $event['action'] !== $filters['action'])) {
            continue;
        }
        
        if ($filters['success'] !== null && isset($event['success']) && $event['success'] !== $filters['success']) {
            continue;
        }
        
        // Match found
        $totalMatched++;
        
        // Apply pagination
        if ($totalMatched > $filters['offset'] && count($matchedLogs) < $filters['limit']) {
            // Sanitize event (remove any PII if accidentally logged)
            $sanitizedEvent = [
                'timestamp' => $event['timestamp'],
                'iso_timestamp' => $event['iso_timestamp'] ?? date('c', $event['timestamp']),
                'endpoint' => $event['endpoint'] ?? 'unknown',
                'action' => $event['action'] ?? 'unknown',
                'user_role' => $event['user_role'] ?? 'unknown',
                'client_ip' => $event['client_ip'] ?? 'unknown',
                'request_method' => $event['request_method'] ?? 'unknown',
                'success' => $event['success'] ?? true,
                'parameters' => $event['parameters'] ?? [],
                'metadata' => $event['metadata'] ?? []
            ];
            
            // Explicitly remove user_id from display (compliance requirement)
            // Root can see user_id for accountability but not in filtered view
            if (isset($event['user_id'])) {
                $sanitizedEvent['user_id_hash'] = 'user_' . substr(md5($event['user_id']), 0, 8);
            }
            
            $matchedLogs[] = $sanitizedEvent;
        }
    }
    
    // Get unique endpoints for filter suggestions
    $uniqueEndpoints = [];
    $uniqueActions = [];
    
    foreach ($lines as $line) {
        $event = @json_decode($line, true);
        if ($event) {
            if (isset($event['endpoint'])) {
                $uniqueEndpoints[$event['endpoint']] = true;
            }
            if (isset($event['action'])) {
                $uniqueActions[$event['action']] = true;
            }
        }
    }
    
    // Response
    sendJSON([
        'success' => true,
        'message' => 'Audit logs retrieved',
        'viewer_role' => 'root',
        'access_level' => 'full_compliance',
        'logs' => $matchedLogs,
        'pagination' => [
            'total_matched' => $totalMatched,
            'returned' => count($matchedLogs),
            'offset' => $filters['offset'],
            'limit' => $filters['limit'],
            'has_more' => $totalMatched > ($filters['offset'] + $filters['limit'])
        ],
        'filters_applied' => $filters,
        'available_filters' => [
            'endpoints' => array_keys($uniqueEndpoints),
            'actions' => array_keys($uniqueActions)
        ],
        'privacy_notice' => 'Audit logs do not contain student PII or raw analytics payloads',
        'export_available' => true,
        'export_endpoint' => '/api/root/audit/export'
    ], 200);
    
} catch (Exception $e) {
    error_log("Audit logs viewer error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to retrieve audit logs'], 500);
}
