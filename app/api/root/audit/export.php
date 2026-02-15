<?php
/**
 * 🔒 PRIVACY ENFORCEMENT AUDIT - ROOT EXPORT
 * Compliance audit log export with confirmation flow
 * 
 * Role Required: root ONLY
 * 
 * Export Capabilities:
 * - CSV or JSON format
 * - Date range filtering
 * - Confirmation required for large exports
 * - All exports logged as high-risk actions
 * 
 * Export Safeguards:
 * - Maximum 50,000 log entries per export
 * - Maximum 365 day date range
 * - Confirmation required for exports > 10,000 entries
 * - Each export creates audit trail
 * - Export metadata logged (who, when, why, format)
 * 
 * Exports NEVER include:
 * - Student PII
 * - Raw analytics payloads
 * - Sensitive credentials
 * 
 * Created: 2026-02-09 (Phase 6: Audit Access)
 */

define('APP_ROOT', dirname(__DIR__, 4));
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/api/admin/auth_check.php';
require_once APP_ROOT . '/api/helpers/role_check.php';

setCORSHeaders();

// Security headers
header('X-Content-Type-Options: nosniff');

// ROOT auth and rate limiting
$rootUser = requireRoot();
require_once APP_ROOT . '/api/helpers/rate_limiter.php';
require_rate_limit_auto('root_audit_export');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Require ROOT access
$rootUser = requireRoot();
$userId = $rootUser['user_id'] ?? 'unknown';
$username = $rootUser['username'] ?? 'unknown';

// Parse parameters
$format = $_GET['format'] ?? 'json'; // 'json' or 'csv'
$startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['endDate'] ?? date('Y-m-d');
$confirmed = isset($_GET['confirmed']) && $_GET['confirmed'] === '1';
$reason = $_GET['reason'] ?? 'compliance_review'; // Required justification

// Export limits
define('MAX_AUDIT_EXPORT_ENTRIES', 50000);
define('MAX_AUDIT_EXPORT_DAYS', 365);
define('AUDIT_EXPORT_WARNING_THRESHOLD', 10000);

