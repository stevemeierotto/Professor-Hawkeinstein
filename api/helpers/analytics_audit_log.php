<?php
// � PRIVACY REGRESSION PROTECTED (Phase 5)
// Changes require privacy review: docs/ANALYTICS_PRIVACY_VALIDATION.md
//
// �🚨 ANALYTICS AUDIT LOGGING
// All admin analytics access MUST be logged for compliance and security
// 
// This module provides append-only audit logging for analytics endpoint access.
// Logs are structured, timestamped, and stored separately from analytics data.
//
// Created: 2026-02-08 (Phase 4: Operational Safeguards)

// Audit log file location
define('ANALYTICS_AUDIT_LOG', '/tmp/analytics_audit.log');

/**
 * Log analytics endpoint access
 * 
 * Creates structured audit log entry for compliance and security monitoring
 * 
 * @param string $endpoint Endpoint accessed (e.g., 'admin_analytics_export')
 * @param string $action Action type (view, export, query)
 * @param string $userId User ID (or 'anonymous' for public)
 * @param string $userRole User role (admin, public)
 * @param array $parameters Request parameters
 * @param bool $success Whether request succeeded
 * @param array $metadata Additional metadata (e.g., record count, date range)
 */
function logAnalyticsAccess($endpoint, $action, $userId, $userRole, $parameters = [], $success = true, $metadata = []) {
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'iso_timestamp' => date('c'),
        'endpoint' => $endpoint,
        'action' => $action,
        'user_id' => $userId,
        'user_role' => $userRole,
        'client_ip' => getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'parameters' => $parameters,
        'success' => $success,
        'metadata' => $metadata
    ];
    
    // Convert to JSON for structured logging
    $logLine = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    // Append to audit log (file-based for simplicity, could be syslog/database)
    $result = @file_put_contents(ANALYTICS_AUDIT_LOG, $logLine . "\n", FILE_APPEND | LOCK_EX);
    
    if ($result === false) {
        error_log("[AUDIT LOG ERROR] Failed to write audit log for endpoint: $endpoint");
    }
    
    // Also log to system error_log for redundancy
    $summaryLog = sprintf(
        "[ANALYTICS AUDIT] Endpoint: %s | Action: %s | User: %s (%s) | Success: %s | Time: %s",
        $endpoint,
        $action,
        $userId,
        $userRole,
        $success ? 'YES' : 'NO',
        date('Y-m-d H:i:s')
    );
    error_log($summaryLog);
}

/**
 * Log export request (special case with additional details)
 * 
 * @param string $dataset Dataset being exported
 * @param string $format Export format (json, csv)
 * @param string $userId Admin user ID
 * @param array $dateRange ['start' => date, 'end' => date]
 * @param int $recordCount Number of records exported
 * @param bool $success Whether export succeeded
 */
function logAnalyticsExport($dataset, $format, $userId, $dateRange, $recordCount, $success = true) {
    logAnalyticsAccess(
        'admin_analytics_export',
        'export',
        $userId,
        'admin',
        [
            'dataset' => $dataset,
            'format' => $format,
            'start_date' => $dateRange['start'] ?? null,
            'end_date' => $dateRange['end'] ?? null
        ],
        $success,
        [
            'record_count' => $recordCount,
            'date_range_days' => $dateRange['start'] && $dateRange['end'] 
                ? (strtotime($dateRange['end']) - strtotime($dateRange['start'])) / 86400 
                : null
        ]
    );
}

/**
 * Log failed access attempt
 * 
 * @param string $endpoint Endpoint attempted
 * @param string $reason Failure reason
 * @param string $userId User ID or 'anonymous'
 */
function logAnalyticsAccessFailure($endpoint, $reason, $userId = 'anonymous') {
    logAnalyticsAccess(
        $endpoint,
        'access_denied',
        $userId,
        'unknown',
        [],
        false,
        ['failure_reason' => $reason]
    );
}

/**
 * Get recent audit log entries (for admin review)
 * 
 * @param int $limit Maximum number of entries to return
 * @return array Array of audit log entries
 */
function getRecentAuditLogs($limit = 100) {
    if (!file_exists(ANALYTICS_AUDIT_LOG)) {
        return [];
    }
    
    $lines = @file(ANALYTICS_AUDIT_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }
    
    // Get last N lines
    $lines = array_slice($lines, -$limit);
    
    // Parse JSON entries
    $entries = [];
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if (is_array($entry)) {
            $entries[] = $entry;
        }
    }
    
    return array_reverse($entries); // Most recent first
}

/**
 * Check if audit log needs rotation (file size management)
 * 
 * @return bool True if rotation needed
 */
function auditLogNeedsRotation() {
    if (!file_exists(ANALYTICS_AUDIT_LOG)) {
        return false;
    }
    
    $size = filesize(ANALYTICS_AUDIT_LOG);
    return $size > 10 * 1024 * 1024; // 10MB threshold
}

/**
 * Rotate audit log (archive old entries)
 * 
 * This should be called periodically by a cron job or admin action
 */
function rotateAuditLog() {
    if (!file_exists(ANALYTICS_AUDIT_LOG)) {
        return;
    }
    
    $archivePath = ANALYTICS_AUDIT_LOG . '.' . date('Y-m-d-His') . '.archive';
    
    if (@rename(ANALYTICS_AUDIT_LOG, $archivePath)) {
        error_log("[AUDIT LOG] Rotated audit log to: $archivePath");
        return true;
    }
    
    error_log("[AUDIT LOG ERROR] Failed to rotate audit log");
    return false;
}
