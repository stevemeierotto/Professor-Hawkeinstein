<?php
// � PRIVACY REGRESSION PROTECTED (Phase 5)
// Changes require privacy review: docs/ANALYTICS_PRIVACY_VALIDATION.md
//
// �🚨 K-ANONYMITY ENFORCEMENT BOUNDARY
// Analytics responses MUST pass cohort size validation
// 
// This module enforces k-anonymity principles (k=5) by suppressing analytics metrics
// when the underlying cohort size is too small to prevent re-identification attacks.
//
// CRITICAL: All analytics endpoints must call enforceCohortMinimum() before output.
//
// Created: 2026-02-08 (Phase 3: Minimum Cohort Size Protection)

// Minimum cohort size for analytics disclosure
// Below this threshold, metrics are suppressed to prevent individual inference
define('MIN_ANALYTICS_COHORT_SIZE', 5);

/**
 * Enforce minimum cohort size for analytics response
 * 
 * Applies k-anonymity principle (k=5) to prevent re-identification attacks
 * 
 * @param mixed $payload The analytics response payload
 * @param string $contextLabel Endpoint identifier for logging
 * @return mixed Modified payload with suppressed metrics where cohort < threshold
 */
function enforceCohortMinimum($payload, $contextLabel = 'unknown_endpoint') {
    // Convert objects to arrays for uniform processing
    if (is_object($payload)) {
        $payload = json_decode(json_encode($payload), true);
    }
    
    // Skip enforcement if payload is not array-like
    if (!is_array($payload)) {
        return $payload;
    }
    
    // Track suppressions for logging
    $suppressions = [];
    
    // Apply cohort enforcement based on payload structure
    $payload = applyCohortEnforcement($payload, '', $suppressions);
    
    // Log suppressions if any occurred
    if (!empty($suppressions)) {
        logCohortSuppression($suppressions, $contextLabel);
    }
    
    return $payload;
}

/**
 * Recursively apply cohort enforcement to analytics payload
 * 
 * @param mixed $data Data structure to process
 * @param string $path Current path in structure (for logging)
 * @param array &$suppressions Array to collect suppression events
 * @return mixed Processed data with metrics suppressed where needed
 */
function applyCohortEnforcement($data, $path, &$suppressions) {
    if (!is_array($data)) {
        return $data;
    }
    
    // Check if this node represents a cohort metric group
    $cohortSize = extractCohortSize($data);
    
    if ($cohortSize !== null && $cohortSize < MIN_ANALYTICS_COHORT_SIZE) {
        // Cohort is below threshold - suppress sensitive metrics
        $data = suppressMetrics($data, $cohortSize, $path, $suppressions);
    }
    
    // Recursively process nested structures
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $currentPath = $path ? "$path.$key" : $key;
            
            // Check if this nested structure has its own cohort context
            $nestedCohortSize = extractCohortSize($data); // Check parent for studentSummary.total
            
            if ($nestedCohortSize !== null && $nestedCohortSize < MIN_ANALYTICS_COHORT_SIZE) {
                // Apply suppression to this nested structure using parent's cohort size
                $data[$key] = suppressMetrics($value, $nestedCohortSize, $currentPath, $suppressions);
            } else {
                // Continue recursive processing
                $data[$key] = applyCohortEnforcement($value, $currentPath, $suppressions);
            }
        }
    }
    
    return $data;
}

/**
 * Extract cohort size from a data node
 * 
 * Attempts to identify the number of unique students represented by metrics
 * 
 * @param array $data Data node to analyze
 * @return int|null Cohort size if determinable, null otherwise
 */
function extractCohortSize($data) {
    if (!is_array($data)) {
        return null;
    }
    
    // Common field names representing cohort size
    $cohortFields = [
        'total_enrolled',
        'total_students',
        'unique_students',
        'student_count',
        'total',
        'active_students',
        'unique_users',
        'unique_users_served'
    ];
    
    foreach ($cohortFields as $field) {
        if (isset($data[$field]) && is_numeric($data[$field])) {
            return (int)$data[$field];
        }
    }
    
    // Check for 'total' in nested studentSummary
    if (isset($data['studentSummary']['total']) && is_numeric($data['studentSummary']['total'])) {
        return (int)$data['studentSummary']['total'];
    }
    
    // Check if this is a list-like structure (array of items)
    if (isset($data[0]) && is_array($data[0])) {
        // For arrays, check if items have cohort size fields
        foreach ($data as $item) {
            $itemCohortSize = extractCohortSize($item);
            if ($itemCohortSize !== null) {
                return $itemCohortSize;
            }
        }
    }
    
    return null;
}