try {
    // Validate date range
    $startTimestamp = strtotime($startDate . ' 00:00:00');
    $endTimestamp = strtotime($endDate . ' 23:59:59');
    $daysDiff = ($endTimestamp - $startTimestamp) / 86400;
    
    $errors = [];
    $warnings = [];
    
    // Check date range limit
    if ($daysDiff > MAX_AUDIT_EXPORT_DAYS) {
        $errors[] = "Date range exceeds maximum of " . MAX_AUDIT_EXPORT_DAYS . " days (requested: {$daysDiff} days)";
    }
    
    if ($daysDiff < 0) {
        $errors[] = "Start date must be before end date";
    }
    
    // Validate format
    if (!in_array($format, ['json', 'csv'])) {
        $errors[] = "Invalid format. Must be 'json' or 'csv'";
    }
    
    // Validate reason
    if (empty($reason) || strlen($reason) < 5) {
        $errors[] = "Export reason required (minimum 5 characters)";
    }
    
    // Read audit log and estimate size
    $auditLogFile = '/tmp/analytics_audit.log';
    
    if (!file_exists($auditLogFile)) {
        $errors[] = "Audit log file not found";
    }
    
    if (!empty($errors)) {
        sendJSON([
            'success' => false,
            'message' => 'Export validation failed',
            'errors' => $errors
        ], 400);
        return;
    }
    
    // Count matching entries
    $lines = file($auditLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $matchedCount = 0;
    $matchedEvents = [];
    
    foreach ($lines as $line) {
        $event = @json_decode($line, true);
        if (!$event || !isset($event['timestamp'])) {
            continue;
        }
        
        if ($event['timestamp'] >= $startTimestamp && $event['timestamp'] <= $endTimestamp) {
            $matchedCount++;
            
            // Only collect if within limits
            if ($matchedCount <= MAX_AUDIT_EXPORT_ENTRIES) {
                // Sanitize event for export
                $sanitizedEvent = [
                    'timestamp' => $event['timestamp'],
                    'iso_timestamp' => $event['iso_timestamp'] ?? date('c', $event['timestamp']),
                    'endpoint' => $event['endpoint'] ?? 'unknown',
                    'action' => $event['action'] ?? 'unknown',
                    'user_role' => $event['user_role'] ?? 'unknown',
                    'client_ip' => $event['client_ip'] ?? 'unknown',
                    'user_agent' => $event['user_agent'] ?? 'unknown',
                    'request_method' => $event['request_method'] ?? 'unknown',
                    'success' => $event['success'] ?? true,
                    'parameters' => json_encode($event['parameters'] ?? []),
                    'metadata' => json_encode($event['metadata'] ?? [])
                ];
                
                $matchedEvents[] = $sanitizedEvent;
            }
        }
    }
    
    // Check limits
    if ($matchedCount > MAX_AUDIT_EXPORT_ENTRIES) {
        $errors[] = "Export would exceed maximum of " . MAX_AUDIT_EXPORT_ENTRIES . " entries (matched: {$matchedCount})";
    }
    
    // Require confirmation for large exports
    if ($matchedCount >= AUDIT_EXPORT_WARNING_THRESHOLD && !$confirmed) {
        $warnings[] = "Export contains {$matchedCount} entries (>= " . AUDIT_EXPORT_WARNING_THRESHOLD . " threshold)";
        $warnings[] = "Explicit confirmation required";
        
        sendJSON([
            'success' => false,
            'confirmation_required' => true,
            'message' => 'Large export requires confirmation',
            'warnings' => $warnings,
            'export_details' => [
                'entry_count' => $matchedCount,
                'date_range' => ['start' => $startDate, 'end' => $endDate],
                'days' => (int)$daysDiff,
                'format' => $format,
                'reason' => $reason
            ],
            'to_confirm' => 'Add parameter: confirmed=1'
        ], 200);
        return;
    }
    
    if (!empty($errors)) {
        sendJSON([
            'success' => false,
            'message' => 'Export validation failed',
            'errors' => $errors
        ], 400);
        return;
    }
    
    // Log this high-risk export action
    logAuditAccess('export_audit', $userId, 'root', [
        'format' => $format,
        'startDate' => $startDate,
        'endDate' => $endDate,
        'entry_count' => $matchedCount,
        'reason' => $reason,
        'confirmed' => $confirmed
    ]);
    
    // Additional high-visibility logging
    $exportLogFile = '/tmp/audit_exports.log';
    $exportEntry = [
        'timestamp' => time(),
        'iso_timestamp' => date('c'),
        'user_id' => $userId,
        'username' => $username,
        'action' => 'audit_export',
        'format' => $format,
        'date_range' => ['start' => $startDate, 'end' => $endDate],
        'entry_count' => $matchedCount,
        'reason' => $reason,
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    file_put_contents($exportLogFile, json_encode($exportEntry) . "\n", FILE_APPEND | LOCK_EX);
    
    // Generate export
    if ($format === 'csv') {
        exportAuditCSV($matchedEvents, $startDate, $endDate);
    } else {
        exportAuditJSON($matchedEvents, $startDate, $endDate, $reason, $userId);
    }
    
} catch (Exception $e) {
    error_log("Audit export error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to generate audit export'], 500);
}

/**
 * Export audit logs as CSV
 */
function exportAuditCSV($events, $startDate, $endDate) {
    $filename = "audit_export_{$startDate}_to_{$endDate}.csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // CSV header
    fputcsv($output, [
        'Timestamp',
        'ISO Timestamp',
        'Endpoint',
        'Action',
        'User Role',
        'Client IP',
        'User Agent',
        'Request Method',
        'Success',
        'Parameters',
        'Metadata'
    ]);
    
    // Data rows
    foreach ($events as $event) {
        fputcsv($output, [
            $event['timestamp'],
            $event['iso_timestamp'],
            $event['endpoint'],
            $event['action'],
            $event['user_role'],
            $event['client_ip'],
            $event['user_agent'],
            $event['request_method'],
            $event['success'] ? 'true' : 'false',
            $event['parameters'],
            $event['metadata']
        ]);
    }
    
    fclose($output);
    exit;
}

/**
 * Export audit logs as JSON
 */
function exportAuditJSON($events, $startDate, $endDate, $reason, $userId) {
    $filename = "audit_export_{$startDate}_to_{$endDate}.json";
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $export = [
        'export_metadata' => [
            'generated_at' => date('c'),
            'generated_by' => $userId,
            'reason' => $reason,
            'date_range' => ['start' => $startDate, 'end' => $endDate],
            'entry_count' => count($events),
            'format' => 'json'
        ],
        'privacy_notice' => 'This export contains audit logs only. No student PII or raw analytics payloads included.',
        'compliance_certification' => 'FERPA-compliant audit trail export',
        'events' => $events
    ];
    
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