/**
 * Suppress sensitive metrics when cohort is below threshold
 * 
 * @param array $data Data node with metrics
 * @param int $cohortSize Actual cohort size (below threshold)
 * @param string $path Path in data structure
 * @param array &$suppressions Array to collect suppression events
 * @return array Modified data with suppressed metrics
 */
function suppressMetrics($data, $cohortSize, $path, &$suppressions) {
    // Fields that must be suppressed when cohort is too small
    $sensitiveMetrics = [
        'avg_mastery_score',
        'avg_completion_time_days',
        'avg_study_time_hours',
        'completion_rate',
        'avg_mastery',
        'avg_session_duration_minutes',
        'avg_response_time_ms',
        'avg_response_length_chars',
        'avg_interactions_per_user',
        'retry_rate',
        'avg_lessons_per_student',
        'avg_quiz_attempts',
        'avg_student_mastery',
        'students_improved_count'
    ];
    
    $suppressed = [];
    
    foreach ($sensitiveMetrics as $metric) {
        if (isset($data[$metric]) && $data[$metric] !== null) {
            $data[$metric] = null;
            $suppressed[] = $metric;
        }
    }
    
    // Add insufficient_data flag
    if (!empty($suppressed)) {
        $data['insufficient_data'] = true;
        $data['insufficient_data_reason'] = 'Cohort size below minimum threshold for privacy protection';
        
        // Record suppression event
        $suppressions[] = [
            'path' => $path ?: 'root',
            'cohort_size' => $cohortSize,
            'threshold' => MIN_ANALYTICS_COHORT_SIZE,
            'suppressed_metrics' => $suppressed
        ];
    }
    
    return $data;
}

/**
 * Suppress metrics in list/array structures
 * 
 * @param array $items Array of data items (e.g., courses, agents)
 * @param string $path Path in data structure
 * @param array &$suppressions Array to collect suppression events
 * @return array Modified array with suppressions applied per item
 */
function suppressListMetrics($items, $path, &$suppressions) {
    if (!is_array($items) || empty($items)) {
        return $items;
    }
    
    foreach ($items as $index => $item) {
        if (is_array($item)) {
            $cohortSize = extractCohortSize($item);
            
            if ($cohortSize !== null && $cohortSize < MIN_ANALYTICS_COHORT_SIZE) {
                $itemPath = "$path[$index]";
                $items[$index] = suppressMetrics($item, $cohortSize, $itemPath, $suppressions);
            }
        }
    }
    
    return $items;
}

/**
 * Log cohort suppression events
 * 
 * @param array $suppressions List of suppression events
 * @param string $contextLabel Endpoint identifier
 */
function logCohortSuppression($suppressions, $contextLabel) {
    $logMessage = sprintf(
        "[COHORT SUPPRESSION] Endpoint: %s | Events: %d | Time: %s",
        $contextLabel,
        count($suppressions),
        date('Y-m-d H:i:s')
    );
    
    error_log($logMessage);
    
    // In non-production: log details
    if (getenv('APP_ENV') !== 'production') {
        foreach ($suppressions as $suppression) {
            error_log(sprintf(
                "[COHORT SUPPRESSION DETAIL] Path: %s | Cohort: %d/%d | Suppressed: %s",
                $suppression['path'],
                $suppression['cohort_size'],
                $suppression['threshold'],
                implode(', ', $suppression['suppressed_metrics'])
            ));
        }
    }
}

/**
 * Wrapper for analytics response with both PII and cohort validation
 * 
 * Use this for all analytics endpoints to enforce both Phase 2 and Phase 3 protections
 * 
 * @param mixed $data Response payload
 * @param int $statusCode HTTP status code
 * @param string $contextLabel Endpoint identifier for logging
 */
function sendProtectedAnalyticsJSON($data, $statusCode = 200, $contextLabel = 'analytics_endpoint') {
    try {
        // Phase 3: Enforce minimum cohort size
        $data = enforceCohortMinimum($data, $contextLabel);
        
        // Phase 2: Validate for PII leakage (from analytics_response_guard.php)
        validateAnalyticsResponse($data, $contextLabel);
        
        // If both validations pass, send response
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
        
    } catch (AnalyticsPrivacyViolationException $e) {
        // Phase 2 validation failed
        http_response_code(403);
        echo json_encode($e->toResponse(), JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        // Unexpected error
        error_log("[ANALYTICS ERROR] Context: $contextLabel | Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Analytics processing error'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
